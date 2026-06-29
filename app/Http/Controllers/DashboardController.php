<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\JournalActivite;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $enCours            = Dossier::enCours()->count();
        $enRevision         = Dossier::enRevision()->count();
        $echeancesProches   = Dossier::echeanceUrgente()->count();
        $formalitesUrgentes = Formalite::urgentes()->count();

        $fileAttente = Dossier::with(['typeActe', 'redacteur', 'notaire'])
            ->enCours()
            ->orderByRaw('echeance IS NULL, echeance ASC')
            ->limit(10)
            ->get()
            ->map(fn ($d) => [
                'reference'    => $d->reference,
                'objet'        => $d->objet,
                'etape'        => ['value' => $d->etape->value, 'label' => $d->etape->label()],
                'etapeOrdre'   => $d->etape->ordre(),
                'typeActe'     => $d->typeActe?->label,
                'redacteur'    => $d->redacteur?->initiales,
                'notaire'      => $d->notaire?->initiales,
                'echeance'     => $d->echeance?->format('d/m/Y'),
                'echeanceDiff' => $d->echeance?->diffForHumans(),
                'estEnRetard'  => $d->estEnRetard(),
            ]);

        $alertesUrgentes = Dossier::with('typeActe')
            ->echeanceUrgente()
            ->orderBy('echeance')
            ->limit(5)
            ->get()
            ->map(fn ($d) => [
                'reference'    => $d->reference,
                'objet'        => $d->objet,
                'echeance'     => $d->echeance?->format('d/m/Y'),
                'echeanceDiff' => $d->echeance?->diffForHumans(),
                'typeActe'     => $d->typeActe?->label,
                'estEnRetard'  => $d->estEnRetard(),
            ]);

        $activiteRecente = JournalActivite::with(['dossier', 'user'])
            ->orderByDesc('created_at')
            ->limit(15)
            ->get()
            ->map(fn ($j) => [
                'action'     => $j->action,
                'type'       => $j->type,
                'dossier'    => $j->dossier?->reference,
                'user'       => $j->user?->initiales ?? '?',
                'userName'   => $j->user?->name ?? '',
                'created_at' => $j->created_at->diffForHumans(),
            ]);

        $parCategorie = Dossier::join('types_actes', 'dossiers.type_acte_id', '=', 'types_actes.id')
            ->selectRaw('types_actes.categorie, count(*) as total')
            ->whereNull('dossiers.deleted_at')
            ->groupBy('types_actes.categorie')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'categorie' => $row->categorie,
                'label'     => CategorieActe::tryFrom($row->categorie)?->label() ?? ucfirst($row->categorie),
                'total'     => (int) $row->total,
            ]);

        $parEtape = Dossier::selectRaw('etape, count(*) as n')
            ->whereNotNull('etape')
            ->groupBy('etape')
            ->get()
            ->map(fn ($d) => [
                'etape' => $d->getRawOriginal('etape'),
                'label' => $d->etape?->label() ?? $d->getRawOriginal('etape'),
                'ordre' => $d->etape?->ordre() ?? 0,
                'count' => (int) $d->n,
            ])
            ->sortBy('ordre')
            ->values();

        return Inertia::render('Dashboard', [
            'stats' => [
                'enCours'            => $enCours,
                'enRevision'         => $enRevision,
                'echeancesProches'   => $echeancesProches,
                'formalitesUrgentes' => $formalitesUrgentes,
                'total'              => Dossier::count(),
                'ceMois'             => Dossier::whereMonth('created_at', now()->month)
                                            ->whereYear('created_at', now()->year)->count(),
                'clos'               => Dossier::where('etape', EtapeDossier::Cloture)->count(),
                'courriersEnAttente' => Courrier::brouillon()->count(),
            ],
            'fileAttente'     => $fileAttente,
            'alertesUrgentes' => $alertesUrgentes,
            'activiteRecente' => $activiteRecente,
            'parCategorie'    => $parCategorie,
            'parEtape'        => $parEtape,
        ]);
    }
}
