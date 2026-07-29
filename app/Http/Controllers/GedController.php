<?php

namespace App\Http\Controllers;

use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\Partie;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Vue transversale du module GED unifié : liste/recherche tous les DocumentFichier
 * (actes de dossier, pièces de formalité, pièces de partie) en un seul endroit, quel
 * que soit leur documentable réel — jusqu'ici seule une vue par dossier existait
 * (DocumentsTab), sans page centrale pour parcourir l'ensemble des documents.
 *
 * Groupé par dossier plutôt qu'en liste chronologique plate (retour utilisateur du
 * 2026-07-24) : un notaire pense « dossier d'abord », pas « fil de documents » — le
 * reste de l'appli est déjà organisé ainsi (Dossiers/Show, Formalités, Facturation…).
 */
class GedController extends Controller
{
    private const PAR_PAGE = 15;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user       = auth()->user();
        $dossierIds = Dossier::visiblePar($user)->pluck('id');

        $documents = DocumentFichier::query()
            ->with(['versionActuelle'])
            ->with(['documentable' => fn ($morphTo) => $morphTo->morphWith([
                Formalite::class => ['dossier'],
                Partie::class    => ['dossier'],
            ])])
            ->where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('documentable_type', Dossier::class)->whereIn('documentable_id', $dossierIds))
                ->orWhere(fn ($qq) => $qq->where('documentable_type', Formalite::class)->whereIn('documentable_id', Formalite::whereIn('dossier_id', $dossierIds)->pluck('id')))
                ->orWhere(fn ($qq) => $qq->where('documentable_type', Partie::class)->whereIn('documentable_id', Partie::whereIn('dossier_id', $dossierIds)->pluck('id'))))
            ->when($request->q, fn ($q, $s) => $q->where('nom', 'like', "%{$s}%"))
            ->when($request->type, fn ($q, $t) => $q->where('documentable_type', match ($t) {
                'acte'            => Dossier::class,
                'piece_formalite' => Formalite::class,
                'piece_partie'    => Partie::class,
                default           => $t,
            }))
            ->when($request->categorie, fn ($q, $c) => $q->where('categorie', $c))
            ->orderByDesc('updated_at')
            ->get();

        // Regroupement par dossier gouvernant (et non par documentable_id brut : un acte,
        // une pièce de formalité et une pièce de partie du même dossier doivent finir dans
        // le même groupe même si leur documentable_type diffère).
        $parDossierId = $documents->groupBy(fn (DocumentFichier $doc) => $doc->dossierGouvernant()?->id)
            ->filter(fn ($group, $key) => $key !== null);

        $dossiers = Dossier::whereIn('id', $parDossierId->keys())->get()->keyBy('id');

        $groupes = $parDossierId
            ->map(function (Collection $docs, $dossierId) use ($dossiers) {
                $dossier = $dossiers->get($dossierId);
                return [
                    'reference'    => $dossier->reference,
                    'objet'        => $dossier->objet,
                    'url_dossier'  => route('dossiers.show', $dossier->reference),
                    'derniereMaj'  => $docs->max('updated_at'),
                    'documents'    => $docs->map(fn (DocumentFichier $doc) => $this->documentToArray($doc))->values(),
                ];
            })
            ->sortByDesc('derniereMaj')
            ->values();

        $page    = (int) $request->get('page', 1);
        $total   = $groupes->count();
        $tranche = $groupes->slice(($page - 1) * self::PAR_PAGE, self::PAR_PAGE)->values();

        $paginator = new LengthAwarePaginator(
            $tranche, $total, self::PAR_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $stats = [
            'actes'           => DocumentFichier::where('documentable_type', Dossier::class)->whereIn('documentable_id', $dossierIds)->count(),
            'piecesFormalite' => DocumentFichier::where('documentable_type', Formalite::class)->whereIn('documentable_id', Formalite::whereIn('dossier_id', $dossierIds)->pluck('id'))->count(),
            'piecesPartie'    => DocumentFichier::where('documentable_type', Partie::class)->whereIn('documentable_id', Partie::whereIn('dossier_id', $dossierIds)->pluck('id'))->count(),
        ];
        $stats['total']    = $stats['actes'] + $stats['piecesFormalite'] + $stats['piecesPartie'];
        $stats['dossiers'] = $total;

        return Inertia::render('Ged/Index', [
            'groupes' => $paginator,
            'stats'   => $stats,
            'filters' => [
                'q'         => $request->q         ?? '',
                'type'      => $request->type       ?? '',
                'categorie' => $request->categorie  ?? '',
            ],
        ]);
    }

    private function documentToArray(DocumentFichier $doc): array
    {
        return [
            'id'             => $doc->id,
            'nom'            => $doc->nom,
            'categorie'      => $doc->categorie,
            'statut'         => $doc->statut,
            'est_fourni'     => (bool) $doc->est_fourni,
            'has_file'       => (bool) $doc->versionActuelle,
            'chemin_fichier' => $doc->versionActuelle?->chemin_fichier,
            'version'        => $doc->versionActuelle?->numero,
            'type'           => match (true) {
                $doc->documentable instanceof Dossier   => 'acte',
                $doc->documentable instanceof Formalite => 'piece_formalite',
                $doc->documentable instanceof Partie    => 'piece_partie',
                default                                  => 'autre',
            },
            'contexte'       => match (true) {
                $doc->documentable instanceof Formalite => $doc->documentable->labelAffiche(),
                $doc->documentable instanceof Partie    => $doc->documentable->nom,
                default                                  => null,
            },
            'url_download'   => route('documents.download', $doc),
            'url_preview'    => route('documents.preview', $doc),
            'url_versions'   => route('documents.versions', $doc),
            'updated_at'     => $doc->updated_at?->format('d/m/Y H:i'),
        ];
    }
}
