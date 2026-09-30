<?php

namespace App\Http\Controllers;

use App\Enums\StatutSociete;
use App\Models\JournalActivite;
use App\Models\Partie;
use App\Models\Societe;
use App\Support\Normalisation;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Registre des sociétés — recherche, création et correction d'une fiche sans quitter
 * l'écran d'où on la consulte.
 *
 * Calqué sur {@see ClientController}, qui résout le même problème pour les personnes :
 * mêmes conventions de réponse (JSON brut, pas de page Inertia), même factorisation des
 * règles entre `store()` et `update()`, même absence de `destroy()`.
 */
class SocieteController extends Controller
{
    /**
     * Registre des sociétés — la page où l'étude voit ses liquidations en cours.
     *
     * Sans elle, le cycle de vie n'existerait que dans la base : la commande d'alerte
     * enverrait des courriels vers une page inexistante, et rien ne permettrait de constater
     * une radiation. Une alerte sans bouton est une alerte qu'on apprend à ignorer.
     *
     * Volontairement une **liste**, pas un module : pas de page de détail, pas d'édition
     * complète, pas d'historique de fiche. La correction d'une fiche se fait déjà depuis
     * l'assistant de dossier ({@see update()}).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Societe::class);

        $societes = Societe::query()
            ->withCount('dossiers')
            // `dossiers` en entier : `immatriculeeSansRccm()` a besoin de leur étape.
            ->with('dossier:id,reference', 'dossiers:id,societe_id,etape')
            ->when($request->q, fn ($query, $terme) => $query->recherche($terme))
            ->when($request->statut, fn ($query, $statut) => $query->where('statut', $statut))
            ->orderBy('denomination')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Societes/Index', [
            'societes' => $societes->through(fn (Societe $s) => [
                'id'                => $s->id,
                'denomination'      => $s->denomination,
                'sigle'             => $s->sigle,
                'forme'             => $s->forme,
                'forme_label'       => $s->formeLabel(),
                'rccm_numero'       => $s->rccm_numero,
                'statut'            => $s->statut->value,
                'statut_label'      => $s->statut->label(),
                'dissolution_at'    => $s->dissolution_at?->format('d/m/Y'),
                'cloture_at'        => $s->cloture_liquidation_at?->format('d/m/Y'),
                'radiation_at'      => $s->radiation_at?->format('d/m/Y'),
                'liquidateur'       => $s->liquidateurActuel(),
                'dossiers_count'    => $s->dossiers_count,
                'dossier_origine'   => $s->dossier?->reference,
                // Un seul calcul d'échéance dans tout le projet, partagé avec la commande
                // d'alerte — voir Societe::echeancesLiquidation().
                'echeance'          => $this->presenterEcheance($s->prochaineEcheanceLiquidation()),
                // Seule une liquidation clôturée peut être radiée : le bouton n'apparaît que
                // là, et la route le revérifie — l'interface n'est pas un contrôle.
                'peut_etre_radiee'  => $s->statut === StatutSociete::LiquidationCloturee,
                // Les données perdues avant la capture au retour de formalité : l'étude les
                // corrige en une saisie, et le mécanisme empêche que cela se reproduise.
                'manque_rccm'       => $s->immatriculeeSansRccm(),
            ]),
            'statuts' => StatutSociete::toutes(),
            'filters' => [
                'q'      => $request->q ?? '',
                'statut' => $request->statut ?? '',
            ],
            'stats' => [
                'total'         => Societe::count(),
                'enLiquidation' => Societe::statut([StatutSociete::EnLiquidation])->count(),
                'aRadier'       => Societe::statut([StatutSociete::LiquidationCloturee])->count(),
                'manqueRccm'    => Societe::with('dossiers:id,societe_id,etape')->get()
                    ->filter(fn (Societe $s) => $s->immatriculeeSansRccm())
                    ->count(),
            ],
        ]);
    }

    /** @param array|null $echeance */
    private function presenterEcheance(?array $echeance): ?array
    {
        if (! $echeance) {
            return null;
        }

        return [
            ...$echeance,
            'echeance' => $echeance['echeance']->format('d/m/Y'),
        ];
    }

    /**
     * Constate la radiation d'une société au RCCM — **action humaine explicite**.
     *
     * Les trois autres transitions sont automatiques, posées à l'entrée en Expédition du
     * dossier correspondant. Celle-ci ne peut pas l'être : la radiation n'est prouvée que par
     * la pièce du greffe, et aucune étape de dossier ne la constate — le dossier de clôture
     * est terminé bien avant que le greffe ne réponde. La rattacher à une formalité
     * supposerait de déclarer *laquelle* vaut radiation, donc d'inventer.
     */
    public function radier(Request $request, Societe $societe)
    {
        $this->authorize('update', $societe);

        if ($societe->statut !== StatutSociete::LiquidationCloturee) {
            return back()->with('error', sprintf(
                'La société « %s » est %s : seule une liquidation clôturée peut être radiée.',
                $societe->denomination,
                mb_strtolower($societe->statut->label()),
            ));
        }

        $data = $request->validate([
            // Colonne castée : ISO, jamais du français. Le contrat est en tête de
            // resources/js/lib/dates.js, et poster du JJ/MM/AAAA vers une colonne castée est
            // le défaut que ce projet a déjà commis cinq fois.
            'radiation_at' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'radiation_at.before_or_equal' => 'La radiation ne peut pas être constatée dans le futur.',
        ]);

        $societe->update([
            'statut'       => StatutSociete::Radiee,
            'radiation_at' => $data['radiation_at'],
        ]);

        JournalActivite::enregistrer(
            $societe->dossier,
            sprintf(
                'Fiche société « %s » : radiation au RCCM constatée au %s',
                $societe->denomination,
                $societe->radiation_at->format('d/m/Y'),
            ),
            'societe',
            ['statut' => ['avant' => StatutSociete::LiquidationCloturee->value, 'apres' => StatutSociete::Radiee->value]],
            $request->user(),
        );

        return back()->with('success', "Radiation de « {$societe->denomination} » enregistrée.");
    }

    /**
     * Recherche de sociétés pour l'auto-complétion (sélecteur de société de l'assistant de
     * dossier). Réponse JSON brute, pas une page Inertia.
     */
    public function autocomplete(Request $request)
    {
        $this->authorize('viewAny', Societe::class);

        $q = trim((string) $request->get('q', ''));

        // **Aucun filtre de statut** : la clôture d'une liquidation doit pouvoir désigner une
        // société en liquidation, et masquer une fiche radiée priverait le clerc de la seule
        // explication utile. Le statut voyage dans la réponse et s'affiche en badge — « un
        // blocage énuméré plutôt qu'un bouton grisé ».
        $query = Societe::query()->with('dossier:id,reference');

        if (mb_strlen($q) < 2) {
            // Aucune recherche saisie : proposer les fiches les plus récentes plutôt qu'une
            // liste vide, pour permettre de parcourir le registre sans connaître déjà le nom
            // recherché (même parti pris que ClientController::autocomplete).
            $societes = $query->orderByDesc('created_at')->limit(20)->get();
        } else {
            $societes = $query->recherche($q)->orderBy('denomination')->limit(15)->get();
        }

        return response()->json(
            $societes->map(fn (Societe $societe) => $this->presenter($societe))
        );
    }

    /**
     * Fiche complète d'une société, avec les personnes connues de son dossier de
     * constitution.
     *
     * Appelée quand l'utilisateur rattache une société à un dossier de modification : c'est
     * ce qui permet de proposer les associés et gérants déjà fichés comme cédants ou gérant
     * sortant, plutôt que de les faire ressaisir — et donc d'éviter la création de fiches
     * clients concurrentes pour les mêmes personnes (décision #33).
     */
    public function show(Societe $societe)
    {
        $this->authorize('view', $societe);

        return response()->json([
            ...$this->presenter($societe),
            'personnesConnues' => $societe->personnesConnuesPourEcran(),
        ]);
    }

    /**
     * Règles partagées par store() et update() — la fiche société est le même objet qu'on
     * la crée depuis l'assistant de dossier ou qu'on la corrige ensuite.
     */
    /**
     * Convertit en ISO la date reçue au format français, **avant** validation — voir
     * `Normalisation::datesEnISO()` pour le pourquoi, et `ClientController::normaliserDates()`
     * pour le pendant sur la fiche client.
     *
     * La modale envoie désormais de l'ISO, mais un import referait l'inversion en silence.
     */
    private function normaliserDates(Request $request): Request
    {
        $converties = Normalisation::datesEnISO($request->all(), ['date_constitution']);

        if ($converties !== []) {
            $request->merge($converties);
        }

        return $request;
    }

    private function regles(): array
    {
        return [
            'denomination'             => ['required', 'string', 'max:200'],
            'forme'                    => ['nullable', 'string', 'max:50'],
            'sigle'                    => ['nullable', 'string', 'max:50'],
            'rccm_numero'              => ['nullable', 'string', 'max:100'],
            'nif'                      => ['nullable', 'string', 'max:100'],
            'capital_chiffres'         => ['nullable', 'numeric', 'min:0'],
            'nombre_parts'             => ['nullable', 'integer', 'min:0'],
            'valeur_nominale_chiffres' => ['nullable', 'numeric', 'min:0'],
            'siege_quartier'           => ['nullable', 'string', 'max:100'],
            'siege_commune'            => ['nullable', 'string', 'max:100'],
            'siege_ville'              => ['nullable', 'string', 'max:100'],
            'email_societe'            => ['nullable', 'email', 'max:150'],
            // Même format guinéen que la fiche client — une seule convention pour tout le
            // répertoire, sinon un numéro accepté ici serait refusé là.
            'telephone_societe'        => ['nullable', 'string', 'max:25', 'regex:/^(?:\+?224|00224)?[\s.-]*6\d{2}(?:[\s.-]*\d{2}){3}$/'],
            'objet_social'             => ['nullable', 'string'],
            'duree'                    => ['nullable', 'integer', 'min:0'],
            // Une société ne peut pas avoir été constituée dans le futur, ni avant l'indépendance
            // guinéenne — au-delà, c'est une faute de frappe sur l'année.
            'date_constitution'        => ['nullable', 'date', 'before_or_equal:today', 'after:1958-01-01'],
            'notaire_origine'          => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * Messages là où la tournure par défaut se lit mal — `before_or_equal:today` interpole le
     * littéral « today ».
     */
    private function messagesValidation(): array
    {
        return [
            'date_constitution.before_or_equal' => 'La date de constitution ne peut pas être dans le futur.',
            'date_constitution.after'           => 'La date de constitution semble erronée (avant 1958).',
            'telephone_societe.regex'           => 'Numéro guinéen attendu — par exemple 622 78 37 32.',
        ];
    }

    /**
     * Création d'une fiche depuis l'assistant, pour une société **absente du registre** —
     * l'étude traite aussi des modifications de sociétés qu'elle n'a pas constituées.
     * Réponse JSON (la fiche créée) pour rattachement immédiat, sans quitter l'assistant.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Societe::class);

        $societe = Societe::create($this->normaliserDates($request)->validate($this->regles(), $this->messagesValidation()));

        return response()->json($this->presenter($societe), 201);
    }

    /**
     * Correction d'une fiche société.
     *
     * Contrairement à {@see ClientController::update()}, aucune reprojection n'est
     * déclenchée : corriger la fiche ne doit pas réécrire rétroactivement les dossiers
     * passés. La projection `soc.*` d'un dossier est figée au moment où la société y est
     * rattachée, et n'évolue ensuite que par une **modification statutaire** — laquelle est
     * précisément l'objet d'un dossier, avec son PV, sa certification et ses formalités.
     * Réaligner en silence les actes d'un dossier en cours sur une correction de saisie
     * reviendrait à contourner ce circuit.
     */
    public function update(Request $request, Societe $societe)
    {
        $this->authorize('update', $societe);

        $societe->update($this->normaliserDates($request)->validate($this->regles(), $this->messagesValidation()));

        return response()->json($this->presenter($societe->fresh()));
    }

    /**
     * Forme envoyée au frontend : les colonnes, plus les libellés dérivés dont le
     * sélecteur a besoin pour afficher une carte de synthèse sans redemander le serveur.
     */
    private function presenter(Societe $societe): array
    {
        $societe->loadMissing('piecesConstitutives.versionActuelle');

        return [
            ...$societe->toArray(),
            'nom_complet'      => $societe->nomComplet(),
            'forme_label'      => $societe->formeLabel(),
            'dossier_origine'  => $societe->dossier?->only(['id', 'reference']),
            // Dirigeant en exercice, calculé : préremplit `soc.gerant_actuel` au rattachement plutôt
            // que de le laisser saisir alors qu'il est connu du registre.
            'gerant_actuel'    => $societe->gerantActuel(),
            // Cycle de vie de la fiche. `statut_label` et `statut_explication` accompagnent la
            // valeur pour que le sélecteur affiche son badge sans redéclarer l'enum en JavaScript.
            'statut'             => $societe->statut->value,
            'statut_label'       => $societe->statut->label(),
            'statut_couleur'     => $societe->statut->couleur(),
            'statut_explication' => $societe->statut->explication(),
            'liquidateur_actuel' => $societe->liquidateurActuel(),
            // Dossier constitutif : seules les sociétés que l'étude n'a pas constituées doivent le
            // fournir — pour les autres, le dossier d'origine fait foi et la checklist ne s'affiche
            // pas du tout.
            'exige_pieces'     => $societe->exigePiecesConstitutives(),
            'pieces'           => $societe->exigePiecesConstitutives()
                ? $societe->piecesConstitutivesChecklist()
                : [],
            'pieces_manquantes' => $societe->exigePiecesConstitutives()
                ? $societe->piecesConstitutivesManquantes()
                : [],
        ];
    }
}
