<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Http\Requests\StoreDossierRequest;
use App\Http\Requests\UpdateDossierRequest;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
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

        $query = Dossier::with(['typeActe', 'redacteur', 'notaire', 'revision:id,dossier_id,statut'])
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
            'typesActes' => TypeActe::actif()->get()->groupBy(fn ($t) => $t->categorie->value)->map(fn ($group) => $group->map(fn ($t) => [
                'id'          => $t->id,
                'code'        => $t->code,
                'label'       => $t->label,
                'categorie'   => $t->categorie->value,
                'delai_jours' => $t->delai_jours,
                'description' => $t->description,
            ])),
            'notaires'   => User::where('role', 'notaire')->where('actif', true)->get(['id', 'name', 'initiales']),
            'reviseurs'  => User::where('role', 'reviseur')->where('actif', true)->get(['id', 'name', 'initiales']),
            'formalistes' => User::where('role', 'formaliste')->where('actif', true)->get(['id', 'name', 'initiales']),
        ]);
    }

    public function store(
        StoreDossierRequest $request,
        \App\Services\ActesGeneratorService $generatorService,
        \App\Services\FacturationService $facturationService
    ) {
        $typeActe = TypeActe::findOrFail($request->type_acte_id);

        $dossier = DB::transaction(function () use ($request, $typeActe, $generatorService, $facturationService) {
            $reference = $this->genererReference($typeActe);

            $dossier = Dossier::create([
                'reference'     => $reference,
                'type_acte_id'  => $typeActe->id,
                'etape'         => EtapeDossier::Initialisation,
                'redacteur_id'  => auth()->id(),
                'reviseur_id'   => $request->reviseur_id,
                'notaire_id'    => $request->notaire_id,
                'formaliste_id' => $request->formaliste_id,
                'objet'         => $request->objet,
                'valeur'        => $request->valeur,
                'echeance'      => $request->echeance,
            ]);

            Questionnaire::create([
                'dossier_id' => $dossier->id,
                'donnees'    => $request->donnees ?? [],
            ]);

            foreach ($request->parties ?? [] as $partieData) {
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

            JournalActivite::enregistrer($dossier, 'Dossier créé', 'creation', [
                'type_acte' => $typeActe->label,
            ]);

            return $dossier;
        });

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', "Dossier {$dossier->reference} créé et documents générés avec succès.");
    }

    public function edit(Dossier $dossier)
    {
        return redirect()->route('dossiers.show', $dossier->reference);
    }

    public function show(Dossier $dossier)
    {
        $dossier->load([
            'typeActe', 'redacteur', 'reviseur', 'notaire', 'formaliste',
            'questionnaire', 'documents', 'revision.reviseur', 'revision.points',
            'formalites.pieces', 'parties', 'journal.user', 'factures.lignes'
        ]);

        $this->authorize('view', $dossier);

        return Inertia::render('Dossiers/Show', [
            'dossier'    => $this->dossierDetailToArray($dossier),
            'can'        => [
                'update'          => auth()->user()->can('update', $dossier),
                'avancer'         => auth()->user()->can('avancer', $dossier),
                'reviser'         => auth()->user()->can('reviser', $dossier),
                'gererFormalites' => auth()->user()->can('gererFormalites', $dossier),
                'delete'          => auth()->user()->can('delete', $dossier),
            ],
            'reviseurs'   => User::where('role', 'reviseur')->where('actif', true)->get(['id', 'name']),
            'formalistes' => User::where('role', 'formaliste')->where('actif', true)->get(['id', 'name']),
            'notaires'    => User::where('role', 'notaire')->where('actif', true)->get(['id', 'name']),
        ]);
    }

    public function update(UpdateDossierRequest $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $dossier->update($request->validated());

        JournalActivite::enregistrer($dossier, 'Informations du dossier mises à jour', 'modification', []);

        return back()->with('success', 'Dossier mis à jour.');
    }

    public function updateQuestionnaire(Request $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $validated = $request->validate(['donnees' => ['required', 'array']]);

        if ($dossier->questionnaire) {
            $dossier->questionnaire->update(['donnees' => $validated['donnees']]);
        } else {
            Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $validated['donnees']]);
        }

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
            'formalites'  => $d->formalites->map(fn ($f) => [
                'id'           => $f->id,
                'organisme'    => $f->organisme,
                'organismeLabel' => $f->labelOrganisme(),
                'statut'       => $f->statut?->value,
                'montant_base' => $f->montant_base,
                'taux'         => $f->taux,
                'montant_calcule' => $f->montant_calcule,
                'echeance_at'  => $f->echeance_at?->toDateTimeString(),
                'estUrgente'   => $f->estUrgente(),
                'estDepassee'  => $f->estDepassee(),
                'pieces'       => $f->pieces->map(fn ($p) => [
                    'id'         => $p->id,
                    'label'      => $p->label,
                    'est_fourni' => $p->est_fourni,
                ]),
            ]),
            'parties' => $d->parties->map(fn ($p) => [
                'id'        => $p->id,
                'nom'       => $p->nom,
                'role'      => $p->role,
                'cni'       => $p->cni,
                'telephone' => $p->telephone,
                'adresse'   => $p->adresse,
                'email'     => $p->email,
                'initiales' => $p->initiales,
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
        ];
    }
}
