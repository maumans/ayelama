<?php

namespace App\Models;

use App\Enums\FormeTitreRepresentation;
use App\Enums\MotifRepresentation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Partie extends Model
{
    protected $fillable = [
        'dossier_id', 'client_id', 'nom', 'role', 'type_personne', 'cni',
        'telephone', 'adresse', 'email',
        // Emplacement de cette partie dans le questionnaire — renseigné par le
        // frontend, seul détenteur du schéma (voir la migration
        // add_donnees_mapping_to_parties_table et ClientProjectionService).
        'donnees_prefixe', 'donnees_bloc', 'donnees_index',
        // Représentation : portée par le REPRÉSENTÉ (un mandataire sert plusieurs
        // mandants, chacun avec son propre titre).
        'represente_par_partie_id', 'representation_motif', 'representation_qualite',
        'representation_titre_forme', 'representation_titre_date',
        'representation_titre_autorite', 'representation_titre_reference',
    ];

    protected function casts(): array
    {
        return [
            'donnees_index'              => 'integer',
            'representation_motif'       => MotifRepresentation::class,
            'representation_titre_forme' => FormeTitreRepresentation::class,
            'representation_titre_date'  => 'date',
        ];
    }

    /**
     * Avant de supprimer une partie, délier ceux qu'elle représente.
     *
     * La clé étrangère est auto-référente et `ON DELETE SET NULL`, sur une table par ailleurs
     * détruite en cascade par `dossiers.id`. MySQL est capricieux sur cette combinaison — il
     * peut refuser la cascade tant qu'une référence interne subsiste — là où SQLite, qui
     * reconstruit la table, ne dit rien. Le test passerait, la suppression d'un dossier
     * échouerait en développement. On délie donc explicitement, avant.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $partie) {
            static::where('represente_par_partie_id', $partie->id)
                ->update(['represente_par_partie_id' => null]);
        });
    }

    /**
     * Cette partie est-elle projetable dans le questionnaire, c'est-à-dire liée à
     * une fiche client ET localisée dans le schéma ?
     *
     * Deux localisations possibles : un préfixe pour une section scalaire
     * (`pp.`, `ger.`…), ou un bloc répétable + index (`associes[1]`). Les items de
     * bloc n'ont pas de préfixe — leurs clés sont à plat dans l'item.
     */
    public function estProjetable(): bool
    {
        return $this->client_id !== null && $this->estLocalisee();
    }

    /**
     * Cette partie occupe-t-elle un emplacement du questionnaire ?
     *
     * Distinguée de `estProjetable()` parce que la **mention de comparution** doit être écrite
     * même pour une personne sans fiche client (saisie libre) : sans fiche il n'y a pas
     * d'identité à projeter, mais il y a toujours une façon de comparaître à restituer dans
     * l'acte. Un représentant, lui, n'est jamais localisé pour lui-même.
     */
    public function estLocalisee(): bool
    {
        return filled($this->donnees_prefixe) || filled($this->donnees_bloc);
    }

    /**
     * Restreint aux parties projetables — même condition que estProjetable(),
     * exprimée en SQL pour filtrer les dossiers d'un client sans les charger tous.
     */
    public function scopeProjetables($query)
    {
        return $query->whereNotNull('client_id')
            ->where(fn ($q) => $q->whereNotNull('donnees_prefixe')->orWhereNotNull('donnees_bloc'));
    }

    /**
     * Restreint aux parties localisées, avec ou sans fiche client — miroir SQL de
     * `estLocalisee()`. Sert à `reprojeterDossiersDuClient()`, qui doit aussi retrouver les
     * dossiers où le client corrigé n'est **que** représentant : sans ce scope, corriger la
     * fiche d'un mandataire ne toucherait aucun dossier.
     */
    public function scopeLocalisees($query)
    {
        return $query->where(fn ($q) => $q->whereNotNull('donnees_prefixe')->orWhereNotNull('donnees_bloc'));
    }

    /** La personne qui comparaît pour celle-ci, le cas échéant. */
    public function representant()
    {
        return $this->belongsTo(self::class, 'represente_par_partie_id');
    }

    /** Les parties que celle-ci représente — plusieurs, couramment. */
    public function representes()
    {
        return $this->hasMany(self::class, 'represente_par_partie_id');
    }

    /** Cette partie se fait-elle représenter, et par quelqu'un de désigné ? */
    public function estRepresentee(): bool
    {
        return $this->representation_motif !== null && $this->represente_par_partie_id !== null;
    }

    /**
     * Pièces requises par rôle (+ type de personne pour un associé) — reflète le
     * questionnaire papier officiel (constitution SARL/SARLU). Toutes les catégories
     * listées ici doivent rester exclues du tableau générique `pieces` exposé au
     * frontend (voir DossierController::dossierDetailToArray()) pour éviter un doublon
     * d'affichage avec la checklist typée.
     */
    private const PIECES_REQUISES = [
        'associe_physique' => [
            'cni'                  => 'CNI / Passeport',
            'certificat_residence' => 'Certificat de résidence',
            'photo_secondaire'     => 'Deuxième photo d\'identité',
        ],
        // Règle 6 du CR de juillet 2026 : quand une personne morale est associée, « le PV
        // de l'assemblée générale de la société associée est OBLIGATOIRE ». L'entrée
        // unique « Déclaration RCCM **ou** PV de délibération » rendait de fait le PV
        // facultatif — l'alternative suffisait à cocher la pièce. Les deux sont désormais
        // distinctes et toutes deux exigées.
        'associe_morale' => [
            'statuts'          => 'Statuts',
            'declaration_rccm' => 'Déclaration RCCM',
            'pv_ag'            => "PV de l'assemblée générale autorisant la participation",
            'cni_representant' => 'CNI/passeport du représentant légal',
        ],
        'gerant' => [
            'cni'                  => 'CNI / Passeport',
            'certificat_residence' => 'Certificat de résidence',
            'photo_secondaire'     => 'Deuxième photo d\'identité',
        ],

        // ── Représentation (2026-09-24) ──────────────────────────────────────

        // Le représentant comparaît, il n'est **pas** partie à l'acte : on vérifie qui il est,
        // rien de plus. Ni certificat de résidence ni deuxième photo — ceux-là attestent du
        // rattachement d'une partie à l'acte, et il n'en est pas une.
        'mandataire' => [
            'cni' => 'CNI / Passeport du représentant',
        ],

        // Les titres sont portés par le REPRÉSENTÉ : c'est son mandat. Trois mandants
        // représentés par le même homme, c'est trois procurations et une seule CNI.
        'titre_procuration' => [
            'procuration' => 'Procuration',
        ],
        'titre_legale' => [
            // ⚠️ Libellé délibérément prudent : le document guinéen qui établit une tutelle
            // n'a pas été arbitré avec l'étude. Déclaré ici et nulle part ailleurs, et rendu
            // non bloquant par PIECES_A_CONFIRMER tant que la réponse n'est pas connue.
            'acte_tutelle' => 'Décision désignant le tuteur ou le curateur (forme à préciser)',
        ],
        // Aucun jeu pour l'organique : le PV d'assemblée et la déclaration RCCM sont déjà
        // collectés par `associe_morale`. Voir MotifRepresentation::jeuDePiecesDuTitre().
    ];

    /**
     * Pièces exigées dont **la nature même** reste à arbitrer avec l'étude.
     *
     * Elles apparaissent à la checklist, avec leur libellé prudent, mais ne bloquent pas
     * l'avancement : refuser de sortir d'Initialisation pour un document qu'on ne sait pas
     * nommer serait inventer une contrainte. Le jour où l'étude tranche, on retire l'entrée
     * d'ici et le blocage s'applique — sans autre changement.
     */
    public const PIECES_A_CONFIRMER = ['acte_tutelle'];

    /**
     * Pièces qu'on ne propose **jamais** à la reprise depuis un autre dossier.
     *
     * Une procuration vaut pour un acte déterminé : la reprendre d'un dossier antérieur ferait
     * comparaître un mandataire sur un pouvoir qui ne couvre pas l'opération. Une décision de
     * tutelle, à l'inverse, est un attribut durable de la personne : elle reste reprenable,
     * avec sa date, comme le mécanisme le prévoit déjà.
     */
    private const PIECES_NON_REPRENABLES = ['procuration'];

    /**
     * Rôle → jeu de pièces. Table de correspondance plutôt qu'une cascade de `in_array`,
     * pour que l'ajout d'un rôle (les modifications statutaires en ont apporté quatre) soit
     * une ligne de données et non une branche de logique supplémentaire.
     *
     * Ne figurent pas ici, volontairement : `gerant_sortant`, `president_seance`,
     * `secretaire_seance`. Ces personnes sont **mentionnées** à l'acte, elles n'y apportent
     * rien — exiger leur CNI bloquerait le dossier pour une pièce que l'étude n'a aucune
     * raison de réclamer (un gérant révoqué, a fortiori décédé, ne fournit plus de
     * justificatif).
     */
    private const JEU_PAR_ROLE = [
        'associe'        => 'associe_physique',
        'associe_unique' => 'associe_physique',
        'gerant'         => 'gerant',
        // Modification statutaire (2026-08-11) : ces rôles apportent les mêmes pièces
        // d'identité qu'un associé à la constitution — c'est la même vérification
        // d'identité, sur un acte différent.
        'cedant'         => 'associe_physique',
        'cessionnaire'   => 'associe_physique',
        'souscripteur'   => 'associe_physique',
        'gerant_entrant' => 'gerant',
        // Représentant, quel que soit le motif : un seul rôle, la distinction étant portée
        // par le motif du lien (MotifRepresentation::ROLE). Trois rôles dupliqueraient
        // l'information, et un mandataire servant à la fois un associé et un cédant n'aurait
        // aucune valeur cohérente.
        'mandataire'     => 'mandataire',
    ];

    /**
     * Rôles dont une **personne morale** doit produire le jeu de pièces d'une société
     * associée (statuts, déclaration RCCM, PV d'AG autorisant l'opération, CNI du
     * représentant légal — règle 6 du CR de juillet 2026).
     *
     * `gerant_entrant` en est exclu : la gérance d'une SARL est exercée par une personne
     * physique.
     */
    private const ROLES_ADMETTANT_PERSONNE_MORALE = [
        'associe', 'associe_unique', 'cedant', 'cessionnaire', 'souscripteur',
    ];

    /**
     * Rôles d'un questionnaire qui n'appellent **aucune** pièce, et pourquoi.
     *
     * Contrepartie de JEU_PAR_ROLE : tout `clientRole` déclaré dans `questionnaires.js`
     * doit figurer dans l'une ou l'autre de ces deux tables, jamais dans aucune. Sans ce
     * « aucune pièce, décidé » explicite, l'oubli d'un rôle est indistinguable d'un rôle
     * délibérément sans pièce — c'est exactement ce qui a laissé `cedant`, `cessionnaire`,
     * `souscripteur` et `gerant_entrant` sans checklist côté écran pendant des mois.
     *
     * PiecesRequisesParRoleTest interdit la troisième possibilité.
     */
    private const ROLES_SANS_PIECES = [
        // Mentionnés à l'acte, ils n'y apportent rien : réclamer leur CNI bloquerait le
        // dossier pour une pièce que l'étude n'a aucune raison d'exiger (un gérant révoqué,
        // a fortiori décédé, ne fournit plus de justificatif).
        'gerant_sortant', 'president_seance', 'secretaire_seance',
        // Professionnels désignés à l'acte, dont l'agrément — et non l'identité — fait foi.
        'commissaire_titulaire', 'commissaire_suppleant', 'administrateur', 'liquidateur',
        // ⚠️ Jamais arbitré, pas « décidé sans pièces ». Ces rôles figuraient dans la liste
        // blanche de Create.jsx, qui leur affichait une checklist toujours vide faute de jeu
        // déclaré ici. Le comportement est donc inchangé — mais il est désormais écrit, et
        // c'est le seul endroit où le corriger le jour où l'étude tranchera.
        'vendeur', 'acheteur', 'bailleur', 'locataire', 'creancier', 'debiteur',
        'actionnaire', 'membre',
    ];

    public static function categoriesPiecesRequises(): array
    {
        return array_values(array_unique(array_merge(...array_values(array_map('array_keys', self::PIECES_REQUISES)))));
    }

    /**
     * La règle « rôle → jeu de pièces », servie en **données** au frontend.
     *
     * Elle y était réécrite quatre fois — RepeatableGroup.jsx, Create.jsx, Show.jsx, plus la
     * normalisation physique/morale de partiesPayload.js — et les quatre copies divergeaient :
     * trois rôles couverts sur sept, et deux écrans qui n'affichaient pas la même chose pour le
     * même dossier. `piecesRequisesParCle()` ne suffisait pas : elle expose la table des jeux,
     * pas la règle qui choisit le jeu. C'est la règle qui manquait.
     *
     * @return array<string, string> rôle → clé de jeu
     */
    public static function jeuParRole(): array
    {
        return self::JEU_PAR_ROLE;
    }

    /**
     * Rôle → jeu applicable quand la personne est **morale**.
     *
     * Une map et non la liste ROLES_ADMETTANT_PERSONNE_MORALE : ainsi le nom du jeu
     * (`associe_morale`) reste déclaré ici, et le résolveur JS n'a plus aucun nom de jeu en
     * dur — condition pour que le test de parité puisse l'affirmer.
     *
     * @return array<string, string>
     */
    public static function jeuMoraleParRole(): array
    {
        return array_fill_keys(self::ROLES_ADMETTANT_PERSONNE_MORALE, 'associe_morale');
    }

    /** @return array<int, string> Rôles délibérément sans pièce — voir ROLES_SANS_PIECES. */
    public static function rolesSansPieces(): array
    {
        return self::ROLES_SANS_PIECES;
    }

    /**
     * Tout ce dont le frontend a besoin pour résoudre les pièces d'une personne, en un bloc.
     *
     * Servi à l'identique par `DossierController::create()` et `show()`. Les deux écrans
     * recevaient auparavant la seule table des jeux et recomposaient la règle chacun de son
     * côté — d'où deux affichages différents pour le même dossier. Un accesseur unique rend la
     * divergence impossible : il n'y a plus qu'une chose à passer.
     */
    public static function reglesPiecesRequises(): array
    {
        return [
            'jeux'          => self::piecesRequisesParCle(),
            'parRole'       => self::jeuParRole(),
            'moraleParRole' => self::jeuMoraleParRole(),
        ];
    }

    /**
     * Supprime la partie **et** ses pièces, fichiers compris.
     *
     * `pieces()` est un morphMany : aucune contrainte de clé étrangère ne l'accompagne, donc un
     * `delete()` nu laisse les `DocumentFichier`, leurs versions et les fichiers sur le disque.
     * PartieController::destroy() faisait le ménage, la resynchronisation du questionnaire non —
     * deux comportements pour le même geste. Les deux passent désormais par ici.
     */
    public function supprimerAvecPieces(): void
    {
        foreach ($this->pieces as $piece) {
            $piece->supprimerAvecFichiers();
        }

        $this->delete();
    }

    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function pieces()
    {
        return $this->morphMany(DocumentFichier::class, 'documentable')->orderBy('id');
    }

    /**
     * ⚠️ `mb_substr` et non `$w[0]` : voir User::getInitialesAttribute(). Indexer une chaîne
     * rend un octet, et la première lettre d'un nom accentué en occupe deux — les initiales
     * n'étaient alors plus de l'UTF-8 valide, et `json_encode()` faisait échouer toute la
     * réponse. Une partie nommée « Émile DIALLO » renvoyait un 500 sur la fiche dossier.
     */
    public function getInitialesAttribute(): string
    {
        return mb_strtoupper(
            collect(explode(' ', $this->nom ?? ''))
                ->map(fn ($mot) => mb_substr($mot, 0, 1))
                ->filter()
                ->take(2)
                ->join('')
        );
    }

    public static function piecesRequisesPour(string $role, string $typePersonne = 'physique'): array
    {
        if ($typePersonne === 'morale' && in_array($role, self::ROLES_ADMETTANT_PERSONNE_MORALE, true)) {
            return self::PIECES_REQUISES['associe_morale'] ?? [];
        }

        $jeu = self::JEU_PAR_ROLE[$role] ?? null;

        return $jeu ? (self::PIECES_REQUISES[$jeu] ?? []) : [];
    }

    public static function piecesRequisesParCle(): array
    {
        return self::PIECES_REQUISES;
    }

    /**
     * Les pièces attendues de **cette** personne, motif de représentation compris.
     *
     * Composition plutôt que nouveaux jeux complets : croiser rôle × type × motif ferait
     * 3 × 8 × 2 combinaisons à déclarer, quand l'ajout est en réalité additif.
     *
     *   pièces d'un représenté = jeu de base (rôle × type) + jeu du titre (motif)
     *   pièces d'un représentant = jeu `mandataire`, par son rôle
     *
     * Le motif `organique` **retire** `cni_representant` du jeu `associe_morale` : la CNI du
     * représentant légal est désormais réclamée au représentant lui-même, qui est une partie
     * du dossier avec sa propre checklist. La demander aux deux endroits ferait déposer deux
     * fois le même document. Les dossiers sans représentation déclarée gardent exactement le
     * comportement d'avant — aucune donnée n'est déplacée (voir devbook, décision #50).
     */
    public function piecesRequisesDefinition(): array
    {
        $requis = self::piecesRequisesPour($this->role ?? '', $this->type_personne ?? 'physique');

        $motif = $this->representation_motif;
        if (! $motif instanceof MotifRepresentation) {
            return $requis;
        }

        if ($motif === MotifRepresentation::Organique) {
            unset($requis['cni_representant']);
        }

        $jeuDuTitre = $motif->jeuDePiecesDuTitre();

        return $jeuDuTitre
            ? array_merge($requis, self::PIECES_REQUISES[$jeuDuTitre] ?? [])
            : $requis;
    }

    /**
     * Cette pièce bloque-t-elle l'avancement si elle manque ?
     *
     * Tout bloque, sauf ce dont l'étude n'a pas encore arrêté la nature — voir
     * PIECES_A_CONFIRMER. Lu par la checklist et par le contrôle d'étape, pour que les deux
     * disent la même chose.
     */
    public static function pieceEstBloquante(string $categorie): bool
    {
        return ! in_array($categorie, self::PIECES_A_CONFIRMER, true);
    }

    /**
     * Manque-t-il à cette personne une pièce qui empêche le dossier d'avancer ?
     *
     * Le prédicat vivait en double — `DossierStepService::erreursDeConstitution()`, qui décide,
     * et `DossierController::constitutionComplete()`, qui pilote le bouton « Avancer » de la
     * liste. Le devbook §9 nommait déjà cette divergence comme un point faible connu ; ajouter
     * la règle des pièces à confirmer aux deux copies l'aurait aggravée. Un seul détenteur.
     */
    public function aUnePieceBloquanteManquante(): bool
    {
        foreach ($this->piecesChecklist() as $item) {
            if (! $item['est_fourni'] && self::pieceEstBloquante($item['categorie'])) {
                return true;
            }
        }

        return false;
    }

    public function piecesChecklist(): array
    {
        $requis = $this->piecesRequisesDefinition();
        if (!$requis) {
            return [];
        }

        $existantes  = $this->pieces->whereIn('categorie', array_keys($requis))->keyBy('categorie');
        $reprenables = $this->piecesReprenables();

        return collect($requis)->map(fn ($label, $slug) => [
            'id'             => $existantes->get($slug)?->id,
            'categorie'      => $slug,
            'label'          => $label,
            'est_fourni'     => (bool) $existantes->get($slug)?->est_fourni,
            'aUnFichier'     => (bool) $existantes->get($slug)?->versionActuelle,
            'chemin_fichier' => $existantes->get($slug)?->versionActuelle?->chemin_fichier,
            'version'        => $existantes->get($slug)?->versionActuelle?->numero,
            // Pièce que cette même personne a déjà fournie dans un autre dossier — proposée à la
            // reprise plutôt que redemandée. `null` dès qu'elle est fournie ici.
            'reprise'        => $reprenables[$slug] ?? null,
            // Pièce dont l'étude n'a pas encore arrêté la nature : elle se dépose, mais son
            // absence ne bloque pas. L'écran doit le dire, sans quoi le clerc croirait à un
            // blocage muet.
            'a_confirmer'    => ! self::pieceEstBloquante($slug),
        ])->values()->all();
    }

    /**
     * Pièces requises **manquantes ici** que cette même personne a déjà fournies ailleurs.
     *
     * Signalé à l'usage : un dossier de modification réclamait CNI, certificat de résidence et
     * deuxième photo à un souscripteur qui les avait déjà déposées lors de la constitution de la
     * même société. Une pièce d'identité est un attribut de la **personne**, pas du dossier.
     *
     * Le rapprochement se fait sur `client_id` **exclusivement** : c'est le seul lien fiable entre
     * deux `Partie` (la fiche client est la source de vérité de l'identité — décision #33). Deux
     * homonymes ne sont pas la même personne, et une partie sans fiche client ne propose donc rien.
     *
     * La **date** accompagne chaque proposition : une pièce d'identité a une durée de validité, et
     * reprendre un scan de trois ans sans le voir serait pire que de le redemander.
     *
     * @return array<string, array{piece_id: int, dossier: string, date: ?string}> indexé par catégorie
     */
    public function piecesReprenables(): array
    {
        if (!$this->client_id) {
            return [];
        }

        $requis = $this->piecesRequisesDefinition();
        if (!$requis) {
            return [];
        }

        $dejaFournies = $this->pieces
            ->filter(fn (DocumentFichier $p) => $p->versionActuelle !== null)
            ->pluck('categorie')
            ->all();

        // Une procuration vaut pour un acte déterminé : la proposer depuis un autre dossier
        // ferait comparaître un mandataire sur un pouvoir qui ne couvre pas l'opération. Le
        // retrait se fait ici **et** dans reprendrePiece(), sinon la route
        // `parties.pieces.reprendre` offrirait le contournement.
        $manquantes = array_diff(array_keys($requis), $dejaFournies, self::PIECES_NON_REPRENABLES);
        if ($manquantes === []) {
            return [];
        }

        $autresParties = self::where('client_id', $this->client_id)
            ->where('id', '!=', $this->id)
            ->with(['dossier:id,reference', 'pieces.versionActuelle'])
            ->get();

        $reprenables = [];

        foreach ($autresParties as $partie) {
            foreach ($partie->pieces as $piece) {
                if (!in_array($piece->categorie, $manquantes, true) || !$piece->versionActuelle) {
                    continue;
                }

                $date = $piece->versionActuelle->created_at;

                // La plus récente l'emporte : une pièce d'identité renouvelée doit primer sur
                // l'ancienne, sans quoi on proposerait de reprendre un document périmé.
                $connue = $reprenables[$piece->categorie] ?? null;
                if ($connue && $connue['_date'] !== null && $date !== null && $connue['_date']->gte($date)) {
                    continue;
                }

                $reprenables[$piece->categorie] = [
                    'piece_id' => $piece->id,
                    'dossier'  => $partie->dossier?->reference,
                    'date'     => $date?->format('d/m/Y'),
                    '_date'    => $date,
                ];
            }
        }

        return array_map(
            fn (array $r) => collect($r)->except('_date')->all(),
            $reprenables,
        );
    }

    /**
     * Reprend une pièce fournie ailleurs par la même personne, **en la copiant**.
     *
     * ⚠️ Copie et non référence. Un dossier notarial doit être physiquement complet — c'est ce que
     * l'inventaire de clôture atteste et ce que l'archive conserve. Et deux `DocumentFichier`
     * pointant sur le même chemin seraient un piège : `supprimerAvecFichiers()` effacerait le
     * fichier sous les pieds de l'autre dossier. `nouvelleVersion()` accepte pourtant une chaîne
     * (chemin déjà stocké), ce qui rendrait l'erreur facile à commettre.
     *
     * La version porte `source = 'reprise'`, aux côtés de `upload`, `genere`, `restauration` et
     * `signe_cachete` : l'historique doit dire d'où vient chaque fichier.
     */
    public function reprendrePiece(string $categorie, DocumentFichier $source): DocumentFichier
    {
        $version = $source->versionActuelle;
        $requis  = $this->piecesRequisesDefinition();

        if (!$version || !array_key_exists($categorie, $requis)
            || in_array($categorie, self::PIECES_NON_REPRENABLES, true)) {
            throw new \InvalidArgumentException("Pièce « {$categorie} » non reprenable pour cette personne.");
        }

        $piece = $this->pieces()->firstOrCreate(
            ['categorie' => $categorie],
            ['nom' => $requis[$categorie], 'est_requis' => true],
        );

        $extension = pathinfo($version->chemin_fichier, PATHINFO_EXTENSION);
        $numero    = ($piece->versions()->max('numero') ?? 0) + 1;
        $cible     = 'parties/' . $this->dossier->reference . '/'
            . Str::slug($this->nom . '-' . $categorie) . '_v' . $numero
            . ($extension ? '.' . $extension : '');

        Storage::disk('public')->copy($version->chemin_fichier, $cible);

        $piece->nouvelleVersion($cible, 'parties/' . $this->dossier->reference, [
            'source'        => 'reprise',
            'nom_original'  => $version->nom_original,
            'mime_type'     => $version->mime_type,
            'taille_octets' => $version->taille_octets,
        ]);

        return $piece->fresh();
    }
}
