<?php

namespace App\Models;

use App\Support\Normalisation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;

/**
 * Lieu du référentiel : ville, commune ou quartier.
 *
 * Voir la migration `create_lieux_table` pour le choix d'une table auto-référencée, et pour la
 * raison qui fait que les questionnaires stockent le **nom** et non l'identifiant.
 */
class Lieu extends Model
{
    protected $table = 'lieux';

    public const NIVEAU_VILLE    = 'ville';
    public const NIVEAU_COMMUNE  = 'commune';
    public const NIVEAU_QUARTIER = 'quartier';

    /**
     * Niveau d'un lieu → niveau de ses enfants. `null` pour un quartier : c'est le dernier étage.
     *
     * Table plutôt qu'une cascade de `if` : ajouter un niveau (district, secteur) sera une ligne de
     * données, et la validation comme la cascade la consultent toutes deux.
     */
    public const NIVEAU_ENFANT = [
        self::NIVEAU_VILLE    => self::NIVEAU_COMMUNE,
        self::NIVEAU_COMMUNE  => self::NIVEAU_QUARTIER,
        self::NIVEAU_QUARTIER => null,
    ];

    /** Clé du référentiel complet en cache — voir `referentielComplet()`. */
    public const CLE_CACHE_REFERENTIEL = 'lieux.referentiel';

    protected $fillable = ['parent_id', 'niveau', 'nom', 'nom_normalise', 'a_verifier', 'actif', 'created_by_id', 'source'];

    protected function casts(): array
    {
        return ['a_verifier' => 'boolean', 'actif' => 'boolean'];
    }

    /**
     * `nom_normalise` est toujours dérivé de `nom` — jamais fourni par l'appelant.
     *
     * Sans ce point unique, une création par un contrôleur, un seeder ou une commande pourrait poser
     * une forme comparable incohérente, et l'unicité ne protégerait plus de rien.
     */
    protected static function booted(): void
    {
        static::saving(function (self $lieu) {
            $lieu->nom_normalise = Normalisation::comparable($lieu->nom);
        });

        // Le référentiel complet est servi depuis le cache : toute écriture doit l'y périmer,
        // sinon un lieu ajouté en pleine saisie resterait invisible jusqu'au prochain vidage.
        // `saved` **et** `deleted` : la désactivation passe par une mise à jour, la suppression non.
        static::saved(fn () => static::oublierReferentiel());
        static::deleted(fn () => static::oublierReferentiel());
    }

    public static function oublierReferentiel(): void
    {
        Cache::forget(self::CLE_CACHE_REFERENTIEL);
    }

    /**
     * Le référentiel **entier**, groupé par niveau, le parent désigné par son nom.
     *
     * ⚠️ Servait deux fois : cette requête vivait dans `IntakeController` et la cascade interrogeait
     * par ailleurs un point d'entrée **par niveau et par champ**. Sur le questionnaire de
     * modification, qui porte 18 champs géographiques, cela faisait jusqu'à 18 requêtes à
     * l'ouverture, puis une par changement de ville ou de commune — c'est cette multiplication que
     * l'étude percevait comme une lenteur, pas le poids des données : le référentiel entier pèse
     * 5 Ko, et moins de 15 Ko compressé même à 2 400 lieux.
     *
     * Le parent est donné par son **nom** et non par son identifiant : c'est ce que les fiches et
     * les questionnaires détiennent (`soc.siege_ville = "Conakry"`), et ce que les actes reprennent.
     *
     * @return array<string, list<array{nom: string, parent: ?string}>>
     */
    public static function referentielComplet(): array
    {
        return Cache::rememberForever(self::CLE_CACHE_REFERENTIEL, fn () => [
            // Les trois niveaux sont **toujours** présents, même vides : sans ce socle,
            // `groupBy` n omet les niveaux sans lieu et un appelant lisant $ref['ville'] casse sur
            // un référentiel neuf. La forme du contrat ne doit pas dépendre du contenu.
            self::NIVEAU_VILLE    => [],
            self::NIVEAU_COMMUNE  => [],
            self::NIVEAU_QUARTIER => [],
            ...self::actif()
            ->with('parent:id,nom')
            ->orderBy('nom')
            ->get(['id', 'parent_id', 'niveau', 'nom'])
            ->groupBy('niveau')
            ->map(fn ($groupe) => $groupe->map(fn (self $l) => [
                'nom'    => $l->nom,
                'parent' => $l->parent?->nom,
            ])->values())
            ->toArray(),
        ]);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function enfants()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('nom');
    }

    public function creePar()
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActif($query)
    {
        return $query->where('actif', true);
    }

    public function scopeNiveau($query, string $niveau)
    {
        return $query->where('niveau', $niveau);
    }

    /** Niveau attendu des enfants de ce lieu — `null` s'il n'en admet pas. */
    public function niveauEnfant(): ?string
    {
        return self::NIVEAU_ENFANT[$this->niveau] ?? null;
    }

    /**
     * Le lieu proposé est-il un enfant recevable de ce parent ?
     *
     * Garde-fou du référentiel : sans lui, un quartier pourrait être rattaché directement à une
     * ville, et la cascade proposerait des communes là où on attend des quartiers.
     */
    public function accepteEnfant(string $niveau): bool
    {
        return $this->niveauEnfant() === $niveau;
    }

    /**
     * Retrouve un lieu par son **nom**, sous un parent donné — c'est ainsi que les valeurs des
     * questionnaires (qui portent le nom) sont rapprochées du référentiel.
     */
    public static function parNom(?string $nom, string $niveau, ?int $parentId = null): ?self
    {
        if (blank($nom)) {
            return null;
        }

        return self::where('niveau', $niveau)
            ->where('nom_normalise', Normalisation::comparable($nom))
            ->when($parentId !== null, fn ($q) => $q->where('parent_id', $parentId))
            ->first();
    }

    /**
     * Noms de lieux **déjà employés** dans les fiches et les questionnaires, sous forme comparable.
     *
     * Calculé une fois pour tout l'écran plutôt que par lieu : 126 lieux contre 18 questionnaires,
     * une vérification lieu par lieu ferait autant de balayages du JSON.
     *
     * @return array<string, true> ensemble indexé par nom normalisé
     */
    public static function nomsEmployes(): array
    {
        $employes = [];

        $ajouter = function (?string $valeur) use (&$employes) {
            if (filled($valeur)) {
                $employes[Normalisation::comparable($valeur)] = true;
            }
        };

        foreach (Client::query()->get(['demeurant_ville', 'commune', 'quartier']) as $client) {
            $ajouter($client->demeurant_ville);
            $ajouter($client->commune);
            $ajouter($client->quartier);
        }

        foreach (Societe::query()->get(['siege_ville', 'siege_commune', 'siege_quartier']) as $societe) {
            $ajouter($societe->siege_ville);
            $ajouter($societe->siege_commune);
            $ajouter($societe->siege_quartier);
        }

        // Les questionnaires portent les lieux sous des clés préfixées variables
        // (`soc.siege_commune`, `pp.quartier`, `demeurant_ville` dans les blocs répétables) : on
        // balaie donc les clés par suffixe plutôt que par une liste fermée.
        foreach (Questionnaire::query()->get(['donnees']) as $questionnaire) {
            self::parcourirLieux((array) $questionnaire->donnees, $ajouter);
        }

        return $employes;
    }

    /** Parcourt un questionnaire, blocs répétables compris, en appelant `$ajouter` sur chaque lieu. */
    private static function parcourirLieux(array $donnees, callable $ajouter): void
    {
        foreach ($donnees as $cle => $valeur) {
            if (is_array($valeur)) {
                foreach ($valeur as $item) {
                    if (is_array($item)) {
                        self::parcourirLieux($item, $ajouter);
                    }
                }
                continue;
            }

            if (! is_string($valeur)) {
                continue;
            }

            $estLieu = str_ends_with($cle, 'quartier')
                || str_ends_with($cle, 'commune')
                || str_ends_with($cle, 'ville');

            if ($estLieu) {
                $ajouter($valeur);
            }
        }
    }

    /**
     * Ce lieu peut-il être supprimé ?
     *
     * **Non** s'il a des enfants — supprimer une commune emporterait ses quartiers en cascade, sans
     * que rien ne l'ait annoncé. **Non** s'il est employé quelque part : son nom figure alors dans
     * une fiche ou un questionnaire, et donc possiblement dans un acte déjà produit ; c'est là que
     * la désactivation est la bonne réponse.
     *
     * **Oui** dans tous les autres cas — un lieu ajouté par erreur, mal orthographié ou placé au
     * mauvais niveau doit pouvoir partir. Ne proposer que la désactivation laissait le référentiel
     * se remplir de scories sans recours.
     *
     * @param array<string, true>|null $nomsEmployes ensemble précalculé (voir `nomsEmployes()`)
     */
    public function estSupprimable(?array $nomsEmployes = null): bool
    {
        if ($this->enfants()->exists()) {
            return false;
        }

        $employes = $nomsEmployes ?? self::nomsEmployes();

        return ! isset($employes[$this->nom_normalise]);
    }

    /** Ville · Commune · Quartier, tel qu'on désigne un lieu dans une liste. */
    public function chemin(): string
    {
        $segments = [];

        for ($lieu = $this; $lieu !== null; $lieu = $lieu->parent) {
            $segments[] = $lieu->nom;
        }

        return implode(' · ', array_reverse($segments));
    }
}
