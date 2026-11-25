<?php

namespace App\Http\Controllers;

use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Services\SituationFinanciere;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * La comptabilité **des dossiers**, vue d'ensemble (2026-11-23) — pas une comptabilité générale,
 * que l'étude ne demande pas.
 *
 * Une ligne par dossier ayant une facture ou un versement : honoraires et débours facturés,
 * encaissé, reste dû, versé aux organismes, débours restant à verser, provision détenue ou avance
 * consentie. Les chiffres viennent de `SituationFinanciere`, comme sur la fiche du dossier.
 *
 * Réservé à ceux qui gèrent la facturation (comptable, notaire, administrateur).
 */
class ComptabiliteController extends Controller
{
    /** Filtres proposés : ce que l'étude doit surveiller. Fermés, déclarés ici seulement. */
    private const FILTRES = [
        'impayes'        => 'Reste dû par le client',
        'debours'        => 'Débours restant à verser',
        'avances'        => "Avance de l'étude",
        'non_factures'   => 'Versements non refacturés',
    ];

    private function autoriser(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(RoleUtilisateur::peuventGererFacturation()), 403);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function lignes(Request $request): Collection
    {
        return Dossier::visiblePar($request->user())
            ->where(fn ($q) => $q->has('factures')->orHas('decaissements'))
            ->with(['typeActe', ...SituationFinanciere::RELATIONS])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Dossier $d) => [
                'reference' => $d->reference,
                'objet'     => $d->objet,
                'typeActe'  => $d->typeActe?->code,
                'etape'     => $d->etape?->label(),
                ...SituationFinanciere::pour($d),
            ])
            ->filter(fn (array $l) => match ($request->input('filtre')) {
                'impayes'      => $l['resteDu'] > 0,
                'debours'      => $l['deboursAVerser'] > 0,
                'avances'      => $l['avance'] > 0,
                'non_factures' => $l['versesNonFactures'] > 0,
                default        => true,
            })
            ->values();
    }

    public function index(Request $request)
    {
        $this->autoriser($request);

        $lignes = $this->lignes($request);
        $cles = ['honorairesFactures', 'deboursFactures', 'encaisse', 'resteDu', 'deboursVerses', 'deboursAVerser', 'provision', 'avance', 'enProforma'];

        return Inertia::render('Comptabilite/Index', [
            'lignes'  => $lignes,
            'totaux'  => collect($cles)->mapWithKeys(fn ($c) => [$c => round($lignes->sum($c), 2)]),
            'filtres' => collect(self::FILTRES)->map(fn ($label, $valeur) => ['valeur' => $valeur, 'label' => $label])->values(),
            'filtre'  => $request->input('filtre'),
        ]);
    }

    /**
     * Export pour Excel : séparateur « ; » et BOM UTF-8, sans quoi Excel en français coupe les
     * colonnes à la virgule et lit les accents de travers. Montants en nombres nus, pour calculer.
     */
    public function export(Request $request)
    {
        $this->autoriser($request);

        $entetes = ['Dossier', 'Objet', 'Type', 'Étape', 'Honoraires facturés', 'Débours facturés', 'Encaissé',
            'Reste dû', 'Versé aux organismes', 'Débours à verser', 'Provision détenue', "Avance de l'étude", 'En proforma'];

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, $entetes, ';');
        foreach ($this->lignes($request) as $l) {
            fputcsv($flux, [
                $l['reference'], $l['objet'], $l['typeActe'], $l['etape'],
                $l['honorairesFactures'], $l['deboursFactures'], $l['encaisse'], $l['resteDu'],
                $l['deboursVerses'], $l['deboursAVerser'], $l['provision'], $l['avance'], $l['enProforma'],
            ], ';');
        }
        rewind($flux);
        $csv = stream_get_contents($flux);
        fclose($flux);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="comptabilite-dossiers-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
