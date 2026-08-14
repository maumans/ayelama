<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Revision;
use App\Models\RevisionPoint;
use App\Services\DossierStepService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RevisionController extends Controller
{
    public function __construct(private DossierStepService $stepService) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $today = now()->toDateString();
        $user  = auth()->user();

        $query = Dossier::with(['typeActe', 'redacteur', 'revision.reviseur', 'revision.points', 'documents', 'parties.client'])
            ->visiblePar($user)
            ->enRevision()
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->where('reference', 'like', "%{$s}%")
                   ->orWhere('objet',   'like', "%{$s}%")))
            ->when($request->statut === 'en_attente', fn ($q) => $q->where(fn ($qq) =>
                $qq->whereDoesntHave('revision')
                   ->orWhereHas('revision', fn ($r) => $r->where('statut', 'en_attente'))
            ))
            ->when($request->statut === 'en_cours', fn ($q) =>
                $q->whereHas('revision', fn ($r) => $r->where('statut', 'en_cours')))
            ->when($request->retard === '1', fn ($q) =>
                $q->whereNotNull('echeance')->where('echeance', '<', $today))
            ->when($request->sort === 'reference', fn ($q) => $q->orderBy('reference'))
            ->when($request->sort === 'entree',    fn ($q) => $q->orderByDesc('etape_changed_at'))
            ->when(!in_array($request->sort, ['reference', 'entree']), fn ($q) =>
                $q->orderByRaw('echeance IS NULL, echeance ASC'));

        $stats = [
            'total'     => Dossier::visiblePar($user)->enRevision()->count(),
            'enAttente' => Dossier::visiblePar($user)->enRevision()->where(fn ($q) =>
                $q->whereDoesntHave('revision')
                  ->orWhereHas('revision', fn ($r) => $r->where('statut', 'en_attente'))
            )->count(),
            'enCours'  => Dossier::visiblePar($user)->enRevision()
                ->whereHas('revision', fn ($r) => $r->where('statut', 'en_cours'))
                ->count(),
            'enRetard' => Dossier::visiblePar($user)->enRevision()
                ->whereNotNull('echeance')->where('echeance', '<', $today)
                ->count(),
        ];

        $dossiers = $query->paginate(20)->withQueryString();

        return Inertia::render('Revisions/Index', [
            'dossiers' => $dossiers->through(fn ($d) => [
                'id'          => $d->id,
                'reference'   => $d->reference,
                'objet'       => $d->objet,
                'typeActe'    => $d->typeActe ? ['label' => $d->typeActe->label, 'categorie' => $d->typeActe->categorie?->value, 'code' => $d->typeActe->code] : null,
                'redacteur'   => $d->redacteur ? ['id' => $d->redacteur->id, 'name' => $d->redacteur->name, 'initiales' => $d->redacteur->initiales] : null,
                'clientPrincipal' => $d->clientPrincipalLabel(),
                'echeance'    => $d->echeance?->toDateString(),
                'estEnRetard' => $d->estEnRetard(),
                'revision'    => $d->revision ? [
                    'statut'       => $d->revision->statut?->value,
                    'reviseur'     => $d->revision->reviseur ? ['id' => $d->revision->reviseur->id, 'name' => $d->revision->reviseur->name, 'initiales' => $d->revision->reviseur->initiales] : null,
                    'conformes'    => $d->revision->nombreConformes(),
                    'nonConformes' => $d->revision->nombreNonConformes(),
                    'evalues'      => $d->revision->nombreEvalues(),
                    'estValidable' => $d->revision->estValidable(),
                ] : null,
            ]),
            'stats'   => $stats,
            'filters' => [
                'q'      => $request->q      ?? '',
                'statut' => $request->statut ?? '',
                'retard' => $request->retard ?? '',
                'sort'   => $request->sort   ?? '',
            ],
        ]);
    }

    public function show(Dossier $dossier)
    {
        $this->authorize('view', $dossier);

        $dossier->load(['typeActe', 'redacteur', 'documents.versionActuelle', 'revision.reviseur', 'revision.points']);

        return Inertia::render('Dossiers/Revision', [
            'dossier' => [
                'id'        => $dossier->id,
                'reference' => $dossier->reference,
                'objet'     => $dossier->objet,
                'etape'     => $dossier->etape->value,
                'typeActe'  => $dossier->typeActe?->label,
                'redacteur' => $dossier->redacteur?->name,
            ],
            // Actes seuls : l'accord signé du client est un document du dossier mais pas un
            // acte à certifier. Il apparaissait pourtant comme un point de la grille, alors
            // que Revision::pointsValides() ne le comptait pas — la grille affichait donc
            // un point de plus qu'elle n'en attendait, et la validation restait impossible.
            'documents' => $dossier->documents
                ->where('categorie', '!=', 'accord_client')
                ->map(fn ($doc) => [
                    'id'            => $doc->id,
                    'nom'           => $doc->nom,
                    // ⚠️ Lisait `$doc->type_document` et `$doc->chemin_fichier` — deux colonnes qui
                    // n'existent pas sur `DocumentFichier` depuis l'unification GED du 2026-07-24 :
                    // le type est `categorie`, et le fichier vit sur la version courante. Le type
                    // était donc vide, et `has_file` **toujours faux** — l'aperçu et le
                    // téléchargement disparaissaient de l'écran où le certificateur doit lire les
                    // actes.
                    'categorie'     => $doc->categorie,
                    'typeDocLabel'  => $doc->typeDocumentLabel(),
                    'statut'        => $doc->statut,
                    'url_download'  => route('documents.download', $doc),
                    'url_preview'   => route('documents.preview', $doc),
                    'has_file'      => (bool) $doc->versionActuelle,
                ])->values(),
            'revision' => $dossier->revision ? [
                'id'           => $dossier->revision->id,
                'statut'       => $dossier->revision->statut?->value,
                'commentaire'  => $dossier->revision->commentaire,
                'points'       => $dossier->revision->points->keyBy('point_id')->map(fn ($p) => [
                    'etat'        => $p->etat,
                    'commentaire' => $p->commentaire,
                    'perime'      => (bool) $p->perime,
                ]),
                'estValidable' => $dossier->revision->estValidable(),
            ] : null,
            'can' => [
                'update'   => auth()->user()->can('reviser', $dossier),
                'valider'  => $dossier->revision && auth()->user()->can('valider', $dossier->revision),
                'renvoyer' => $dossier->revision && auth()->user()->can('renvoyer', $dossier->revision),
            ],
        ]);
    }

    public function update(Request $request, Dossier $dossier)
    {
        $this->authorize('reviser', $dossier);

        $revision = $dossier->revision;
        if (!$revision) {
            $revision = Revision::create([
                'dossier_id'  => $dossier->id,
                'reviseur_id' => $dossier->reviseur_id,
                'statut'      => \App\Enums\StatutRevision::EnCours,
            ]);
        }

        $points = $request->validate([
            // `prelude` : cette sauvegarde n'est qu'une étape technique avant valider ou
            // renvoyer (voir Revision.jsx). Sans ce drapeau, « Grille de certification
            // sauvegardée » s'affichait alors même que l'action voulue échouait ensuite —
            // un succès trompeur à côté d'une erreur.
            'prelude'  => ['sometimes', 'boolean'],
            'points'   => ['required', 'array'],
            'points.*' => ['required', 'array'],
            'points.*.etat'        => ['nullable', 'string', 'in:ok,a_corriger'],
            'points.*.commentaire' => ['nullable', 'string', 'max:500', 'required_if:points.*.etat,a_corriger'],
        ])['points'];

        foreach ($points as $pointId => $data) {
            if (($data['etat'] ?? null) === null) continue;
            // perime => false : un nouveau verdict vient d'être saisi sur ce document (qu'il
            // ait été régénéré depuis ou non) — il redevient fiable et compte à nouveau comme
            // évalué (voir Revision::pointsValides()).
            RevisionPoint::updateOrCreate(
                ['revision_id' => $revision->id, 'point_id' => $pointId],
                ['etat' => $data['etat'], 'commentaire' => $data['commentaire'] ?? null, 'perime' => false]
            );
        }

        $revision->update(['statut' => \App\Enums\StatutRevision::EnCours]);

        return $request->boolean('prelude')
            ? back()
            : back()->with('success', 'Grille de certification sauvegardée.');
    }

    public function valider(Dossier $dossier)
    {
        $revision = $dossier->revision;
        abort_unless($revision, 404);
        $this->authorize('valider', $revision);

        // Préconditions d'état, séparées de l'autorisation : elles produisent un message
        // qui nomme la cause au lieu du « Accès refusé » 403 que renvoyait la policy.
        $aCertifier = $revision->documentsACertifier()->count();
        if ($aCertifier === 0) {
            throw ValidationException::withMessages([
                'revision' => ['Aucun acte à certifier : générez les actes du dossier avant de valider.'],
            ]);
        }
        if (!$revision->tousEvalues()) {
            $restants = $aCertifier - $revision->nombreEvalues();
            throw ValidationException::withMessages([
                'revision' => ["Certification incomplète : {$restants} acte(s) sur {$aCertifier} n'ont pas encore été évalués."],
            ]);
        }
        if ($revision->nombreNonConformes() > 0) {
            throw ValidationException::withMessages([
                'revision' => [
                    $revision->nombreNonConformes() . " acte(s) sont marqués « à corriger » : renvoyez le dossier en correction, ou revoyez ces points avant de valider.",
                ],
            ]);
        }

        $revision->valider(auth()->user());
        $this->stepService->avancer($dossier, auth()->user());

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', 'Certification validée — dossier transmis pour signature.');
    }

    public function renvoyer(Request $request, Dossier $dossier)
    {
        $revision = $dossier->revision;
        abort_unless($revision, 404);
        $this->authorize('renvoyer', $revision);

        if ($revision->nombreNonConformes() === 0) {
            throw ValidationException::withMessages([
                'revision' => ["Aucun acte n'est marqué « à corriger » : un renvoi doit indiquer ce qui doit être repris."],
            ]);
        }

        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:500']])['motif'] ?? null;
        $revision->renvoyer(auth()->user(), $motif);
        $this->stepService->reculer($dossier, auth()->user(), $motif);

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', 'Dossier renvoyé en édition.');
    }
}
