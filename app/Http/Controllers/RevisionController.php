<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Revision;
use App\Models\RevisionPoint;
use App\Services\DossierStepService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RevisionController extends Controller
{
    public function __construct(private DossierStepService $stepService) {}

    public function index(Request $request)
    {
        $today = now()->toDateString();

        $query = Dossier::with(['typeActe', 'redacteur', 'revision.reviseur', 'revision.points'])
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
            'total'     => Dossier::enRevision()->count(),
            'enAttente' => Dossier::enRevision()->where(fn ($q) =>
                $q->whereDoesntHave('revision')
                  ->orWhereHas('revision', fn ($r) => $r->where('statut', 'en_attente'))
            )->count(),
            'enCours'  => Dossier::enRevision()
                ->whereHas('revision', fn ($r) => $r->where('statut', 'en_cours'))
                ->count(),
            'enRetard' => Dossier::enRevision()
                ->whereNotNull('echeance')->where('echeance', '<', $today)
                ->count(),
        ];

        $dossiers = $query->paginate(20)->withQueryString();

        return Inertia::render('Revisions/Index', [
            'dossiers' => $dossiers->through(fn ($d) => [
                'id'          => $d->id,
                'reference'   => $d->reference,
                'objet'       => $d->objet,
                'typeActe'    => $d->typeActe?->label,
                'redacteur'   => $d->redacteur?->name,
                'echeance'    => $d->echeance?->toDateString(),
                'estEnRetard' => $d->estEnRetard(),
                'revision'    => $d->revision ? [
                    'statut'       => $d->revision->statut?->value,
                    'reviseur'     => $d->revision->reviseur?->name,
                    'conformes'    => $d->revision->nombreConformes(),
                    'nonConformes' => $d->revision->nombreNonConformes(),
                    'evalues'      => $d->revision->nombreEvalues(),
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
        $dossier->load(['typeActe', 'redacteur', 'revision.reviseur', 'revision.points']);

        $this->authorize('view', $dossier);

        $grille = $dossier->typeActe?->grilleActive;

        return Inertia::render('Dossiers/Revision', [
            'dossier' => [
                'id'        => $dossier->id,
                'reference' => $dossier->reference,
                'objet'     => $dossier->objet,
                'etape'     => $dossier->etape->value,
                'typeActe'  => $dossier->typeActe?->label,
                'redacteur' => $dossier->redacteur?->name,
            ],
            'revision' => $dossier->revision ? [
                'id'         => $dossier->revision->id,
                'statut'     => $dossier->revision->statut?->value,
                'commentaire' => $dossier->revision->commentaire,
                'points'     => $dossier->revision->points->keyBy('point_id')->map(fn ($p) => [
                    'etat'        => $p->etat,
                    'commentaire' => $p->commentaire,
                ]),
                'estValidable' => $dossier->revision->estValidable(),
            ] : null,
            'grille'  => $grille?->groupes(),
            'can'     => [
                'update'   => auth()->user()->can('reviser', $dossier),
                'valider'  => $dossier->revision && auth()->user()->can('valider', $dossier->revision),
                'renvoyer' => $dossier->revision && auth()->user()->can('renvoyer', $dossier->revision),
            ],
        ]);
    }

    public function update(Request $request, Dossier $dossier)
    {
        $revision = $dossier->revision;
        if (!$revision) {
            $revision = Revision::create([
                'dossier_id'  => $dossier->id,
                'reviseur_id' => auth()->id(),
                'statut'      => \App\Enums\StatutRevision::EnCours,
            ]);
        }

        $this->authorize('update', $revision);

        $points = $request->validate([
            'points'   => ['required', 'array'],
            'points.*' => ['required', 'array'],
            'points.*.etat'        => ['nullable', 'string', 'in:conforme,non_conforme,na'],
            'points.*.commentaire' => ['nullable', 'string', 'max:500'],
        ])['points'];

        foreach ($points as $pointId => $data) {
            if (($data['etat'] ?? null) === null) continue;
            RevisionPoint::updateOrCreate(
                ['revision_id' => $revision->id, 'point_id' => $pointId],
                ['etat' => $data['etat'], 'commentaire' => $data['commentaire'] ?? null]
            );
        }

        $revision->update(['statut' => \App\Enums\StatutRevision::EnCours]);

        return back()->with('success', 'Grille de révision sauvegardée.');
    }

    public function valider(Dossier $dossier)
    {
        $revision = $dossier->revision;
        abort_unless($revision, 404);
        $this->authorize('valider', $revision);

        $revision->valider(auth()->user());
        $this->stepService->avancer($dossier, auth()->user());

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', 'Révision validée — dossier transmis pour signature.');
    }

    public function renvoyer(Request $request, Dossier $dossier)
    {
        $revision = $dossier->revision;
        abort_unless($revision, 404);
        $this->authorize('renvoyer', $revision);

        $motif = $request->validate(['motif' => ['nullable', 'string', 'max:500']])['motif'] ?? null;
        $revision->renvoyer(auth()->user(), $motif);
        $this->stepService->reculer($dossier, auth()->user(), $motif);

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', 'Dossier renvoyé en édition.');
    }
}
