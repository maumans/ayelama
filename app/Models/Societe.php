<?php

namespace App\Models;

use App\Enums\EtapeDossier;
use App\Enums\FormeSociete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Fiche société — **registre réutilisable** de l'étude depuis le 2026-08-11.
 *
 * La table existait depuis le 2026-06-29 mais n'était jamais alimentée : les dénominations
 * ne vivaient que dans `questionnaires.donnees`, si bien qu'ouvrir un dossier de
 * modification obligeait à retaper le nom de la société, sa forme, son capital et son
 * siège — informations que l'étude possédait déjà pour l'avoir constituée.
 *
 * Rôle exactement symétrique de celui de {@see Client} pour les personnes (décision #33) :
 * la fiche est la **source de vérité**, `questionnaires.donnees` n'en est qu'une projection
 * dérivée. `versQuestionnaire()` / `depuisQuestionnaire()` sont les deux sens de cette
 * traduction, et le seul endroit du projet qui connaisse la correspondance
 * colonne ↔ clé `soc.*`.
 */
class Societe extends Model
{
    protected $fillable = [
        'dossier_id',
        'denomination', 'forme', 'sigle',
        'capital_chiffres', 'nombre_parts', 'valeur_nominale_chiffres',
        'siege_quartier', 'siege_commune', 'siege_ville',
        'email_societe', 'telephone_societe',
        'objet_social', 'duree', 'exercice_social',
        'date_acte', 'date_constitution', 'notaire_origine',
        'rccm_numero', 'nif', 'jal_journal',
        'direction',
        'commissaire_titulaire', 'commissaire_suppleant',
        'derniere_modification_at', 'actif',
    ];

    /**
     * Correspondance clé de questionnaire ↔ colonne.
     *
     * Unique référence de ce mapping : le backfill, la création d'un dossier de
     * constitution, le préremplissage d'une modification et SocieteMutationService s'en
     * servent tous. Le dupliquer, c'était garantir qu'une clé oubliée d'un côté produise
     * des balises Word vides de l'autre.
     */
    private const CHAMPS_QUESTIONNAIRE = [
        'soc.denomination'             => 'denomination',
        'soc.forme'                    => 'forme',
        'soc.sigle'                    => 'sigle',
        'soc.capital_chiffres'         => 'capital_chiffres',
        'soc.nombre_parts'             => 'nombre_parts',
        'soc.valeur_nominale_chiffres' => 'valeur_nominale_chiffres',
        'soc.siege_quartier'           => 'siege_quartier',
        'soc.siege_commune'            => 'siege_commune',
        'soc.siege_ville'              => 'siege_ville',
        'soc.email_societe'            => 'email_societe',
        'soc.telephone_societe'        => 'telephone_societe',
        'soc.objet_social'             => 'objet_social',
        'soc.duree'                    => 'duree',
        'soc.rccm'                     => 'rccm_numero',
        'soc.nif'                      => 'nif',
        'soc.date_constitution'        => 'date_constitution',
    ];

    protected function casts(): array
    {
        return [
            'capital_chiffres'         => 'decimal:2',
            'valeur_nominale_chiffres' => 'decimal:2',
            'nombre_parts'             => 'integer',
            'duree'                    => 'integer',
            'date_acte'                => 'date',
            'date_constitution'        => 'date',
            'derniere_modification_at' => 'datetime',
            'actif'                    => 'boolean',
            'direction'                => 'array',
        ];
    }

    /**
     * Dossier de **constitution** de la société (son origine).
     *
     * Conservé sous ce nom : la relation existe depuis l'origine de la table et
     * `dossierOrigine()` en est l'alias explicite. Ne pas confondre avec `dossiers()`,
     * qui liste tous les dossiers portant *sur* cette société.
     */
    public function dossier()
    {
        return $this->belongsTo(Dossier::class);
    }

    /** Alias lisible de {@see dossier()} — c'est le dossier qui a créé la société. */
    public function dossierOrigine()
    {
        return $this->dossier();
    }

    /** Tous les dossiers portant sur cette société : constitution, modifications, dissolution. */
    public function dossiers()
    {
        return $this->hasMany(Dossier::class);
    }

    /**
     * Recherche pour l'autocomplétion. `sigle` et `nif` sont inclus : une société est
     * souvent connue de l'étude par son sigle plutôt que par sa raison sociale complète.
     */
    public function scopeRecherche(Builder $query, string $terme): Builder
    {
        return $query->where(function (Builder $q) use ($terme) {
            $q->where('denomination', 'like', "%{$terme}%")
                ->orWhere('sigle', 'like', "%{$terme}%")
                ->orWhere('rccm_numero', 'like', "%{$terme}%")
                ->orWhere('nif', 'like', "%{$terme}%");
        });
    }

    public function scopeActif(Builder $query): Builder
    {
        return $query->where('actif', true);
    }

    public function formeEnum(): ?FormeSociete
    {
        return $this->forme ? FormeSociete::tryFrom(trim($this->forme)) : null;
    }

    /**
     * Libellé complet de la forme juridique.
     *
     * Délègue à {@see FormeSociete::label()} plutôt que de reproduire un `match` : la
     * version précédente en portait un, et il **ignorait SNC, SCS et SAU** — les trois
     * formes ajoutées par le CR de juillet 2026. Une seule table de vérité évite que la
     * prochaine forme ajoutée s'affiche à nouveau sous son sigle brut.
     */
    public function formeLabel(): string
    {
        return $this->formeEnum()?->label() ?? ($this->forme ?? '');
    }

    /**
     * Forme normalisée d'une dénomination, pour comparaison.
     *
     * Insensible à la casse, aux espaces multiples et aux espaces de bord. Sert à la règle 4
     * (« la dénomination sociale doit être UNIQUE », voir
     * {@see \App\Services\ReglesSocieteService::verifierDenominationUnique()}) **et** au
     * rapprochement des questionnaires existants avec les fiches du registre lors du
     * backfill. Une seule implémentation : deux normalisations divergentes feraient qu'une
     * dénomination jugée unique par la règle créerait malgré tout un doublon au registre.
     */
    public static function normaliserDenomination(mixed $valeur): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim((string) $valeur)));
    }

    /** Dénomination suivie du sigle, tel qu'on désigne la société dans une liste. */
    public function nomComplet(): string
    {
        return $this->sigle
            ? "{$this->denomination} ({$this->sigle})"
            : (string) $this->denomination;
    }

    /**
     * Attributs de fiche déduits d'un questionnaire de dossier de société.
     *
     * `soc.forme` est souvent absent des questionnaires (constat du 2026-08-05 : les 10
     * dossiers réels en sont tous dépourvus, la forme vivant dans le code du type d'acte) —
     * l'appelant passe donc `$formeParDefaut`, tiré de
     * {@see FormeSociete::depuisCodeTypeActe()}.
     *
     * @param  array<string, mixed> $donnees
     * @return array<string, mixed> prêt pour create() / update()
     */
    public static function depuisQuestionnaire(array $donnees, ?string $formeParDefaut = null): array
    {
        $attributs = [];

        foreach (self::CHAMPS_QUESTIONNAIRE as $cle => $colonne) {
            if (array_key_exists($cle, $donnees) && filled($donnees[$cle])) {
                $attributs[$colonne] = $donnees[$cle];
            }
        }

        if (blank($attributs['forme'] ?? null) && $formeParDefaut) {
            $attributs['forme'] = $formeParDefaut;
        }

        return $attributs;
    }

    /**
     * Complète les colonnes **vides** de la fiche depuis un questionnaire, sans jamais en écraser
     * une renseignée.
     *
     * Contrepartie du fait qu'un champ `soc.*` que la fiche ne renseigne pas reste saisissable dans
     * l'assistant (voir `ficheRenseigneChamp` côté frontend) : `MICH SARL` était au registre sans
     * numéro RCCM, et le champ correspondant — obligatoire — était masqué donc impossible à remplir.
     * Sans cette méthode, la saisie ne vivrait que dans le questionnaire du dossier : le dossier
     * suivant redemanderait la même information et le registre resterait indéfiniment incomplet.
     *
     * **Jamais d'écrasement** : une valeur déjà au registre est la vérité de référence, et une
     * correction de fiche se fait depuis le registre, pas en creux par un formulaire de dossier.
     * Modifier une valeur existante relève d'une modification statutaire — c'est le travail de
     * {@see \App\Services\SocieteMutationService}, appliqué à l'entrée en Expédition.
     *
     * @param  array<string, mixed> $donnees
     * @return array<string, mixed> colonnes effectivement complétées, pour le journal
     */
    public function completerDepuisQuestionnaire(array $donnees): array
    {
        $ajouts = [];

        foreach (self::CHAMPS_QUESTIONNAIRE as $cle => $colonne) {
            if (filled($this->{$colonne}) || blank($donnees[$cle] ?? null)) {
                continue;
            }

            $ajouts[$colonne] = $donnees[$cle];
        }

        if ($ajouts !== []) {
            $this->update($ajouts);
        }

        return $ajouts;
    }

    /**
     * Projection de la fiche dans les clés `soc.*` d'un questionnaire.
     *
     * C'est ce qui préremplit le questionnaire d'un dossier de modification et, surtout, ce
     * qui peuple les balises `${soc.*}` attendues par les modèles Word : sans cette
     * projection, choisir une société dans le registre afficherait la bonne fiche à l'écran
     * mais produirait des actes aux champs vides.
     *
     * Symétrique de {@see \App\Services\ClientProjectionService} pour les personnes.
     *
     * @return array<string, mixed>
     */
    public function versQuestionnaire(): array
    {
        $donnees = [];

        foreach (self::CHAMPS_QUESTIONNAIRE as $cle => $colonne) {
            $valeur = $this->{$colonne};

            if (blank($valeur)) {
                continue;
            }

            // Les dates partent au format ISO : c'est ce que les champs `date` du
            // questionnaire attendent, et le format sous lequel les autres valeurs de
            // `donnees` sont déjà stockées.
            $donnees[$cle] = $valeur instanceof \DateTimeInterface
                ? $valeur->format('Y-m-d')
                : $valeur;
        }

        // Hors de CHAMPS_QUESTIONNAIRE, qui est une correspondance clé↔colonne un-pour-un : le
        // gérant est **calculé** (json `direction` ou parties du dossier d'origine), pas stocké
        // dans une colonne. Traité à part, comme `depuisQuestionnaire()` traite déjà `forme`.
        //
        // Sans cette ligne, `soc.gerant_actuel` restait vide alors que le nom était affiché juste
        // au-dessus dans « Personnes connues de cette société » — et la balise
        // `${soc.gerant_actuel}` du PV et des statuts mis à jour sortait vide.
        if ($gerant = $this->gerantActuel()) {
            $donnees['soc.gerant_actuel'] = $gerant;
        }

        return $donnees;
    }

    /**
     * Dossier constitutif de la société, versé au registre.
     *
     * Nécessaire pour les sociétés que l'étude **n'a pas constituées** : il n'existe alors ni
     * dossier d'origine, ni statuts, ni RCCM en base. Rattachées à la société et non à un dossier,
     * ces pièces sont réutilisées par chacune de ses modifications futures — une seconde
     * modification deux ans plus tard doit les retrouver sans les redemander.
     *
     * Quatrième `documentable` de {@see DocumentFichier}, après Dossier, Formalite et Partie.
     */
    public function piecesConstitutives()
    {
        return $this->morphMany(DocumentFichier::class, 'documentable')->orderBy('id');
    }

    /**
     * Pièces du dossier constitutif — slug ⇒ libellé + caractère requis.
     *
     * Même forme que {@see Partie::PIECES_REQUISES}, à ceci près que le caractère requis est porté
     * par la pièce : seules deux d'entre elles bloquent l'avancement.
     *
     * `statuts` est bloquant parce qu'on ne peut pas produire des « statuts mis à jour » sans avoir
     * vu les statuts d'origine — ce sont même eux qui en servent de gabarit (voir
     * {@see gabaritStatutsDocx()}). `rccm` l'est parce qu'il porte l'identité légale reprise dans
     * tous les actes.
     */
    public const PIECES_CONSTITUTIVES = [
        'statuts'             => ['label' => 'Statuts en vigueur',                        'requis' => true],
        'rccm'                => ['label' => 'Extrait / déclaration RCCM',                'requis' => true],
        'nif'                 => ['label' => 'NIF / attestation fiscale',                 'requis' => false],
        'actes_modificatifs'  => ['label' => 'PV et actes modificatifs antérieurs',       'requis' => false],
        'attestation_capital' => ['label' => 'Attestation de dépôt du capital',           'requis' => false],
        'insertion_jal'       => ['label' => "Insertion au journal d'annonces légales",   'requis' => false],
        'dnsv'                => ['label' => "DNSV d'origine",                            'requis' => false],
    ];

    /**
     * Cette société doit-elle fournir son dossier constitutif ?
     *
     * **C'est le pivot de tout le dispositif** : une société que l'étude a constituée n'a rien à
     * fournir — son dossier d'origine contient déjà statuts, PV, RCCM et DNSV, et `dossier_id` en
     * est la preuve. Seules les sociétés entrées au registre à la main (celles qu'un autre office a
     * constituées) sont concernées.
     */
    public function exigePiecesConstitutives(): bool
    {
        return $this->dossier_id === null;
    }

    /**
     * État des pièces constitutives, prêt pour l'affichage.
     *
     * Miroir de {@see Partie::piecesChecklist()} — même forme de retour, donc les mêmes composants
     * de ligne (aperçu sous la ligne, téléversement) sont réutilisables tels quels.
     *
     * @return array<int, array>
     */
    public function piecesConstitutivesChecklist(): array
    {
        $existantes = $this->piecesConstitutives
            ->whereIn('categorie', array_keys(self::PIECES_CONSTITUTIVES))
            ->keyBy('categorie');

        return collect(self::PIECES_CONSTITUTIVES)->map(function (array $definition, string $slug) use ($existantes) {
            $piece = $existantes->get($slug);

            return [
                'id'             => $piece?->id,
                'categorie'      => $slug,
                'label'          => $definition['label'],
                'requis'         => $definition['requis'],
                'est_fourni'     => (bool) $piece?->est_fourni,
                'aUnFichier'     => (bool) $piece?->versionActuelle,
                'chemin_fichier' => $piece?->versionActuelle?->chemin_fichier,
                'nom_original'   => $piece?->versionActuelle?->nom_original,
                'version'        => $piece?->versionActuelle?->numero,
                // Seul un .docx peut servir de gabarit : le clerc doit le savoir avant la
                // génération, pas la découvrir en ouvrant l'acte produit.
                'exploitableCommeGabarit' => $slug === 'statuts' && $this->estDocx($piece),
            ];
        })->values()->all();
    }

    /**
     * Pièces requises non encore fournies — libellés, consommés par la règle bloquante.
     *
     * @return array<int, string>
     */
    public function piecesConstitutivesManquantes(): array
    {
        return collect($this->piecesConstitutivesChecklist())
            ->filter(fn (array $p) => $p['requis'] && !$p['aUnFichier'])
            ->pluck('label')
            ->values()
            ->all();
    }

    public function statutsEnVigueur(): ?DocumentFichier
    {
        return $this->piecesConstitutives->firstWhere('categorie', 'statuts');
    }

    /**
     * Chemin **absolu** des statuts déposés, s'ils peuvent servir de gabarit — `null` sinon.
     *
     * `PhpWord\TemplateProcessor` dézippe un OOXML : un PDF, un `.doc` ou une image le feraient
     * échouer. Le dépôt reste volontairement ouvert au PDF — c'est souvent ce que le client
     * apporte — mais seul un `.docx` est exploitable comme gabarit, et l'appelant retombe alors sur
     * le modèle de l'étude (voir ActesGeneratorService::gabaritPour()).
     */
    public function gabaritStatutsDocx(): ?string
    {
        return $this->gabaritStatutsEnVigueur()['absolu'] ?? null;
    }

    /**
     * Statuts **en vigueur** de la société, utilisables comme gabarit des statuts mis à jour.
     *
     * Trois sources, du plus récent au plus ancien :
     *
     *   1. la pièce constitutive `statuts` **déposée au registre** — le cas d'une société que
     *      l'étude n'a pas constituée ;
     *   2. le document `statuts_maj` de la **dernière modification devenue effective** ;
     *   3. le document `acte_principal` du **dossier de constitution**.
     *
     * Le point 2 n'est pas une élégance : après une première modification, les statuts en vigueur
     * sont ceux qu'elle a produits, non ceux de la constitution. Restreint aux dossiers en
     * **Expédition ou Clôture** — le seuil où {@see \App\Services\SocieteMutationService} considère
     * déjà la modification opposable. Un dossier de modification abandonné ne doit pas devenir la
     * référence des suivants.
     *
     * `origine` accompagne le chemin : le rédacteur doit savoir de quel texte il part (voir la
     * journalisation dans ActesGeneratorService).
     *
     * @return array{absolu: string, origine: string}|null
     */
    public function gabaritStatutsEnVigueur(): ?array
    {
        // 1. Pièce déposée au registre.
        $this->loadMissing('piecesConstitutives.versionActuelle');
        $depose = $this->statutsEnVigueur();

        if ($this->estDocx($depose) && $chemin = $this->cheminExploitable($depose)) {
            return ['absolu' => $chemin, 'origine' => 'les statuts déposés au registre'];
        }

        // 2. Statuts mis à jour de la dernière modification effective.
        // `whereHasMorph` et non `whereHas` : `documentable` est polymorphe, et un `whereHas` irait
        // chercher `societe_id` sur formalites et parties, qui ne l'ont pas.
        $modification = DocumentFichier::where('categorie', 'statuts_maj')
            ->whereHasMorph('documentable', [Dossier::class], fn ($q) => $q
                ->where('societe_id', $this->id)
                ->whereIn('etape', [EtapeDossier::Expedition->value, EtapeDossier::Cloture->value]))
            ->with(['versionActuelle', 'documentable'])
            ->latest('id')
            ->get()
            ->first(fn (DocumentFichier $d) => $this->estDocx($d) && $this->cheminExploitable($d));

        if ($modification) {
            return [
                'absolu'  => $this->cheminExploitable($modification),
                'origine' => 'les statuts mis à jour du dossier ' . $modification->documentable->reference,
            ];
        }

        // 3. Statuts d'origine, produits par le dossier de constitution.
        if ($this->dossier_id) {
            $constitution = DocumentFichier::where('documentable_type', Dossier::class)
                ->where('documentable_id', $this->dossier_id)
                ->where('categorie', 'acte_principal')
                ->with('versionActuelle')
                ->latest('id')
                ->get()
                ->first(fn (DocumentFichier $d) => $this->estDocx($d) && $this->cheminExploitable($d));

            if ($constitution) {
                return [
                    'absolu'  => $this->cheminExploitable($constitution),
                    'origine' => 'les statuts du dossier de constitution ' . $this->dossier?->reference,
                ];
            }
        }

        return null;
    }

    /** Chemin absolu de la version courante, si le fichier est bien présent sur le disque. */
    private function cheminExploitable(?DocumentFichier $piece): ?string
    {
        $chemin = $piece?->versionActuelle?->chemin_fichier;

        return $chemin && Storage::disk('public')->exists($chemin)
            ? Storage::disk('public')->path($chemin)
            : null;
    }

    private function estDocx(?DocumentFichier $piece): bool
    {
        $chemin = $piece?->versionActuelle?->chemin_fichier;

        return $chemin !== null
            && strtolower(pathinfo($chemin, PATHINFO_EXTENSION)) === 'docx';
    }

    /** Sous-répertoire de stockage de ses pièces sur le disque `public`. */
    public function repertoirePieces(): string
    {
        return 'societes/' . $this->id;
    }

    /**
     * Personnes déjà connues pour cette société — associés, associé unique et gérants du
     * dossier de constitution, avec leur fiche client.
     *
     * Pour une cession de parts, le cédant est presque toujours un associé déjà fiché : le
     * proposer directement évite de le ressaisir, et garantit surtout que c'est bien la
     * **même** fiche client qui est réutilisée (décision #33 — la fiche client est la source
     * de vérité de l'identité, une ressaisie créerait un doublon concurrent).
     *
     * @return \Illuminate\Support\Collection<int, Partie>
     */
    public function associesConnus()
    {
        if (!$this->dossier_id) {
            return collect();
        }

        return Partie::query()
            ->where('dossier_id', $this->dossier_id)
            ->whereIn('role', ['associe', 'associe_unique', 'gerant'])
            ->with('client')
            ->get();
    }

    /**
     * Rôles susceptibles de tenir la direction, **par ordre de préférence**.
     *
     * Couvre les 9 formes, dont le dirigeant ne porte pas le même titre : `gerant` en SARL / SARLU /
     * SNC / SCS, `president` en SAS / SASU, `pca` et `dg` en SA, `administrateur` en GIE.
     *
     * `associe_unique` **en dernier** : c'est une déduction et non un rôle de direction déclaré —
     * dans une SARLU ou une SASU, l'associé unique dirige dans la quasi-totalité des cas, et le
     * questionnaire de constitution ne crée une partie dédiée que si la case « le gérant est une
     * personne différente de l'associé unique » a été cochée.
     *
     * L'ordre compte : sans lui, une SA rendrait son directeur général là où son PCA est le
     * représentant légal.
     */
    private const ROLES_DIRECTION = [
        'gerant', 'president', 'pca', 'dg', 'administrateur', 'associe_unique',
    ];

    /**
     * Nom du dirigeant en exercice, ou `null` s'il est inconnu.
     *
     * Deux sources, dans cet ordre :
     *
     *   1. `direction['gerant']` — **autoritaire**, écrit par
     *      {@see \App\Services\SocieteMutationService} à chaque changement de gérance porté au
     *      registre. C'est la vérité dès qu'une modification a été appliquée.
     *   2. les parties du **dossier de constitution**, via {@see ROLES_DIRECTION}.
     *
     * Le repli n'est pas un raffinement : `direction` est nulle sur toutes les fiches issues du
     * backfill (le rapprochement des questionnaires ne la peuplait pas), donc sans lui le dirigeant
     * resterait introuvable pour l'intégralité du registre existant.
     *
     * Requête propre plutôt que passage par `associesConnus()` : celle-ci ne retient que les rôles
     * utiles à l'import de cédants (`associe`, `associe_unique`, `gerant`) et laisserait échapper le
     * président d'une SAS ou le PCA d'une SA.
     */
    public function gerantActuel(): ?string
    {
        $declare = $this->direction['gerant'] ?? null;

        if (filled($declare)) {
            return trim((string) $declare);
        }

        if (!$this->dossier_id) {
            return null;
        }

        $parNom = Partie::query()
            ->where('dossier_id', $this->dossier_id)
            ->whereIn('role', self::ROLES_DIRECTION)
            ->get()
            ->keyBy('role');

        foreach (self::ROLES_DIRECTION as $role) {
            $nom = $parNom->get($role)?->nom;

            if (filled($nom)) {
                return trim((string) $nom);
            }
        }

        return null;
    }
}
