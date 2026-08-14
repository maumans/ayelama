<?php

namespace App\Http\Controllers;

use App\Enums\RubriqueCloture;
use App\Models\Dossier;
use App\Services\InventaireClotureService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * Vue transversale de la GED : toutes les pièces de tous les dossiers visibles.
 *
 * Groupée par dossier plutôt qu'en liste chronologique plate (retour utilisateur du
 * 2026-07-24) : un notaire pense « dossier d'abord », pas « fil de documents ».
 *
 * Depuis le 2026-08-04, chaque dossier est en outre **rangé par rubrique** (actes,
 * accord client, pièces des parties, pièces des formalités, courriers, facturation),
 * exactement comme l'onglet Clôture — les deux écrans partagent
 * InventaireClotureService pour ne pas pouvoir diverger sur le rangement. Effet de
 * bord bénéfique : les courriers et les reçus entrent enfin dans la GED, alors qu'ils
 * en étaient absents bien qu'étant des pièces du dossier (ils vivent dans leurs
 * propres tables, héritage antérieur au module GED unifié).
 */
class GedController extends Controller
{
    private const PAR_PAGE = 15;

    public function __construct(private InventaireClotureService $inventaire) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $dossiers = Dossier::visiblePar(auth()->user())
            ->where('etape', \App\Enums\EtapeDossier::Cloture->value)
            ->with([
                'typeActe', // Ajouté pour l'arborescence
                'documents.versionActuelle',
                'documents.documentable',
                'parties.pieces.versionActuelle',
                'formalites.pieces.versionActuelle',
                'courriers',
                'factures.paiements.recu',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $recherche = trim((string) $request->q);
        $rubriqueFiltre = $request->rubrique;
        
        // Nouveaux filtres pour l'arborescence
        $anneeFiltre = $request->annee;
        $categorieFiltre = $request->categorie;
        $typeActeFiltre = $request->type_acte;
        $dossierFiltre = $request->dossier; // reference du dossier

        // On construit tous les groupes pour pouvoir générer l'arborescence complète
        $tousLesGroupes = $dossiers
            ->map(function (Dossier $dossier) use ($recherche, $rubriqueFiltre) {
                $rubriques = $this->inventaire->pour($dossier)
                    ->when($rubriqueFiltre, fn (Collection $r) => $r->where('rubrique', $rubriqueFiltre))
                    ->map(fn (array $rubrique) => [
                        ...$rubrique,
                        'pieces' => $recherche === ''
                            ? $rubrique['pieces']
                            : array_values(array_filter(
                                $rubrique['pieces'],
                                fn (array $p) => stripos($p['nom'], $recherche) !== false
                                    || stripos((string) $p['origine'], $recherche) !== false,
                            )),
                    ])
                    ->filter(fn (array $rubrique) => count($rubrique['pieces']) > 0)
                    ->values();

                return [
                    'reference'   => $dossier->reference,
                    'objet'       => $dossier->objet,
                    'etape'       => $dossier->etape?->value,
                    'etapeLabel'  => $dossier->etape?->label(),
                    'url_dossier' => route('dossiers.show', $dossier->reference),
                    'derniereMaj' => $dossier->updated_at?->format('d/m/Y H:i'),
                    'nbPieces'    => $rubriques->sum(fn (array $r) => count($r['pieces'])),
                    'rubriques'   => $rubriques->all(),
                    // Métadonnées pour l'arborescence
                    'annee'       => $dossier->created_at?->format('Y') ?? 'Inconnu',
                    'categorie'   => $dossier->typeActe?->categorie?->label() ?? 'Autre',
                    'type_acte'   => $dossier->typeActe?->label ?? 'Autre',
                ];
            })
            ->filter(fn (array $g) => $g['nbPieces'] > 0)
            ->values();

        // Extraire l'arborescence légère (sans les pièces) pour le panneau latéral
        $arborescence = [];
        foreach ($tousLesGroupes as $g) {
            $an = $g['annee'];
            $cat = $g['categorie'];
            $typ = $g['type_acte'];
            
            if (!isset($arborescence[$an])) $arborescence[$an] = [];
            if (!isset($arborescence[$an][$cat])) $arborescence[$an][$cat] = [];
            if (!isset($arborescence[$an][$cat][$typ])) $arborescence[$an][$cat][$typ] = [];
            
            $arborescence[$an][$cat][$typ][] = [
                'reference' => $g['reference'],
                'objet'     => $g['objet'],
                'nbPieces'  => $g['nbPieces'],
            ];
        }

        // Filtrer les groupes pour l'affichage principal
        $groupesFiltres = $tousLesGroupes->filter(function ($g) use ($anneeFiltre, $categorieFiltre, $typeActeFiltre, $dossierFiltre) {
            if ($anneeFiltre && $g['annee'] !== $anneeFiltre) return false;
            if ($categorieFiltre && $g['categorie'] !== $categorieFiltre) return false;
            if ($typeActeFiltre && $g['type_acte'] !== $typeActeFiltre) return false;
            if ($dossierFiltre && $g['reference'] !== $dossierFiltre) return false;
            return true;
        })->values();

        $page    = max(1, (int) $request->get('page', 1));
        $tranche = $groupesFiltres->slice(($page - 1) * self::PAR_PAGE, self::PAR_PAGE)->values();

        $paginator = new LengthAwarePaginator(
            $tranche, $groupesFiltres->count(), self::PAR_PAGE, $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render('Ged/Index', [
            'groupes' => $paginator,
            'arborescence' => $arborescence,
            'stats'   => $this->stats($groupesFiltres), // Les stats correspondent à ce qui est affiché
            'rubriques' => collect(RubriqueCloture::ordonnees())
                ->map(fn (RubriqueCloture $r) => ['value' => $r->value, 'label' => $r->label()])
                ->values(),
            'filters' => [
                'q'        => $request->q        ?? '',
                'rubrique' => $request->rubrique ?? '',
                'annee'    => $anneeFiltre       ?? '',
                'categorie'=> $categorieFiltre   ?? '',
                'type_acte'=> $typeActeFiltre    ?? '',
                'dossier'  => $dossierFiltre     ?? '',
            ],
        ]);
    }

    /**
     * Compteurs par rubrique, calculés sur les groupes déjà filtrés — l'utilisateur
     * doit voir les chiffres de ce qu'il regarde, pas des totaux absolus qui ne
     * correspondraient pas à la liste affichée.
     *
     * @param  Collection<int, array>  $groupes
     */
    private function stats(Collection $groupes): array
    {
        $parRubrique = collect(RubriqueCloture::ordonnees())
            ->mapWithKeys(fn (RubriqueCloture $r) => [
                $r->value => $groupes->sum(fn (array $g) => collect($g['rubriques'])
                    ->where('rubrique', $r->value)
                    ->sum(fn (array $rub) => count($rub['pieces']))),
            ]);

        return [
            'dossiers'    => $groupes->count(),
            'total'       => $groupes->sum('nbPieces'),
            'parRubrique' => $parRubrique->all(),
        ];
    }
}
