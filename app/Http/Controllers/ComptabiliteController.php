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
        // 2026-11-25 : les deux autres questions que pose l'étude.
        'soldes'         => 'Soldés',
        'proformas'      => 'Proforma en attente',
    ];

    /** Le prédicat de chaque situation — un seul endroit, pour la liste comme pour l'export. */
    private static function situation(?string $filtre, array $l): bool
    {
        return match ($filtre) {
            'impayes'      => $l['resteDu'] > 0,
            'debours'      => $l['deboursAVerser'] > 0,
            'avances'      => $l['avance'] > 0,
            'non_factures' => $l['versesNonFactures'] > 0,
            'soldes'       => $l['totalFacture'] > 0 && $l['resteDu'] <= 0,
            'proformas'    => $l['enProforma'] > 0,
            default        => true,
        };
    }

    /**
     * Les critères reçus, validés : un type d'acte ou une étape inconnus sont ignorés plutôt que
     * de vider la liste sans explication.
     *
     * @return array{filtre: ?string, type: ?string, etape: ?string, du: ?string, au: ?string, q: ?string}
     */
    private function criteres(Request $request): array
    {
        $date = fn (?string $v) => $v && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : null;

        return [
            'filtre' => array_key_exists((string) $request->input('filtre'), self::FILTRES) ? $request->input('filtre') : null,
            'type'   => $request->filled('type') ? (string) $request->input('type') : null,
            'etape'  => \App\Enums\EtapeDossier::tryFrom((string) $request->input('etape'))?->value,
            'du'     => $date($request->input('du')),
            'au'     => $date($request->input('au')),
            'q'      => $request->filled('q') ? trim((string) $request->input('q')) : null,
        ];
    }

    private function autoriser(Request $request): void
    {
        abort_unless($request->user()?->hasAnyRole(RoleUtilisateur::peuventGererFacturation()), 403);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function lignes(Request $request): Collection
    {
        $c = $this->criteres($request);

        // Les critères qui portent sur le dossier se posent en SQL ; la situation financière,
        // calculée, se filtre ensuite.
        return Dossier::visiblePar($request->user())
            ->where(fn ($q) => $q->has('factures')->orHas('decaissements'))
            ->when($c['type'], fn ($q, $code) => $q->whereHas('typeActe', fn ($t) => $t->where('code', $code)))
            ->when($c['etape'], fn ($q, $etape) => $q->where('etape', $etape))
            ->when($c['du'], fn ($q, $du) => $q->whereDate('created_at', '>=', $du))
            ->when($c['au'], fn ($q, $au) => $q->whereDate('created_at', '<=', $au))
            ->when($c['q'], fn ($q, $texte) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$texte}%")->orWhere('objet', 'like', "%{$texte}%")))
            ->with(['typeActe', ...SituationFinanciere::RELATIONS])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Dossier $d) => [
                'reference' => $d->reference,
                'objet'     => $d->objet,
                'typeActe'  => $d->typeActe?->code,
                'etape'     => $d->etape?->label(),
                'creeLe'    => $d->created_at?->format('d/m/Y'),
                ...SituationFinanciere::pour($d),
            ])
            ->filter(fn (array $l) => self::situation($c['filtre'], $l))
            ->values();
    }

    /** Les choix proposés : seulement ce qui existe parmi les dossiers comptables visibles. */
    private function options(Request $request): array
    {
        $dossiers = Dossier::visiblePar($request->user())
            ->where(fn ($q) => $q->has('factures')->orHas('decaissements'))
            ->with('typeActe:id,code,label')
            ->get(['id', 'type_acte_id', 'etape']);

        return [
            'types' => $dossiers->pluck('typeActe')->filter()->unique('code')->sortBy('label')
                ->map(fn ($t) => ['valeur' => $t->code, 'label' => $t->label])->values(),
            'etapes' => collect(\App\Enums\EtapeDossier::cases())
                ->filter(fn ($e) => $dossiers->contains(fn ($d) => $d->etape === $e))
                ->map(fn ($e) => ['valeur' => $e->value, 'label' => $e->label()])->values(),
        ];
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
            'filtre'  => $this->criteres($request)['filtre'],
            // Critères combinables (2026-11-25), repris tels quels par l'export.
            'criteres' => $this->criteres($request),
            'options'  => $this->options($request),
        ]);
    }

    /**
     * Export pour Excel : séparateur « ; » et BOM UTF-8, sans quoi Excel en français coupe les
     * colonnes à la virgule et lit les accents de travers. Montants en nombres nus, pour calculer.
     */
    public function export(Request $request)
    {
        $this->autoriser($request);

        $entetes = ['Dossier', 'Objet', 'Type', 'Étape', 'Ouvert le', 'Honoraires facturés', 'Débours facturés', 'Encaissé',
            'Reste dû', 'Versé aux organismes', 'Débours à verser', 'Provision détenue', "Avance de l'étude", 'En proforma'];

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, "\xEF\xBB\xBF");
        fputcsv($flux, $entetes, ';');
        foreach ($this->lignes($request) as $l) {
            fputcsv($flux, [
                $l['reference'], $l['objet'], $l['typeActe'], $l['etape'], $l['creeLe'],
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
