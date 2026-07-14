<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Http\Requests\StoreDossierRequest;
use App\Http\Requests\UpdateDossierRequest;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
use App\Models\ModeleCourrier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\DossierStepService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DossierController extends Controller
{
    public function __construct(private DossierStepService $stepService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user  = auth()->user();
        $today = now()->toDateString();

        $query = Dossier::visiblePar($user)
            ->with(['typeActe', 'redacteur', 'notaire', 'revision:id,dossier_id,statut'])
            ->withCount([
                'documents',
                'formalites as formalites_non_clos' => fn ($q) => $q->where('statut', '!=', 'cloture'),
            ])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->where('reference', 'like', "%{$s}%")
                   ->orWhere('objet', 'like', "%{$s}%")))
            ->when($request->etape,     fn ($q, $e)   => $q->where('etape', $e))
            ->when($request->categorie, fn ($q, $cat) => $q->whereHas('typeActe', fn ($qq) => $qq->where('categorie', $cat)))
            ->when($request->retard === '1', fn ($q)  => $q->where('etape', '!=', 'cloture')->whereNotNull('echeance')->where('echeance', '<', $today))
            ->when($request->sort === 'echeance',  fn ($q) => $q->orderBy('echeance'))
            ->when($request->sort === 'reference', fn ($q) => $q->orderBy('reference'))
            ->when($request->sort === 'valeur',    fn ($q) => $q->orderByDesc('valeur'))
            ->when(!in_array($request->sort, ['echeance', 'reference', 'valeur']), fn ($q) => $q->orderByDesc('updated_at'));

        // Stats globales (indépendantes des filtres courants)
        $parEtapeRaw = Dossier::selectRaw('etape, count(*) as n')
            ->groupBy('etape')
            ->pluck('n', 'etape');

        $etapesOrdered = collect(EtapeDossier::ordered());

        $stats = [
            'total'        => Dossier::count(),
            'enCours'      => Dossier::where('etape', '!=', 'cloture')->count(),
            'enRetard'     => Dossier::where('etape', '!=', 'cloture')
                                ->whereNotNull('echeance')
                                ->where('echeance', '<', $today)
                                ->count(),
            'cloturesMois' => Dossier::where('etape', 'cloture')
                                ->whereMonth('updated_at', now()->month)
                                ->whereYear('updated_at', now()->year)
                                ->count(),
            'montantTotal' => (int) Dossier::whereNotNull('valeur')->sum('valeur'),
            'parEtape'     => $etapesOrdered->map(fn ($e) => [
                'value' => $e->value,
                'label' => $e->label(),
                'count' => $parEtapeRaw[$e->value] ?? 0,
            ])->values(),
        ];

        $dossiers = $query->paginate(25)->withQueryString();

        return Inertia::render('Dossiers/Index', [
            'dossiers'   => $dossiers->through(fn ($d) => [
                ...$this->dossierToArray($d),
                'etapeOrdre'  => $d->etapeOrdre(),
                'canAvancer'  => $user->can('avancer', $d),
                'peutAvancer' => match ($d->etape) {
                    EtapeDossier::Initialisation => !empty(trim($d->objet ?? '')) && $d->notaire_id && $d->reviseur_id,
                    EtapeDossier::Edition        => $d->documents_count > 0,
                    EtapeDossier::Revision       => $d->revision?->statut?->value === 'valide',
                    EtapeDossier::Formalites     => $d->formalites_non_clos === 0,
                    default                      => true,
                },
            ]),
            'filters'    => [
                'q'         => $request->q         ?? '',
                'etape'     => $request->etape      ?? '',
                'categorie' => $request->categorie  ?? '',
                'retard'    => $request->retard     ?? '',
                'sort'      => $request->sort       ?? '',
            ],
            'etapes'     => $etapesOrdered->map(fn ($e) => ['value' => $e->value, 'label' => $e->label()]),
            'categories' => collect(CategorieActe::cases())->map(fn ($c) => ['value' => $c->value, 'label' => $c->label()]),
            'stats'      => $stats,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Dossier::class);

        return Inertia::render('Dossiers/Create', [
            'typesActes' => TypeActe::actif()->with('modeles')->get()->groupBy(fn ($t) => $t->categorie->value)->map(fn ($group) => $group->map(fn ($t) => [
                'id'          => $t->id,
                'code'        => $t->code,
                'label'       => $t->label,
                'categorie'   => $t->categorie->value,
                'delai_jours' => $t->delai_jours,
                'description' => $t->description,
                'modeles'     => $t->modeles->pluck('nom'),
            ])),
            'notaires'   => User::withRole('notaire')->where('actif', true)->get(['id', 'name', 'initiales']),
            'reviseurs'  => User::withRole('reviseur')->where('actif', true)->get(['id', 'name', 'initiales']),
            'formalistes' => User::withRole('formaliste')->where('actif', true)->get(['id', 'name', 'initiales']),
        ]);
    }

    public function store(
        StoreDossierRequest $request,
        \App\Services\ActesGeneratorService $generatorService,
        \App\Services\FacturationService $facturationService,
        \App\Services\FormaliteGenerationService $formaliteGenerationService
    ) {
        $dossier = $this->creerDossier(
            $request->validated(),
            $generatorService,
            $facturationService,
            $formaliteGenerationService
        );

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', "Dossier {$dossier->reference} créé et documents générés avec succès.");
    }

    /**
     * Création d'un dossier à partir d'un tableau de données déjà validées
     * (même forme que StoreDossierRequest::rules()) — factorisé pour être
     * réutilisé par DemandeController::convertir() (conversion d'une demande
     * externe en dossier), sans dupliquer la transaction.
     */
    public function creerDossier(
        array $data,
        \App\Services\ActesGeneratorService $generatorService,
        \App\Services\FacturationService $facturationService,
        \App\Services\FormaliteGenerationService $formaliteGenerationService
    ): Dossier {
        $typeActe = TypeActe::findOrFail($data['type_acte_id']);

        return DB::transaction(function () use ($data, $typeActe, $generatorService, $facturationService, $formaliteGenerationService) {
            $reference = $this->genererReference($typeActe);

            $dossier = Dossier::create([
                'reference'     => $reference,
                'type_acte_id'  => $typeActe->id,
                'etape'         => EtapeDossier::Initialisation,
                'redacteur_id'  => auth()->id(),
                'reviseur_id'   => $data['reviseur_id'] ?? null,
                'notaire_id'    => $data['notaire_id'],
                'formaliste_id' => $data['formaliste_id'] ?? null,
                'objet'         => $data['objet'],
                'valeur'        => $data['valeur'] ?? null,
                'echeance'      => $data['echeance'] ?? null,
                'urgent'        => $data['urgent'] ?? false,
                'notes'         => $data['notes'] ?? null,
            ]);

            Questionnaire::create([
                'dossier_id' => $dossier->id,
                'donnees'    => $data['donnees'] ?? [],
            ]);

            foreach ($data['parties'] ?? [] as $partieData) {
                Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));
            }

            // Génération automatique des documents depuis les ModèlesActes actifs
            $modeles = ModeleActe::where('type_acte_id', $typeActe->id)
                ->where('est_actif', true)
                ->orderBy('type_document')
                ->get();

            foreach ($modeles as $modele) {
                $chemin = $generatorService->genererDocument(
                    $dossier,
                    $modele->chemin_fichier,
                    Str::slug($modele->nom)
                );

                \App\Models\Document::create([
                    'dossier_id'     => $dossier->id,
                    'nom'            => $modele->nom,
                    'type_document'  => $modele->type_document,
                    'statut'         => 'a_editer',
                    'chemin_fichier' => $chemin,
                    'version'        => $modele->version ?? 'v1',
                ]);
            }

            // Génération automatique de la facture (note de frais)
            // Le service déduit l'assiette du questionnaire (capital, prix, loyers…)
            // et applique les barèmes configurés en base
            $dossier->load('questionnaire');
            $facturationService->genererFacture($dossier);

            // Génération automatique des formalités à partir des barèmes marqués
            // "génère une formalité" pour ce type d'acte (table baremes)
            $formaliteGenerationService->genererFormalites($dossier);

            JournalActivite::enregistrer($dossier, 'Dossier créé', 'creation', [
                'type_acte' => $typeActe->label,
            ]);

            return $dossier;
        });
    }

    public function edit(Dossier $dossier)
    {
        return redirect()->route('dossiers.show', $dossier->reference);
    }

    public function show(Dossier $dossier)
    {
        $this->authorize('view', $dossier);

        $dossier->load([
            'typeActe', 'redacteur', 'reviseur', 'notaire', 'formaliste',
            'questionnaire', 'documents', 'revision.reviseur', 'revision.points',
            'formalites.pieces', 'formalites.dependDe', 'formalites.dependants',
            'parties.client', 'journal.user', 'factures.lignes',
            'courriers.redacteur',
        ]);

        return Inertia::render('Dossiers/Show', [
            'dossier'    => $this->dossierDetailToArray($dossier),
            'can'        => [
                'update'          => auth()->user()->can('update', $dossier),
                'avancer'         => auth()->user()->can('avancer', $dossier),
                'reviser'         => auth()->user()->can('reviser', $dossier),
                'gererFormalites' => auth()->user()->can('gererFormalites', $dossier),
                'genererDocuments' => auth()->user()->can('genererDocuments', $dossier),
                'genererCourriers' => auth()->user()->can('genererCourriers', $dossier),
                'reassigner'      => auth()->user()->can('reassigner', $dossier),
                'delete'          => auth()->user()->can('delete', $dossier),
                'validerRevision'  => $dossier->revision && auth()->user()->can('valider', $dossier->revision),
                'renvoyerRevision' => $dossier->revision && auth()->user()->can('renvoyer', $dossier->revision),
            ],
            'reviseurs'   => User::withRole('reviseur')->where('actif', true)->get(['id', 'name']),
            'formalistes' => User::withRole('formaliste')->where('actif', true)->get(['id', 'name']),
            'notaires'    => User::withRole('notaire')->where('actif', true)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateDossierRequest $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $data = $request->validated();

        if (!auth()->user()->can('reassigner', $dossier)) {
            unset($data['notaire_id'], $data['reviseur_id'], $data['formaliste_id']);
        }

        $dossier->update($data);

        JournalActivite::enregistrer($dossier, 'Informations du dossier mises à jour', 'modification', []);

        return back()->with('success', 'Dossier mis à jour.');
    }

    public function updateQuestionnaire(Request $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $validated = $request->validate([
            'donnees'         => ['required', 'array'],
            'managedRoles'    => ['nullable', 'array'],
            'managedRoles.*'  => ['string', 'max:100'],
            ...StoreDossierRequest::partiesRules(),
        ]);

        DB::transaction(function () use ($dossier, $validated) {
            if ($dossier->questionnaire) {
                $dossier->questionnaire->update(['donnees' => $validated['donnees']]);
            } else {
                Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $validated['donnees']]);
            }

            $managedRoles = $validated['managedRoles'] ?? [];
            if (!empty($managedRoles)) {
                $dossier->parties()->whereIn('role', $managedRoles)->delete();
                foreach ($validated['parties'] ?? [] as $partieData) {
                    Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));
                }
            }
        });

        JournalActivite::enregistrer($dossier, 'Questionnaire mis à jour', 'modification', []);

        return back()->with('success', 'Questionnaire mis à jour.');
    }

    public function destroy(Dossier $dossier)
    {
        $this->authorize('delete', $dossier);

        $ref = $dossier->reference;
        $dossier->delete();

        return redirect()->route('dossiers.index')
            ->with('success', "Dossier {$ref} archivé.");
    }

    public function avancer(Dossier $dossier)
    {
        $this->authorize('avancer', $dossier);

        $dossier = $this->stepService->avancer($dossier, auth()->user());

        return back()->with('success', "Dossier avancé à l'étape : {$dossier->etape->label()}.");
    }

    public function genererDocuments(
        Dossier $dossier,
        \App\Services\ActesGeneratorService $generatorService
    ) {
        $this->authorize('genererDocuments', $dossier);

        $dossier->load(['typeActe', 'questionnaire']);

        $modeles = ModeleActe::where('type_acte_id', $dossier->type_acte_id)
            ->where('est_actif', true)
            ->orderBy('type_document')
            ->get();

        if ($modeles->isEmpty()) {
            return back()->with('error', "Aucun modèle actif pour ce type de dossier. Configurez les modèles d'actes avant de générer.");
        }

        $existingNoms = $dossier->documents()->pluck('nom')->toArray();
        $count = 0;

        foreach ($modeles as $modele) {
            if (in_array($modele->nom, $existingNoms)) {
                continue;
            }

            $chemin = $generatorService->genererDocument(
                $dossier,
                $modele->chemin_fichier,
                Str::slug($modele->nom)
            );

            \App\Models\Document::create([
                'dossier_id'     => $dossier->id,
                'nom'            => $modele->nom,
                'type_document'  => $modele->type_document,
                'statut'         => 'a_editer',
                'chemin_fichier' => $chemin,
                'version'        => $modele->version ?? 'v1',
            ]);

            $count++;
        }

        JournalActivite::enregistrer($dossier, "{$count} document(s) généré(s) depuis les modèles", 'creation', []);

        $msg = $count > 0
            ? "{$count} document(s) généré(s) avec succès depuis les modèles."
            : 'Tous les documents de ce modèle sont déjà présents dans le dossier.';

        return back()->with('success', $msg);
    }

    private function genererReference(TypeActe $typeActe): string
    {
        $prefixe = $typeActe->prefixe_reference;
        $annee   = date('Y');
        $last    = Dossier::where('reference', 'like', "{$prefixe}-{$annee}-%")
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last);
            $seq   = (int) end($parts) + 1;
        }

        return sprintf('%s-%s-%04d', $prefixe, $annee, $seq);
    }

    private function dossierToArray(Dossier $d): array
    {
        return [
            'id'         => $d->id,
            'reference'  => $d->reference,
            'objet'      => $d->objet,
            'etape'      => ['value' => $d->etape->value, 'label' => $d->etape->label()],
            'typeActe'   => $d->typeActe ? ['label' => $d->typeActe->label, 'categorie' => $d->typeActe->categorie?->value, 'code' => $d->typeActe->code] : null,
            'redacteur'  => $d->redacteur ? ['name' => $d->redacteur->name, 'initiales' => $d->redacteur->initiales] : null,
            'notaire'    => $d->notaire   ? ['name' => $d->notaire->name,   'initiales' => $d->notaire->initiales]   : null,
            'valeur'     => $d->valeur,
            'echeance'   => $d->echeance?->toDateString(),
            'urgent'     => $d->urgent,
            'notes'      => $d->notes,
            'estEnRetard' => $d->estEnRetard(),
            'updated_at' => $d->updated_at->diffForHumans(),
        ];
    }

    private function dossierDetailToArray(Dossier $d): array
    {
        return [
            ...$this->dossierToArray($d),
            'reviseur'    => $d->reviseur   ? ['id' => $d->reviseur->id,   'name' => $d->reviseur->name,   'initiales' => $d->reviseur->initiales]   : null,
            'formaliste'  => $d->formaliste ? ['id' => $d->formaliste->id, 'name' => $d->formaliste->name, 'initiales' => $d->formaliste->initiales] : null,
            'etapeOrdre'  => $d->etapeOrdre(),
            'questionnaire' => $d->questionnaire?->donnees,
            'documents'   => $d->documents->map(fn ($doc) => [
                'id'             => $doc->id,
                'nom'            => $doc->nom,
                'type_document'  => $doc->type_document,
                'version'        => $doc->version,
                'statut'         => $doc->statut,
                'chemin_fichier' => $doc->chemin_fichier,
                'has_file'       => (bool) $doc->chemin_fichier,
                'url_download'   => route('documents.download', $doc),
                'url_preview'    => route('documents.preview', $doc),
            ]),
            'revision'    => $d->revision ? [
                'id'         => $d->revision->id,
                'statut'     => $d->revision->statut?->value,
                'commentaire' => $d->revision->commentaire,
                'reviseur'   => $d->revision->reviseur ? ['name' => $d->revision->reviseur->name] : null,
                'points'     => $d->revision->points->map(fn ($p) => [
                    'point_id'    => $p->point_id,
                    'etat'        => $p->etat,
                    'commentaire' => $p->commentaire,
                ]),
                'estValidable' => $d->revision->estValidable(),
            ] : null,
            'formalites'  => $d->formalites
                ->sortBy([['ordre', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($f) => $f->versArray(auth()->user())),
            'parties' => $d->parties->map(fn ($p) => [
                'id'        => $p->id,
                'nom'       => $p->nom,
                'role'      => $p->role,
                'cni'       => $p->cni,
                'telephone' => $p->telephone,
                'adresse'   => $p->adresse,
                'email'     => $p->email,
                'initiales' => $p->initiales,
                'client_id' => $p->client_id,
                'client'    => $p->client ? [
                    'id'           => $p->client->id,
                    'type'         => $p->client->type,
                    'civilite'     => $p->client->civilite,
                    'prenom_nom'   => $p->client->prenom_nom,
                    'denomination' => $p->client->denomination,
                    'piece_numero' => $p->client->piece_numero,
                    'telephone'    => $p->client->telephone,
                    'forme'        => $p->client->forme,
                    'rccm'         => $p->client->rccm,
                ] : null,
            ]),
            'journal' => $d->journal->map(fn ($j) => [
                'id'         => $j->id,
                'action'     => $j->action,
                'type'       => $j->type,
                'user'       => $j->user ? ['name' => $j->user->name, 'initiales' => $j->user->initiales] : null,
                'created_at' => $j->created_at->diffForHumans(),
            ]),
            'factures' => $d->factures->map(fn ($f) => [
                'id'                => $f->id,
                'note_numero'       => $f->note_numero,
                'note_date'         => $f->note_date ? $f->note_date->toIso8601String() : null,
                'objet'             => $f->objet,
                'assiette_chiffres' => (float) $f->assiette_chiffres,
                'total_chiffres'    => (float) $f->total_chiffres,
                'lignes'            => $f->lignes->map(fn ($l) => [
                    'designation' => $l->designation,
                    'quantite'    => $l->quantite,
                    'montant'     => (float) $l->montant,
                ]),
            ]),
            'courriers' => $d->courriers->map(fn ($c) => [
                'id'             => $c->id,
                'reference'      => $c->reference,
                'objet'          => $c->objet,
                'destinataire'   => $c->destinataire,
                'type'           => $c->type,
                'statut'         => $c->statut,
                'envoye_at'      => $c->envoye_at?->format('d/m/Y'),
                'redacteur'      => $c->redacteur?->name,
                'chemin_fichier' => $c->chemin_fichier,
                'has_file'       => (bool) $c->chemin_fichier,
                'url_download'   => $c->chemin_fichier ? route('courriers.download', $c) : null,
                'url_preview'    => $c->chemin_fichier ? route('courriers.preview', $c) : null,
            ]),
            'courrierModelesApplicables' => $this->courrierModelesApplicables($d),
        ];
    }

    /**
     * Lettres de transmission (ModeleCourrier) applicables au type d'acte précis
     * du dossier — sert à la fois à l'onglet Expédition et au blocage de sortie
     * d'étape (DossierStepService::verifierExpedition).
     */
    private function courrierModelesApplicables(Dossier $d): \Illuminate\Support\Collection
    {
        return ModeleCourrier::with('typesActes')
            ->actif()
            ->get()
            ->filter(fn (ModeleCourrier $m) => $m->applicablePour($d->typeActe))
            ->map(fn (ModeleCourrier $m) => ['id' => $m->id, 'nom' => $m->nom])
            ->values();
    }
}
