<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\FormeSociete;
use App\Enums\RoleUtilisateur;
use App\Enums\TypeModificationStatutaire;
use App\Http\Requests\StoreDossierRequest;
use App\Http\Requests\UpdateDossierRequest;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
use App\Models\ModeleCourrier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Setting;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Notifications\DossierAssigneNotification;
use App\Services\DossierStepService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DossierController extends Controller
{
    public function __construct(
        private DossierStepService $stepService,
        private NotificationService $notifications,
        private \App\Services\ClientProjectionService $projection,
        private \App\Services\InventaireClotureService $inventaire,
    ) {}

    /**
     * Colonne d'assignation → rôle notifié. Le rédacteur est exclu : c'est
     * toujours l'auteur du dossier, il n'a pas à être averti de sa propre action.
     */
    private const ROLES_ASSIGNABLES = [
        'reviseur_id'   => RoleUtilisateur::Reviseur,
        'notaire_id'    => RoleUtilisateur::Notaire,
        'formaliste_id' => RoleUtilisateur::Formaliste,
    ];

    /**
     * Notifie les utilisateurs nouvellement assignés au dossier.
     *
     * @param  array<string, int|null>  $avant  Valeurs des colonnes avant la
     *                                          modification (vide à la création).
     */
    private function notifierAssignations(Dossier $dossier, array $avant = []): void
    {
        foreach (self::ROLES_ASSIGNABLES as $colonne => $role) {
            $nouvelId = $dossier->{$colonne};

            if (!$nouvelId || $nouvelId === ($avant[$colonne] ?? null)) {
                continue;
            }

            $assigne = User::find($nouvelId);
            if (!$assigne) {
                continue;
            }

            // sauf: l'acteur — s'assigner soi-même ne doit pas s'auto-notifier.
            $this->notifications->envoyer(
                [$assigne],
                new DossierAssigneNotification($dossier, $role),
                auth()->user(),
            );
        }
    }

    /**
     * Le dossier est-il constitué ? Miroir de
     * DossierStepService::erreursDeConstitution() — objet, notaire, certificateur, pièces
     * des personnes fournies, accord client signé.
     *
     * Duplication assumée entre le serveur (qui décide) et cet indicateur (qui pilote le
     * bouton « Avancer » de la liste) : c'est le point faible connu, documenté au devbook
     * §9. Toute règle modifiée dans le service doit l'être ici.
     */
    private function constitutionComplete(Dossier $dossier): bool
    {
        $dossier->loadMissing('documents', 'parties.pieces.versionActuelle');

        if (empty(trim($dossier->objet ?? '')) || !$dossier->notaire_id || !$dossier->reviseur_id) {
            return false;
        }

        $piecesManquantes = $dossier->parties->contains(
            fn ($p) => collect($p->piecesChecklist())->contains(fn ($item) => !$item['est_fourni'])
        );
        if ($piecesManquantes) {
            return false;
        }

        return (bool) $dossier->documents->firstWhere('categorie', 'accord_client')?->est_signe_cachete;
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user  = auth()->user();
        $today = now()->toDateString();

        $query = Dossier::visiblePar($user)
            ->with(['typeActe', 'redacteur', 'notaire', 'revision:id,dossier_id,statut'])
            ->withCount([
                'documents',
                // Passe par le scope plutôt qu'une comparaison en dur : l'état
                // terminal d'une formalité est défini une seule fois, dans
                // StatutFormalite::estTerminee().
                'formalites as formalites_non_clos' => fn ($q) => $q->nonTerminees(),
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

        // L'état « prêt à clôturer » exige l'inventaire complet du dossier (actes,
        // pièces de parties et de formalités, courriers, reçus) : impossible à obtenir
        // en withCount sans une pile de sous-requêtes polymorphes. Calculé uniquement
        // pour les dossiers de la page effectivement en Expédition — en pratique une
        // poignée — plutôt que de laisser le bouton « Avancer » actif à tort, ce qui
        // recréerait la divergence serveur/liste déjà corrigée.
        $pretsACloturer = $dossiers->getCollection()
            ->filter(fn (Dossier $d) => $d->etape === EtapeDossier::Expedition)
            ->mapWithKeys(fn (Dossier $d) => [$d->id => $this->inventaire->estCompletementVerifie($d)]);

        // Même approche pour l'Initialisation : « toutes les pièces des personnes fournies »
        // et « accord client signé » supposent de parcourir des relations polymorphes,
        // impossible en withCount. Limité aux dossiers de la page à cette étape.
        $pretsAEditer = $dossiers->getCollection()
            ->filter(fn (Dossier $d) => $d->etape === EtapeDossier::Initialisation)
            ->mapWithKeys(fn (Dossier $d) => [$d->id => $this->constitutionComplete($d)]);

        return Inertia::render('Dossiers/Index', [
            'dossiers'   => $dossiers->through(fn ($d) => [
                ...$this->dossierToArray($d),
                'etapeOrdre'  => $d->etapeOrdre(),
                'canAvancer'  => $user->can('avancer', $d),
                // Miroir de DossierStepService::verifierPrerequis() : il pilote l'état
                // du bouton « Avancer » de la liste. `match` exhaustif, sans `default`
                // — c'est un `default => true` qui laissait le bouton actif à l'étape
                // Expédition alors que le serveur y exige des documents signés.
                // Toute étape ajoutée à EtapeDossier devra être décidée ici aussi.
                'peutAvancer' => match ($d->etape) {
                    // Le dossier doit être constitué : objet, intervenants, pièces des
                    // personnes et accord signé du client.
                    EtapeDossier::Initialisation => $pretsAEditer[$d->id] ?? false,
                    // Les actes existent forcément à ce stade (générés à l'entrée en
                    // Édition) ; le double contrôle de constitution reste appliqué côté
                    // serveur pour les dossiers antérieurs au 2026-08-04.
                    EtapeDossier::Edition    => ($pretsAEditer[$d->id] ?? $this->constitutionComplete($d)) && $d->documents_count > 0,
                    EtapeDossier::Revision   => $d->revisionValidee(),
                    EtapeDossier::Signature  => (bool) ($d->date_signature_client && $d->date_signature_notaire),
                    EtapeDossier::Formalites => $d->formalites_non_clos === 0,
                    // Toutes les pièces de l'inventaire doivent avoir été vérifiées
                    // dans l'onglet Clôture — la configuration « documents obligatoires
                    // par type d'acte » a été supprimée le 2026-08-04.
                    EtapeDossier::Expedition => $pretsACloturer[$d->id] ?? false,
                    // Étape terminale : le bouton n'est de toute façon pas rendu
                    // (voir Dossiers/Index.jsx), mais false est la réponse juste.
                    EtapeDossier::Cloture    => false,
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
            'brouillons' => auth()->user()->can('create', Dossier::class)
                ? DossierBrouillonController::pourUtilisateur(auth()->id())
                : [],
        ]);
    }

    public function create()
    {
        $this->authorize('create', Dossier::class);

        return Inertia::render('Dossiers/Create', [
            // Plus de liste d'actes ici : elle venait de `TypeActe::modeles()`, donc de l'ancienne
            // colonne `type_acte_id`, et annonçait autre chose que ce qui serait généré. L'assistant
            // interroge désormais `/types-actes/{id}/actes-prevus`, qui tient compte des variantes
            // décidées — impossible à connaître au moment de charger cette page.
            'typesActes' => TypeActe::actif()->get()->groupBy(fn ($t) => $t->categorie->value)->map(fn ($group) => $group->map(fn ($t) => [
                'id'          => $t->id,
                'code'        => $t->code,
                'label'       => $t->label,
                'categorie'   => $t->categorie->value,
                'delai_jours' => $t->delai_jours,
                'description' => $t->description,
            ])),
            'notaires'   => User::withRole('notaire')->where('actif', true)->get(['id', 'name', 'initiales']),
            'reviseurs'  => User::withRole('reviseur')->where('actif', true)->get(['id', 'name', 'initiales']),
            'formalistes' => User::withRole('formaliste')->where('actif', true)->get(['id', 'name', 'initiales']),
            'defauts'    => Setting::defaultAssignees(),
            'piecesRequises' => Partie::piecesRequisesParCle(),
            // Saisies inachevées de l'utilisateur, proposées à la reprise en tête
            // de l'assistant (voir DossierBrouillonController).
            'brouillons' => DossierBrouillonController::pourUtilisateur(auth()->id()),
            // Règles légales par type d'acte de société (CR juillet 2026) : capital
            // minimum, associé unique, commissaire aux comptes, responsabilité. Exposées
            // pour **guider** la saisie — sans quoi elles ne se découvriraient qu'au moment
            // du blocage. Dérivées de l'enum, jamais dupliquées en JavaScript.
            'reglesParTypeActe' => TypeActe::actif()->get()
                ->mapWithKeys(fn (TypeActe $t) => [
                    $t->code => \App\Enums\FormeSociete::depuisCodeTypeActe($t->code)?->reglesApplicables(),
                ])
                ->filter()
                ->all(),
            // Règles 8 à 11 : impact statutaire, documents produits et droits d'enregistrement
            // par type de modification. Calculés depuis le 2026-08-05 mais jamais restitués —
            // on ne découvrait les actes et les coûts qu'après la création du dossier. Dérivés
            // de l'enum, jamais dupliqués en JavaScript.
            'typesModification' => TypeModificationStatutaire::toutes(),
        ]);
    }

    public function store(
        StoreDossierRequest $request,
        \App\Services\ActesGeneratorService $generatorService,
        \App\Services\FacturationService $facturationService,
        \App\Services\FormaliteGenerationService $formaliteGenerationService
    ) {
        $dossier = $this->creerDossier(
            $request->validated(),
            $generatorService,
            $facturationService,
            $formaliteGenerationService
        );

        // Le dossier naît en Initialisation : on atterrit sur l'onglet Informations, où se
        // trouvent l'accord client à déposer, les pièces des personnes et le questionnaire
        // — c'est le travail de cette étape. `focus=pieces` fait en plus défiler jusqu'à
        // la checklist quand il y a des pièces à fournir (voir
        // DossierStepService::verifierInitialisation(), qui bloque l'avancement sans elles).
        // Dérivé de la checklist elle-même plutôt que d'une liste de rôles recopiée ici :
        // les modifications statutaires en ont ajouté quatre (cédant, cessionnaire,
        // souscripteur, gérant entrant), et une liste dupliquée aurait laissé leurs dossiers
        // atterrir sans invitation à compléter les pièces.
        $aDesPiecesAFournir = $dossier->parties
            ->contains(fn (\App\Models\Partie $partie) => $partie->piecesRequisesDefinition() !== []);

        return redirect()->route('dossiers.show', array_filter([
            'dossier' => $dossier->reference,
            'tab'     => 'informations',
            'focus'   => $aDesPiecesAFournir ? 'pieces' : null,
        ]))->with('success', "Dossier {$dossier->reference} créé — déposez l'accord du client et complétez les pièces pour passer à l'édition des actes.");
    }

    /**
     * Création d'un dossier à partir d'un tableau de données déjà validées
     * (même forme que StoreDossierRequest::rules()) — factorisé pour être
     * réutilisé par DemandeController::convertir() (conversion d'une demande
     * externe en dossier), sans dupliquer la transaction.
     */
    public function creerDossier(
        array $data,
        \App\Services\ActesGeneratorService $generatorService,
        \App\Services\FacturationService $facturationService,
        \App\Services\FormaliteGenerationService $formaliteGenerationService
    ): Dossier {
        $typeActe = TypeActe::findOrFail($data['type_acte_id']);

        // Brouillon dont ce dossier est l'aboutissement : il détient les pièces déjà
        // téléversées pendant la saisie. Chargé hors transaction, supprimé après
        // succès seulement — un rollback doit laisser le brouillon intact pour que
        // l'utilisateur puisse réessayer sans avoir tout perdu.
        $brouillon = null;
        if (!empty($data['brouillon_id'])) {
            $brouillon = \App\Models\DossierBrouillon::find($data['brouillon_id']);
        }

        $dossier = DB::transaction(function () use ($data, $typeActe, $brouillon, $generatorService, $facturationService, $formaliteGenerationService) {
            $reference = $this->genererReference($typeActe);

            $dossier = Dossier::create([
                'reference'     => $reference,
                'type_acte_id'  => $typeActe->id,
                'societe_id'    => $data['societe_id'] ?? null,
                'etape'         => EtapeDossier::Initialisation,
                'redacteur_id'  => auth()->id(),
                'reviseur_id'   => $data['reviseur_id'] ?? null,
                'notaire_id'    => $data['notaire_id'],
                'formaliste_id' => $data['formaliste_id'] ?? null,
                'objet'         => $data['objet'],
                'valeur'        => $data['valeur'] ?? null,
                'echeance'      => $data['echeance'] ?? null,
                'urgent'        => $data['urgent'] ?? false,
                'notes'         => $data['notes'] ?? null,
            ]);

            Questionnaire::create([
                'dossier_id' => $dossier->id,
                'donnees'    => $data['donnees'] ?? [],
            ]);

            foreach ($data['parties'] ?? [] as $partieData) {
                $pieces = $partieData['pieces'] ?? [];
                // Pièces héritées d'un brouillon : déjà sur le disque privé, on les
                // rejoue par le même chemin qu'un téléversement direct (nommage,
                // hash et versionnage identiques) — voir DossierBrouillon.
                foreach ($partieData['pieces_brouillon'] ?? [] as $categorie => $chemin) {
                    if (isset($pieces[$categorie])) {
                        continue; // Un fichier fraîchement choisi prime sur le brouillon.
                    }
                    $fichier = $brouillon?->fichierPourPiece($chemin);
                    if ($fichier) {
                        $pieces[$categorie] = $fichier;
                    }
                }
                unset($partieData['pieces'], $partieData['pieces_brouillon']);

                $partie = Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));

                foreach ($pieces as $categorie => $fichier) {
                    $requis = $partie->piecesRequisesDefinition();
                    $piece = $partie->pieces()->create([
                        'nom' => $requis[$categorie] ?? 'Pièce',
                        'categorie' => $categorie,
                        'est_requis' => true
                    ]);
                    $piece->nouvelleVersion($fichier, 'parties/' . $dossier->reference);
                }
            }

            // Avant toute génération : réaligner le questionnaire sur les fiches
            // clients rattachées. Le frontend n'envoie plus l'identité des personnes
            // liées, seulement le lien (client_id + emplacement dans le schéma) —
            // c'est ici que les balises ${pp.*}/${ger.*} attendues par les modèles
            // Word sont effectivement peuplées. Projeter après aurait obligé à
            // régénérer immédiatement tous les documents.
            $this->projection->reprojeter($dossier->load(['questionnaire', 'parties.client']));
            $dossier->load('questionnaire');

            // Une constitution de société fait naître une fiche au registre : c'est elle qui
            // permettra, plus tard, d'ouvrir un dossier de modification sur cette société sans
            // ressaisir sa dénomination, sa forme, son capital ni son siège. Une modification
            // ou une dissolution, à l'inverse, arrive avec sa `societe_id` déjà choisie.
            $this->enregistrerAuRegistreDesSocietes($dossier, $typeActe);

            // Les actes ne sont PAS générés ici : ils le sont au passage Initialisation →
            // Édition (ActesGeneratorService::genererActesDepuisModeles(), appelé par
            // DossierStepService::avancer()), donc sur un questionnaire que le client a
            // validé par son accord signé. Les générer dès la création revenait à produire
            // des actes sur des données non validées, à régénérer à la moindre correction.

            // Génération automatique de la facture (note de frais)
            // Le service déduit l'assiette du questionnaire (capital, prix, loyers…)
            // et applique les barèmes configurés en base
            $dossier->load('questionnaire');
            $facturationService->genererFacture($dossier);

            // Génération automatique des formalités à partir des barèmes marqués
            // "génère une formalité" pour ce type d'acte (table baremes)
            $formaliteGenerationService->genererFormalites($dossier);

            JournalActivite::enregistrer($dossier, 'Dossier créé', 'creation', [
                'type_acte' => $typeActe->label,
            ]);

            return $dossier;
        });

        // Le brouillon a rempli son office : ses fichiers sont désormais rattachés
        // aux parties. Supprimé avec son répertoire pour ne pas laisser d'orphelins.
        $brouillon?->supprimerAvecFichiers();

        // Hors transaction : une notification ne doit jamais partir pour un
        // dossier dont la création aurait été annulée par un rollback.
        $this->notifierAssignations($dossier);

        return $dossier;
    }

    /**
     * Actes prévus par la configuration mais absents du dossier.
     *
     * Comparaison par `nom`, la même que la génération utilise pour ne pas écraser un acte déjà
     * produit : les deux doivent voir le même ensemble, sinon l'encart annoncerait un acte que le
     * bouton ne produirait pas.
     *
     * Seuls les actes **ayant un gabarit** y figurent : un rôle attendu sans gabarit relève de la
     * configuration, pas du dossier, et la vue Processus le signale déjà.
     *
     * @return array<int, array{nom: string, type_document: string}>
     */
    private function actesManquants(Dossier $dossier, \App\Services\ActesGeneratorService $generateur): array
    {
        if (! auth()->user()?->can('genererDocuments', $dossier)) {
            return [];
        }

        $dossier->loadMissing('documents', 'questionnaire', 'typeActe');
        $presents = $dossier->documents->pluck('nom')->all();

        $variantes = TypeModificationStatutaire::depuisLibelles(
            $dossier->questionnaire?->donnees['modif.types']
                ?? $dossier->questionnaire?->donnees['modif.type']
                ?? null,
        );

        return collect($generateur->actesPrevus(
            $dossier->typeActe,
            array_map(fn (TypeModificationStatutaire $t) => $t->value, $variantes),
        ))
            ->filter(fn (array $a) => $a['a_gabarit'] && ! in_array($a['nom'], $presents, true))
            ->map(fn (array $a) => ['nom' => $a['nom'], 'type_document' => $a['type_document']])
            ->values()
            ->all();
    }

    /**
     * Crée la fiche du registre d'une société qui vient d'être constituée.
     *
     * Ne fait rien si le dossier n'est pas une constitution de société (une modification ou
     * une dissolution porte sur une société **préexistante**, dont la fiche est choisie dans
     * l'assistant), si le dossier est déjà rattaché, ou si la dénomination est absente : une
     * fiche sans nom ne serait retrouvable par personne.
     *
     * Une dénomination déjà au registre est **réutilisée** plutôt que dupliquée. Cela ne
     * contredit pas la règle 4 (« la dénomination doit être unique ») : celle-ci est vérifiée
     * de façon bloquante par ReglesSocieteService avant l'avancement du dossier, et le
     * registre ne doit pas, lui, se remplir de doublons entre-temps.
     */
    private function enregistrerAuRegistreDesSocietes(Dossier $dossier, TypeActe $typeActe): void
    {
        if ($typeActe->categorie !== CategorieActe::Societe) {
            return;
        }

        // Fiche déjà rattachée — cas d'une modification ou d'une dissolution. Rien à créer, mais
        // le questionnaire peut porter des informations que la fiche n'avait pas : les champs
        // `soc.*` non renseignés au registre restent saisissables dans l'assistant précisément
        // pour cela (`MICH SARL` y était sans numéro RCCM, pourtant obligatoire à l'acte). Sans
        // cette reprise, la saisie ne vivrait que dans ce dossier et le suivant la redemanderait.
        if ($dossier->societe_id !== null) {
            $this->completerFicheSociete($dossier);

            return;
        }

        $forme        = FormeSociete::depuisCodeTypeActe($typeActe->code);
        $donnees      = $dossier->questionnaire?->donnees ?? [];
        $denomination = $donnees['soc.denomination'] ?? null;

        if (blank($denomination)) {
            return;
        }

        // Rapprochement en PHP et non en SQL : la normalisation (casse, espaces multiples)
        // doit être identique à celle de la règle 4, qui vit dans Societe. Volume attendu de
        // quelques centaines de fiches — même arbitrage que
        // ReglesSocieteService::verifierDenominationUnique().
        $cible     = Societe::normaliserDenomination($denomination);
        $existante = Societe::all()->first(
            fn (Societe $s) => Societe::normaliserDenomination($s->denomination) === $cible
        );

        if ($existante) {
            $dossier->update(['societe_id' => $existante->id]);

            return;
        }

        // `dossier_id` n'est posé que par une **constitution** : c'est le dossier qui a créé la
        // société. Une modification ou une dissolution sur une société hors registre crée bien sa
        // fiche — sans quoi la saisie ne servirait qu'une fois et le dossier suivant la
        // redemanderait — mais sans s'en déclarer l'origine. C'est aussi ce qui fait que son
        // dossier constitutif reste exigé (`Societe::exigePiecesConstitutives()`).
        $societe = Societe::create([
            ...Societe::depuisQuestionnaire($donnees, $forme?->value),
            'dossier_id' => $forme ? $dossier->id : null,
        ]);
        $dossier->update(['societe_id' => $societe->id]);
    }

    /**
     * Reporte au registre les informations que la fiche rattachée n'avait pas.
     *
     * Jamais d'écrasement (voir {@see Societe::completerDepuisQuestionnaire()}) : corriger une
     * valeur déjà au registre relève d'une modification statutaire, appliquée par
     * `SocieteMutationService` à l'entrée en Expédition, pas d'un formulaire de création.
     *
     * Journalisé sur le dossier : une fiche de référence qui change sans trace n'est pas
     * acceptable — même exigence que pour la mise à jour d'une fiche client depuis le Répertoire.
     */
    private function completerFicheSociete(Dossier $dossier): void
    {
        $societe = $dossier->societe;
        $donnees = $dossier->questionnaire?->donnees ?? [];

        if (!$societe || $donnees === []) {
            return;
        }

        $ajouts = $societe->completerDepuisQuestionnaire($donnees);

        if ($ajouts === []) {
            return;
        }

        JournalActivite::enregistrer(
            $dossier,
            sprintf(
                'Fiche société « %s » complétée au registre depuis ce dossier : %s',
                $societe->denomination,
                implode(', ', array_keys($ajouts)),
            ),
            'modification',
            ['societe_id' => $societe->id, 'champs_ajoutes' => $ajouts],
        );
    }

    public function edit(Dossier $dossier)
    {
        return redirect()->route('dossiers.show', $dossier->reference);
    }

    public function show(
        Dossier $dossier,
        \App\Services\ReglesSocieteService $reglesSociete,
        \App\Services\ActesGeneratorService $generateur,
    )
    {
        $this->authorize('view', $dossier);

        $dossier->load([
            'typeActe', 'societe.dossier:id,reference', 'societe.piecesConstitutives.versionActuelle',
            'redacteur', 'reviseur', 'notaire', 'formaliste',
            'questionnaire', 'documents.versionActuelle', 'documents.signeCachetePar', 'revision.reviseur', 'revision.points',
            'formalites.pieces.versionActuelle', 'formalites.dependDe', 'formalites.dependants',
            'parties.client', 'parties.pieces.versionActuelle', 'journal.user',
            'factures.lignes', 'factures.paiements.recu', 'factures.paiements.enregistrePar',
            'courriers.redacteur', 'courriers.signeCachetePar',
        ]);

        return Inertia::render('Dossiers/Show', [
            'dossier'    => [
                ...$this->dossierDetailToArray($dossier),
                // Actes que la configuration prévoit et qui ne sont pas au dossier. Corriger un
                // rattachement de gabarit ne réveille pas les dossiers existants — c'est voulu, une
                // correction ne doit pas modifier en silence les dossiers d'autres clercs — mais
                // l'écart doit se voir, sinon un acte manquant reste invisible.
                'actesManquants' => $this->actesManquants($dossier, $generateur),
            ],
            'can'        => [
                'update'          => auth()->user()->can('update', $dossier),
                'avancer'         => auth()->user()->can('avancer', $dossier),
                'reviser'         => auth()->user()->can('reviser', $dossier),
                'gererFormalites' => auth()->user()->can('gererFormalites', $dossier),
                'gererFacturation' => auth()->user()->can('gererFacturation', $dossier),
                'genererDocuments' => auth()->user()->can('genererDocuments', $dossier),
                // Dépôt des pièces des personnes : Initialisation comprise, contrairement à
                // `genererDocuments` — c'est l'étape qui les exige pour être franchie.
                'gererPieces'      => auth()->user()->can('gererPieces', $dossier),
                // Étapes différentes de `update` : le questionnaire et les parties ne se
                // modifient qu'en Édition, les signatures qu'à l'étape Signature.
                'modifierQuestionnaire' => auth()->user()->can('modifierQuestionnaire', $dossier),
                'enregistrerSignatures' => auth()->user()->can('enregistrerSignatures', $dossier),
                'cloturerDocuments' => auth()->user()->can('cloturerDocuments', $dossier),
                'genererCourriers' => auth()->user()->can('genererCourriers', $dossier),
                'reassigner'      => auth()->user()->can('reassigner', $dossier),
                'delete'          => auth()->user()->can('delete', $dossier),
                'validerRevision'  => $dossier->revision && auth()->user()->can('valider', $dossier->revision),
                'renvoyerRevision' => $dossier->revision && auth()->user()->can('renvoyer', $dossier->revision),
            ],
            'reviseurs'   => User::withRole('reviseur')->where('actif', true)->get(['id', 'name']),
            'formalistes' => User::withRole('formaliste')->where('actif', true)->get(['id', 'name']),
            'notaires'    => User::withRole('notaire')->where('actif', true)->get(['id', 'name']),
            // Impact statutaire, actes produits et formalités à venir d'un dossier de
            // modification (`null` pour tout autre type d'acte). Calculé depuis le
            // 2026-08-05 mais jamais affiché : le clerc ne pouvait pas savoir, en consultant
            // le dossier, pourquoi tel acte y figure et tel autre non.
            'modificationStatutaire' => $reglesSociete->modificationStatutaire($dossier),
            // Nécessaire ici aussi : la modale d'édition du questionnaire en tire les
            // incompatibilités entre modifications (`incompatiblesAvec`). Sans cette prop, les
            // combinaisons interdites à la création redeviendraient saisissables après coup.
            'typesModification' => TypeModificationStatutaire::toutes(),
            // Fiche du registre sur laquelle porte le dossier — l'état de la société tel que
            // le dossier l'a figé au rattachement.
            'societe' => $dossier->societe ? [
                ...$dossier->societe->only([
                    'id', 'denomination', 'sigle', 'forme', 'rccm_numero', 'nif',
                    'capital_chiffres', 'nombre_parts', 'siege_quartier', 'siege_commune',
                    'siege_ville', 'objet_social', 'derniere_modification_at',
                ]),
                'nom_complet' => $dossier->societe->nomComplet(),
                'forme_label' => $dossier->societe->formeLabel(),
                'dossier_origine' => $dossier->societe->dossier?->only(['id', 'reference']),
                // Une société constituée par l'étude n'a rien à fournir : son dossier d'origine
                // contient déjà statuts, PV, RCCM et DNSV. La checklist ne concerne que les autres.
                'exige_pieces' => $dossier->societe->exigePiecesConstitutives(),
                'pieces' => $dossier->societe->exigePiecesConstitutives()
                    ? $dossier->societe->piecesConstitutivesChecklist()
                    : [],
            ] : null,
        ]);
    }

    public function update(UpdateDossierRequest $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $data = $request->validated();

        if (!auth()->user()->can('reassigner', $dossier)) {
            unset($data['notaire_id'], $data['reviseur_id'], $data['formaliste_id']);
        }

        $assignationsAvant = $dossier->only(array_keys(self::ROLES_ASSIGNABLES));

        $dossier->update($data);

        JournalActivite::enregistrer($dossier, 'Informations du dossier mises à jour', 'modification', []);

        $this->notifierAssignations($dossier, $assignationsAvant);

        return back()->with('success', 'Dossier mis à jour.');
    }

    public function updateQuestionnaire(
        Request $request,
        Dossier $dossier,
        \App\Services\ClientProjectionService $projection,
    ) {
        // `modifierQuestionnaire` et non `update` : cette action régénère les actes, elle
        // ne doit donc pas être possible après la validation de la certification (voir
        // DossierPolicy::modifierQuestionnaire).
        $this->authorize('modifierQuestionnaire', $dossier);

        $validated = $request->validate([
            'donnees'         => ['required', 'array'],
            'managedRoles'    => ['nullable', 'array'],
            'managedRoles.*'  => ['string', 'max:100'],
            ...StoreDossierRequest::partiesRules(),
        ]);

        DB::transaction(function () use ($dossier, $validated) {
            if ($dossier->questionnaire) {
                $dossier->questionnaire->update(['donnees' => $validated['donnees']]);
            } else {
                Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $validated['donnees']]);
            }

            // Correspondance par partie_id (transmis par le frontend, voir attachPartieIds()
            // dans Show.jsx) plutôt que par position : préserve l'id des Partie déjà
            // existantes — donc leurs pièces déjà téléversées, rattachées par documentable_id
            // sans contrainte FK — même en cas de réordonnancement ou de suppression d'un
            // item au milieu de la liste (un simple index-à-index s'y ferait piéger). Un item
            // sans partie_id (nouvellement ajouté dans cette session d'édition) est créé ;
            // toute Partie existante non référencée dans le nouveau payload est supprimée.
            $managedRoles = $validated['managedRoles'] ?? [];
            foreach ($managedRoles as $role) {
                $existantes   = $dossier->parties()->where('role', $role)->get()->keyBy('id');
                $nouvelles    = collect($validated['parties'] ?? [])->where('role', $role)->values();
                $idsConserves = [];

                foreach ($nouvelles as $partieData) {
                    $partieId = $partieData['partie_id'] ?? null;
                    unset($partieData['partie_id']);

                    $pieces = $partieData['pieces'] ?? [];
                    unset($partieData['pieces']);

                    if ($partieId && $existantes->has($partieId)) {
                        $existantes[$partieId]->update($partieData);
                        $idsConserves[] = $partieId;
                    } else {
                        $nouvellePartie = Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));
                        $idsConserves[] = $nouvellePartie->id;
                        
                        foreach ($pieces as $categorie => $fichier) {
                            $requis = $nouvellePartie->piecesRequisesDefinition();
                            $piece = $nouvellePartie->pieces()->create([
                                'nom' => $requis[$categorie] ?? 'Pièce', 
                                'categorie' => $categorie, 
                                'est_requis' => true
                            ]);
                            $piece->nouvelleVersion($fichier, 'parties/' . $dossier->reference);
                        }
                    }
                }
                $existantes->whereNotIn('id', $idsConserves)->each->delete();
            }

            // Les parties viennent d'être resynchronisées : un rôle peut désormais
            // pointer vers une autre fiche client (ou vers une fiche fraîchement
            // corrigée). On réaligne le questionnaire sur ces fiches avant de
            // régénérer les actes, sinon les balises ${pp.*}/${ger.*} garderaient
            // l'identité de l'ancien client.
            $projection->reprojeter($dossier->fresh());
        });

        // Les actes déjà générés référencent l'ancien contenu du questionnaire — on les
        // régénère depuis leur modèle pour qu'ils reflètent les réponses à jour. Les
        // documents verrouillés (signés/cachetés) ou sans modèle actif correspondant sont
        // ignorés silencieusement, comme le fait déjà genererDocuments() pour les cas non
        // applicables.
        $dossier->load(['questionnaire', 'documents']);
        $regeneres = $projection->regenererDocumentsNonVerrouilles($dossier);

        $message = $regeneres > 0
            ? "Questionnaire mis à jour — {$regeneres} document(s) régénéré(s) automatiquement."
            : 'Questionnaire mis à jour.';

        JournalActivite::enregistrer($dossier, $message, 'modification', []);

        return back()->with('success', $message);
    }

    /**
     * Fiche récapitulative du questionnaire — générée à la demande (jamais persistée en
     * GED, toujours à jour), destinée à être imprimée et signée par le client. Ouverte en
     * `inline` : le lecteur PDF natif du navigateur fournit à la fois impression et
     * téléchargement, sans UI supplémentaire à construire.
     */
    public function telechargerFicheRecueil(Dossier $dossier, \App\Services\FicheRecueilPdfService $ficheService)
    {
        $this->authorize('view', $dossier);

        $chemin = $ficheService->genererPdf($dossier);
        $path   = Storage::disk('public')->path($chemin);

        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Fiche-' . $dossier->reference . '.pdf"',
        ]);
    }

    /**
     * Dépôt de la fiche de recueil signée par le client — preuve d'accord bloquante pour
     * quitter l'étape Édition (DossierStepService::verifierEdition()). Contrairement
     * aux actes, ce document n'a pas d'état brouillon : le seul fichier qui existe jamais
     * pour cette catégorie est la preuve signée, verrouillée dès son dépôt (même mécanisme
     * que DocumentController::televerserSigne()). `firstOrCreate` permet de re-téléverser
     * (remplacement en cas d'erreur) sans dupliquer la ligne.
     */
    /**
     * Constate les dates de signature du client et du notaire.
     *
     * Action à part, et non deux champs de la mise à jour générique : une date de
     * signature est un fait daté, pas une propriété modifiable à volonté. Restreinte à
     * l'étape Signature (`DossierPolicy::enregistrerSignatures`) et tracée au journal —
     * auparavant, un formaliste pouvait effacer les dates d'un dossier déjà passé en
     * Formalités sans qu'aucune trace n'en subsiste.
     */
    public function enregistrerSignatures(Request $request, Dossier $dossier)
    {
        $this->authorize('enregistrerSignatures', $dossier);

        $data = $request->validate([
            'date_signature_client'  => ['sometimes', 'nullable', 'date'],
            'date_signature_notaire' => ['sometimes', 'nullable', 'date'],
        ]);

        $dossier->update($data);

        $faits = [];
        if (array_key_exists('date_signature_client', $data)) {
            $faits[] = $data['date_signature_client']
                ? 'signature du client le ' . \Carbon\Carbon::parse($data['date_signature_client'])->format('d/m/Y')
                : 'date de signature du client retirée';
        }
        if (array_key_exists('date_signature_notaire', $data)) {
            $faits[] = $data['date_signature_notaire']
                ? 'signature du notaire le ' . \Carbon\Carbon::parse($data['date_signature_notaire'])->format('d/m/Y')
                : 'date de signature du notaire retirée';
        }

        if ($faits) {
            JournalActivite::enregistrer(
                $dossier,
                'Signatures — ' . implode(', ', $faits),
                'signature',
                $data,
            );
        }

        return back()->with('success', 'Signatures enregistrées.');
    }

    public function televerserAccordClient(Request $request, Dossier $dossier)
    {
        // L'accord porte sur le questionnaire : le remplacer après la certification
        // reviendrait à changer la pièce qui prouve l'accord du client sur un contenu
        // déjà validé.
        $this->authorize('modifierQuestionnaire', $dossier);

        $request->validate(['fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png']]);

        // Le nom dépend du type de dossier — une modification de statuts reçoit la décision
        // d'assemblée des associés, pas une fiche de recueil signée (voir pieceAccordAttendue()).
        $attendu = $dossier->pieceAccordAttendue();

        $document = $dossier->documents()->firstOrCreate(
            ['categorie' => $attendu['categorie']],
            ['nom' => $attendu['nom'], 'statut' => 'edite', 'created_by_id' => auth()->id()]
        );

        $document->nouvelleVersion($request->file('fichier'), 'documents/' . $dossier->reference, ['source' => 'upload']);
        $document->update([
            'est_signe_cachete'    => true,
            'signe_cachete_at'     => now(),
            'signe_cachete_par_id' => auth()->id(),
        ]);

        JournalActivite::enregistrer($dossier, "« {$attendu['nom']} » téléversé", 'etape', []);

        return back()->with('success', "« {$attendu['nom']} » enregistré.");
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

    /**
     * Produit les actes du dossier depuis les gabarits configurés.
     *
     * ⚠️ **Délègue au service, et ne réimplémente rien.** Cette action portait jusqu'au 2026-08-11
     * sa propre copie de la génération, et les deux avaient dérivé : elle interrogeait encore
     * `where('type_acte_id', …)` — donc ignorait les modèles partagés entre types d'actes —,
     * n'appliquait aucun filtre par modification décidée (un transfert de siège pouvait produire un
     * acte de cession) et ne connaissait pas les gabarits hérités. D'où le « Aucun modèle actif »
     * affiché alors que deux actes venaient d'être produits par l'autre chemin.
     */
    public function genererDocuments(
        Dossier $dossier,
        \App\Services\ActesGeneratorService $generatorService
    ) {
        $this->authorize('genererDocuments', $dossier);

        $resultat = $generatorService->produireActes($dossier);

        // Trois situations distinctes, que l'ancien message confondait : rien de configuré est un
        // défaut de paramétrage, « déjà présents » et « n produits » sont des succès.
        if ($resultat['attendus'] === 0) {
            return back()->with(
                'error',
                "Aucun gabarit n'est configuré pour ce processus. Ouvrez Paramètres > Types d'actes pour voir ce qui est attendu et rattacher les modèles manquants.",
            );
        }

        if ($resultat['crees'] === 0) {
            return back()->with('success', 'Tous les actes attendus sont déjà présents au dossier.');
        }

        JournalActivite::enregistrer(
            $dossier,
            "{$resultat['crees']} document(s) généré(s) depuis les modèles",
            'creation',
            [],
        );

        return back()->with('success', "{$resultat['crees']} document(s) généré(s) depuis les modèles.");
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
            'etapeSuivante' => $d->etape->suivante()
                ? ['value' => $d->etape->suivante()->value, 'label' => $d->etape->suivante()->label()]
                : null,
            'typeActe'   => $d->typeActe ? ['label' => $d->typeActe->label, 'categorie' => $d->typeActe->categorie?->value, 'code' => $d->typeActe->code] : null,
            'redacteur'  => $d->redacteur ? ['name' => $d->redacteur->name, 'initiales' => $d->redacteur->initiales] : null,
            'notaire'    => $d->notaire   ? ['name' => $d->notaire->name,   'initiales' => $d->notaire->initiales]   : null,
            'valeur'     => $d->valeur,
            'echeance'   => $d->echeance?->toDateString(),
            'urgent'     => $d->urgent,
            'notes'      => $d->notes,
            'date_signature_client'  => $d->date_signature_client?->toDateString(),
            'date_signature_notaire' => $d->date_signature_notaire?->toDateString(),
            'estEnRetard' => $d->estEnRetard(),
            'updated_at' => $d->updated_at->diffForHumans(),
        ];
    }

    /**
     * Sérialisation partagée d'un DocumentFichier — utilisée pour les documents du
     * dossier et les pièces des parties (les pièces de formalité ont leur propre
     * mapping dans Formalite::versArray(), au même format).
     */
    private function documentFichierToArray(\App\Models\DocumentFichier $doc): array
    {
        return [
            'id'             => $doc->id,
            'nom'            => $doc->nom,
            'categorie'      => $doc->categorie,
            // Libellé servi avec le document : les pages n'ont plus à recopier le vocabulaire, ce
            // qui laissait sortir des slugs bruts (« statuts_maj ») dès qu'un type était ajouté.
            'typeDocLabel'   => $doc->typeDocumentLabel(),
            'statut'         => $doc->statut,
            'est_requis'     => (bool) $doc->est_requis,
            'est_fourni'     => (bool) $doc->est_fourni,
            'chemin_fichier' => $doc->versionActuelle?->chemin_fichier,
            'has_file'       => (bool) $doc->versionActuelle,
            'version'        => $doc->versionActuelle?->numero,
            'est_signe_cachete' => (bool) $doc->est_signe_cachete,
            'signe_cachete_at'  => $doc->signe_cachete_at?->format('d/m/Y H:i'),
            'signe_cachete_par' => $doc->signeCachetePar?->name,
            'url_download'      => route('documents.download', $doc),
            'url_preview'       => route('documents.preview', $doc),
            'url_versions'      => route('documents.versions', $doc),
            'url_televerser_signe' => route('documents.televerser_signe', $doc),
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
            // L'accord client n'est pas un acte — exclu de la liste générique (Actes &
            // documents / Certification) et exposé à part, seul InformationsTab le consomme.
            'documents'   => $d->documents->where('categorie', '!=', 'accord_client')
                ->map(fn ($doc) => $this->documentFichierToArray($doc))->values(),
            'accordClient' => ($accordClient = $d->documents->firstWhere('categorie', 'accord_client'))
                ? $this->documentFichierToArray($accordClient)
                : null,
            // Titre, consigne et bouton d'impression dépendent du type de dossier : une
            // modification de statuts attend la décision des associés, pas une fiche signée.
            'accordAttendu' => $d->pieceAccordAttendue(),
            'revision'    => $d->revision ? [
                'id'         => $d->revision->id,
                'statut'     => $d->revision->statut?->value,
                'commentaire' => $d->revision->commentaire,
                'reviseur'   => $d->revision->reviseur ? ['name' => $d->revision->reviseur->name] : null,
                'points'     => $d->revision->points->map(fn ($p) => [
                    'point_id'    => $p->point_id,
                    'etat'        => $p->etat,
                    'commentaire' => $p->commentaire,
                    'perime'      => (bool) $p->perime,
                ]),
                'estValidable' => $d->revision->estValidable(),
            ] : null,
            'formalites'  => $d->formalites
                ->sortBy([['ordre', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($f) => $f->versArray(auth()->user())),
            'parties' => $d->parties->map(fn ($p) => [
                'id'        => $p->id,
                'nom'       => $p->nom,
                'role'      => $p->role,
                'type_personne' => $p->type_personne,
                'cni'       => $p->cni,
                'telephone' => $p->telephone,
                'adresse'   => $p->adresse,
                'email'     => $p->email,
                'initiales' => $p->initiales,
                'client_id' => $p->client_id,
                'client'    => $p->client ? [
                    'id'           => $p->client->id,
                    'type'         => $p->client->type,
                    'civilite'     => $p->client->civilite,
                    'prenom_nom'   => $p->client->prenom_nom,
                    'denomination' => $p->client->denomination,
                    'piece_numero' => $p->client->piece_numero,
                    'telephone'    => $p->client->telephone,
                    'forme'        => $p->client->forme,
                    'rccm'         => $p->client->rccm,
                ] : null,
                'photo'     => ($photo = $p->pieces->firstWhere('categorie', 'photo'))
                    ? $this->documentFichierToArray($photo)
                    : null,
                // Les catégories de la checklist typée (piecesChecklist ci-dessous) sont
                // exclues d'ici pour ne pas être affichées deux fois.
                'pieces'    => $p->pieces
                    ->whereNotIn('categorie', array_merge(['photo'], Partie::categoriesPiecesRequises()))
                    ->values()
                    ->map(fn ($doc) => $this->documentFichierToArray($doc)),
                'piecesChecklist' => $p->piecesChecklist(),
            ]),
            'journal' => $d->journal->map(fn ($j) => [
                'id'         => $j->id,
                'action'     => $j->action,
                'type'       => $j->type,
                'user'       => $j->user ? ['name' => $j->user->name, 'initiales' => $j->user->initiales] : null,
                'created_at' => $j->created_at->diffForHumans(),
            ]),
            'factures' => $d->factures->map(fn ($f) => $f->versArray()),
            'courriers' => $d->courriers->map(fn ($c) => [
                'id'             => $c->id,
                'reference'      => $c->reference,
                'objet'          => $c->objet,
                'destinataire'   => $c->destinataire,
                'type'           => $c->type,
                'statut'         => $c->statut,
                'envoye_at'      => $c->envoye_at?->format('d/m/Y'),
                'redacteur'      => $c->redacteur?->name,
                'chemin_fichier' => $c->chemin_fichier,
                'has_file'       => (bool) $c->chemin_fichier,
                'est_requis'        => (bool) $c->est_requis,
                'est_signe_cachete' => (bool) $c->est_signe_cachete,
                'signe_cachete_at'  => $c->signe_cachete_at?->format('d/m/Y H:i'),
                'signe_cachete_par' => $c->signeCachetePar?->name,
                'url_download'      => $c->chemin_fichier ? route('courriers.download', $c) : null,
                'url_preview'       => $c->chemin_fichier ? route('courriers.preview', $c) : null,
                'url_televerser_signe' => route('courriers.televerser_signe', $c),
            ]),
            'courrierModelesApplicables' => $this->courrierModelesApplicables($d),
            // Inventaire de clôture : toutes les pièces produites par le workflow,
            // rangées par rubrique. Alimente l'onglet Clôture — et sert de source unique
            // avec la GED pour que les deux rangements ne divergent pas.
            'inventaireCloture' => $this->inventaire->pour($d),
            'clotureProgression' => $this->inventaire->progression($d),
        ];
    }

    /**
     * Lettres de transmission (ModeleCourrier) applicables au type d'acte précis
     * du dossier — sert à la fois à l'onglet Expédition et au blocage de sortie
     * d'étape (DossierStepService::verifierExpedition).
     */
    private function courrierModelesApplicables(Dossier $d): \Illuminate\Support\Collection
    {
        return ModeleCourrier::with('typesActes')
            ->actif()
            ->get()
            ->filter(fn (ModeleCourrier $m) => $m->applicablePour($d->typeActe))
            ->map(fn (ModeleCourrier $m) => ['id' => $m->id, 'nom' => $m->nom])
            ->values();
    }
}
