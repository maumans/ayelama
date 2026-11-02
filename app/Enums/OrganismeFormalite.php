<?php

namespace App\Enums;

/**
 * L'organisme auprès duquel une formalité se dépose.
 *
 * ⚠️ **Cette liste vivait en cinq exemplaires**, sans aucun test pour les tenir d'accord :
 *
 *  1. `ParametresController::ORGANISMES`, en capitales initiales, côté barème ;
 *  2. la validation `in:apip,impots,conservation_fonciere,cnss,greffe` de `FormaliteController` ;
 *  3. `Formalite::labelOrganisme()` ;
 *  4. `ORGANISME_META` de `resources/js/data/formaliteStatuts.js` ;
 *  5. `ORGANISMES_ONGLETS` de `Formalites/Index.jsx`.
 *
 * Et elles **divergeaient déjà** : `Autre` existait côté barème (c'est sous ce nom que
 * l'insertion au journal d'annonces légales était posée) mais ni la validation, ni les
 * onglets, ni les libellés ne le connaissaient. Une formalité « autre » était donc refusée à
 * la modification manuelle et **invisible dans l'écran Formalités** — elle existait en base,
 * personne ne la voyait. C'est exactement la famille de défauts silencieux que le dépôt
 * refuse, et elle s'est révélée en cherchant où poser la publication au JAL.
 *
 * Le pendant JS est `ORGANISME_META`, verrouillé par {@see \Tests\Feature\OrganismesFormaliteTest}.
 */
enum OrganismeFormalite: string
{
    case Apip                 = 'apip';
    case Impots               = 'impots';
    case ConservationFonciere = 'conservation_fonciere';
    case Cnss                 = 'cnss';
    case Greffe               = 'greffe';

    /**
     * Le journal d'annonces légales.
     *
     * Ajouté le 2026-11-02. La publication est une étape à part entière du tableau de
     * validation signé le 2026-10-28 : exigée pour chaque modification (lignes 1 à 5) et pour
     * la dissolution (ligne 15), absente de la clôture de liquidation (ligne 17). Elle était
     * jusqu'ici rangée sous « Autre », qui n'existait pas côté formalité — la démarche la plus
     * systématique du cycle de vie d'une société était donc la seule qu'on ne pouvait pas
     * suivre.
     */
    case Jal = 'jal';

    /**
     * Tout le reste.
     *
     * Conservé parce que des barèmes le portent déjà, et qu'un organisme inconnu vaut mieux
     * qu'une formalité refusée à l'enregistrement. Il n'est pas un fourre-tout pour autant :
     * la publication en est justement sortie.
     */
    case Autre = 'autre';

    /** Le nom complet, pour une phrase ou un courrier. */
    public function label(): string
    {
        return match ($this) {
            self::Apip                 => 'APIP',
            self::Impots               => 'Direction des Impôts',
            self::ConservationFonciere => 'Conservation foncière',
            self::Cnss                 => 'CNSS',
            self::Greffe               => 'Greffe du Tribunal de Commerce',
            self::Jal                  => "Journal d'annonces légales",
            self::Autre                => 'Autre organisme',
        };
    }

    /** Le nom court, pour un badge — en capitales, comme les autres pastilles de l'écran. */
    public function abrege(): string
    {
        return match ($this) {
            self::Apip                 => 'APIP',
            self::Impots               => 'IMPÔTS',
            self::ConservationFonciere => 'FONCIER',
            self::Cnss                 => 'CNSS',
            self::Greffe               => 'GREFFE',
            self::Jal                  => 'JAL',
            self::Autre                => 'AUTRE',
        };
    }

    /**
     * Le nom tel que la colonne `baremes.organisme` l'écrit — en capitales initiales.
     *
     * ⚠️ **Deux écritures pour une même notion, et c'est un héritage, pas un choix.** Les
     * barèmes stockent `Greffe`, les formalités `greffe` ; `Bareme::organismeFormalite()` fait
     * la conversion. La renommer d'un côté toucherait des barèmes référencés par des factures
     * émises. On garde donc les deux écritures, mais une seule source les déclare.
     */
    public function libelleBareme(): string
    {
        return match ($this) {
            self::Apip                 => 'APIP',
            self::Impots               => 'Impots',
            self::ConservationFonciere => 'Conservation',
            self::Cnss                 => 'CNSS',
            self::Greffe               => 'Greffe',
            self::Jal                  => 'JAL',
            self::Autre                => 'Autre',
        };
    }

    /** Les organismes auprès desquels on dépose — `Autre` n'ouvre pas d'onglet. */
    public static function suivis(): array
    {
        return array_values(array_filter(self::cases(), fn (self $o) => $o !== self::Autre));
    }

    /** @return array<int, string> Pour une règle de validation `Rule::in()`. */
    public static function valeurs(): array
    {
        return array_map(fn (self $o) => $o->value, self::cases());
    }

    /** @return array<int, array{valeur: string, label: string, abrege: string}> */
    public static function toutes(): array
    {
        return array_map(
            fn (self $o) => ['valeur' => $o->value, 'label' => $o->label(), 'abrege' => $o->abrege()],
            self::cases(),
        );
    }

    public static function depuisLibelleBareme(?string $libelle): self
    {
        foreach (self::cases() as $organisme) {
            if ($organisme->libelleBareme() === $libelle) {
                return $organisme;
            }
        }

        return self::Autre;
    }
}
