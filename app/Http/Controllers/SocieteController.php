<?php

namespace App\Http\Controllers;

use App\Enums\StatutSociete;
use App\Models\JournalActivite;
use App\Models\Partie;
use App\Models\Societe;
use App\Models\SocieteHistorique;
use App\Services\EtatSocieteService;
use App\Services\SocieteMutationService;
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
    /**
     * Les lacunes qu'on peut isoler depuis l'en-tête, et la façon de les requêter.
     *
     * ⚠️ **Une alerte doit mener quelque part.** L'en-tête annonçait « 5 immatriculées sans
     * RCCM » en texte simple : elle comptait un problème sans offrir de le voir, ni donc de le
     * corriger. Même travers que le blocage de clôture qui réclamait un dossier déjà ouvert
     * (décision 75) — un chiffre sans sortie se contemple.
     *
     * `rccm` passe par le scope du modèle, miroir requêtable du prédicat d'affichage
     * ({@see Societe::manqueRccm()}) ; les trois autres sont de simples absences de colonne.
     */
    private const LACUNES = ['rccm', 'nif', 'date_constitution', 'sans_dossier'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Societe::class);

        $lacune = in_array((string) $request->string('manque'), self::LACUNES, true)
            ? (string) $request->string('manque')
            : null;

        $societes = Societe::query()
            ->withCount('dossiers')
            // `dossiers` en entier : `immatriculeeSansRccm()` a besoin de leur étape.
            ->with('dossier:id,reference', 'dossiers:id,societe_id,etape')
            ->when($request->q, fn ($query, $terme) => $query->recherche($terme))
            ->when($request->statut, fn ($query, $statut) => $query->where('statut', $statut))
            ->when($lacune, fn ($query, $quoi) => match ($quoi) {
                'rccm'              => $query->manqueRccm(),
                'nif'               => $query->where(fn ($q) => $q->whereNull('nif')->orWhere('nif', '')),
                'date_constitution' => $query->whereNull('date_constitution'),
                'sans_dossier'      => $query->whereNull('dossier_id'),
            })
            ->orderBy('denomination')
            ->paginate(25)
            ->withQueryString();

        $lignes = $societes->through(fn (Societe $s) => [
            'id'                => $s->id,
            'denomination'      => $s->denomination,
            'sigle'             => $s->sigle,
            'forme'             => $s->forme,
            'forme_label'       => $s->formeLabel(),
            'rccm_numero'       => $s->rccm_numero,
            'nif'               => $s->nif,
            'date_constitution' => $s->date_constitution?->format('d/m/Y'),
            // Toujours renseignés (mesuré : 15 fiches sur 15), et absents du tableau jusqu'ici
            // pendant que deux colonnes de liquidation y restaient vides 14 fois sur 15.
            'siege'             => $s->siegeComplet(),
            'capital_chiffres'  => $s->capital_chiffres,
            'email_societe'     => $s->email_societe,
            'telephone_societe' => $s->telephone_societe,
            'statut'            => $s->statut->value,
            'statut_label'      => $s->statut->label(),
            'dissolution_at'    => $s->dissolution_at?->format('d/m/Y'),
            'cloture_at'        => $s->cloture_liquidation_at?->format('d/m/Y'),
            'radiation_at'      => $s->radiation_at?->format('d/m/Y'),
            'liquidateur'       => $s->liquidateurActuel(),
            'dossiers_count'    => $s->dossiers_count,
            'dossier_origine'   => $s->dossier?->reference,
            // Badge « externe » (§ 7.1) : constituée ailleurs, donc son état vient de pièces
            // fournies et non d'un dossier de l'étude. Dérivé de `dossier_id`, comme le filtre
            // « sans dossier constitutif » ci-dessus — une seule vérité pour les deux.
            'externe'           => $s->estExterne(),
            // Un seul calcul d'échéance dans tout le projet, partagé avec la commande
            // d'alerte — voir Societe::echeancesLiquidation().
            'echeance'          => $this->presenterEcheance($s->prochaineEcheanceLiquidation()),
            // Seule une liquidation clôturée peut être radiée : le bouton n'apparaît que
            // là, et la route le revérifie — l'interface n'est pas un contrôle.
            'peut_etre_radiee'  => $s->statut === StatutSociete::LiquidationCloturee,
            // Les données perdues avant la capture au retour de formalité : l'étude les
            // corrige en une saisie, et le mécanisme empêche que cela se reproduise.
            'manque_rccm'       => $s->immatriculeeSansRccm(),
        ]);

        return Inertia::render('Societes/Index', [
            'societes' => $lignes,
            'statuts'  => StatutSociete::toutes(),
            'filters'  => [
                'q'      => $request->q ?? '',
                'statut' => $request->statut ?? '',
                'manque' => $lacune ?? '',
            ],
            // ⚠️ **Les colonnes de liquidation s'affichent quand la page a de quoi les remplir**,
            // et non selon le filtre choisi. Mesuré : « Liquidateur » et « Prochaine échéance »
            // étaient vides sur 14 fiches sur 15 — deux colonnes sur cinq qui ne disaient rien
            // et repoussaient hors de vue ce qui est toujours renseigné. Les masquer sur un
            // critère de filtre aurait caché l'unique société en liquidation dans la vue par
            // défaut ; les lier aux données ne cache jamais rien.
            'afficherLiquidation' => collect($lignes->items())
                ->contains(fn (array $l) => $l['liquidateur'] !== null || $l['echeance'] !== null),
            'stats' => [
                'total'         => Societe::count(),
                'enLiquidation' => Societe::statut([StatutSociete::EnLiquidation])->count(),
                'aRadier'       => Societe::statut([StatutSociete::LiquidationCloturee])->count(),
                // Un `count()` SQL, et non plus `->get()->filter()` sur tout le registre à
                // chaque affichage : le scope dit la même chose que le prédicat, et un test
                // l'affirme fiche par fiche.
                'manqueRccm'    => Societe::manqueRccm()->count(),
                'manqueNif'     => Societe::where(fn ($q) => $q->whereNull('nif')->orWhere('nif', ''))->count(),
                'manqueDate'    => Societe::whereNull('date_constitution')->count(),
                'sansDossier'   => Societe::whereNull('dossier_id')->count(),
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

        $etats = app(EtatSocieteService::class);
        $avant = $etats->courant($societe);

        $societe->update([
            'statut'       => StatutSociete::Radiee,
            'radiation_at' => $data['radiation_at'],
        ]);

        // La radiation clôt le cycle de vie : elle a sa place dans l'historique de la société,
        // qui accepte un dossier nul — précisément pour ce cas.
        SocieteHistorique::create([
            'societe_id'    => $societe->id,
            'dossier_id'    => null,
            'operations'    => ['radiation'],
            'date_effet'    => $societe->radiation_at->toDateString(),
            'etat_avant'    => $avant,
            'etat_apres'    => $etats->courant($societe->fresh()),
            'appliquee_le'  => now(),
            'appliquee_par' => $request->user()?->id,
        ]);

        // ⚠️ **Le journal d'activité est attaché à un dossier, et exige un `Dossier` non nul.**
        // `$societe->dossier` est nul pour toute fiche entrée au registre à la main — c'est-à-dire
        // précisément les sociétés constituées ailleurs, dont la radiation est le cas courant.
        // Le `TypeError` était latent (zéro fiche concernée au 2026-10-26) et serait apparu avec
        // la première société externe. L'historique ci-dessus porte la trace dans tous les cas ;
        // le journal ne la double que lorsqu'il y a un dossier où la lire.
        if ($societe->dossier) {
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
        }

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
    /**
     * La fiche d'une société — écran E2 de l'addendum v1.2.
     *
     * ⚠️ Distincte de {@see show()}, qui rend du **JSON** au sélecteur de l'assistant. Les deux
     * servent la même société et ne peuvent pas fusionner : l'un alimente une carte de synthèse
     * en axios, l'autre une page Inertia. Leur donner la même route aurait obligé l'un des deux
     * à deviner ce que l'autre attend.
     *
     * L'état courant vient de {@see EtatSocieteService}, le même calcul que la projection et que
     * l'application — pour que la fiche ne puisse pas afficher autre chose que ce que le
     * registre enregistre.
     */
    public function fiche(Societe $societe)
    {
        $this->authorize('view', $societe);

        $societe->loadMissing(['associes.client', 'gerants.client', 'historique.auteur', 'historique.dossier', 'dossier']);

        return Inertia::render('Societes/Fiche', [
            'societe' => [
                ...$this->presenter($societe),
                'etat'             => app(EtatSocieteService::class)->courant($societe),
                'parts_reparties'  => $societe->partsReparties(),
                'echeance'         => $this->presenterEcheance($societe->prochaineEcheanceLiquidation()),
                'peut_etre_radiee' => $societe->statut === StatutSociete::LiquidationCloturee,
                'manque_rccm'      => $societe->immatriculeeSansRccm(),
                'dossiers_count'   => $societe->dossiers()->count(),
            ],
            'associes' => $societe->associes->map(fn ($a) => [
                'id'          => $a->id,
                'nom'         => $a->nom,
                'client_id'   => $a->client_id,
                'nb_parts'    => $a->nb_parts,
                'pourcentage' => $a->pourcentage((int) $societe->nombre_parts),
                'date_entree' => $a->date_entree?->format('d/m/Y'),
                'date_sortie' => $a->date_sortie?->format('d/m/Y'),
                'actuel'      => $a->estActuel(),
            ])->values(),
            'gerants' => $societe->gerants->map(fn ($g) => [
                'id'              => $g->id,
                'nom'             => $g->nom,
                'client_id'       => $g->client_id,
                'statutaire'      => $g->statutaire,
                'date_nomination' => $g->date_nomination?->format('d/m/Y'),
                'date_fin'        => $g->date_fin?->format('d/m/Y'),
                'motif_fin'       => $g->motifFinLabel(),
                'en_fonction'     => $g->estEnFonction(),
            ])->values(),
            'historique' => $societe->historique->map(fn ($h) => [
                'id'          => $h->id,
                'operations'  => $h->operations,
                'date_effet'  => $h->date_effet?->format('d/m/Y'),
                'dossier'     => $h->dossier?->reference,
                'auteur'      => $h->auteur?->name,
                'differences' => $h->differences(),
                'associes'    => $h->etat_apres['associes'] ?? [],
            ])->values(),
            // Libellés des champs pour le diff — « capital_chiffres » ne se lit pas.
            'libelles' => [
                'denomination'     => 'Dénomination',
                'sigle'            => 'Sigle',
                'forme'            => 'Forme juridique',
                'siege'            => 'Siège social',
                'capital_chiffres' => 'Capital',
                'nombre_parts'     => 'Nombre de parts',
                'valeur_nominale'  => 'Valeur nominale',
                'objet_social'     => 'Objet social',
                'duree'            => 'Durée',
                'rccm_numero'      => 'RCCM',
                'nif'              => 'NIF',
                'statut'           => 'Statut',
            ],
        ]);
    }

    public function show(Societe $societe)
    {
        $this->authorize('view', $societe);

        return response()->json([
            ...$this->presenter($societe),
            'personnesConnues' => $societe->personnesConnuesPourEcran(),
            // ⚠️ **La composition n'arrivait jamais au formulaire.** `personnesConnues` porte
            // les noms, les rôles et les fiches, mais **pas les parts** : le clerc devait donc
            // retaper la répartition que le registre connaissait déjà, puis recalculer les
            // pourcentages de tête.
            //
            // `courant()` rend exactement ce que l'écran demande — associés avec `nb_parts` et
            // `pourcentage`, capital, nombre de parts, valeur nominale. Son docblock nomme
            // d'ailleurs « le tableau *Nouvelle répartition* de l'écran » comme premier
            // consommateur ; il n'avait simplement jamais été branché.
            'etat' => app(EtatSocieteService::class)->courant($societe),
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

        // Cas T5 de l'addendum : « l'historique commence à l'état initialisé ». Sans cette
        // ligne, la timeline d'une société externe débuterait à sa première modification,
        // comme si l'état d'où elle part n'existait pas — et le premier diff n'aurait rien à
        // quoi se comparer. Même écriture que `ayelema:backfill-associes`, par le même service.
        app(SocieteMutationService::class)->poserEtatInitial(
            $societe,
            app(EtatSocieteService::class),
            null,
            auth()->id(),
        );

        return response()->json($this->presenter($societe->fresh()), 201);
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
            // Badge « externe » du § 7.1 : une société constituée ailleurs se signale, parce
            // que son état vient de pièces fournies et non d'un dossier de l'étude.
            'externe'          => $societe->estExterne(),
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
