<?php

namespace App\Enums;

/**
 * Cycle de vie d'une **fiche société**, distinct du workflow d'un dossier.
 *
 * La distinction n'est pas cosmétique : « en liquidation » a longtemps été proposé comme une
 * étape de dossier de plus. Ce n'en est pas une. Une liquidation dure des mois ou des années,
 * pendant lesquels le dossier de dissolution est clos depuis longtemps et un second dossier
 * (la clôture) n'est pas encore ouvert. L'état appartient donc à la personne morale, pas au
 * dossier qui l'a fait changer d'état.
 *
 * Remplace la colonne `societes.actif`, supprimée le même jour. Son commentaire de migration
 * annonçait exactement cette intention (« une société dissoute ne doit plus être proposée »),
 * mais **aucune ligne de code ne l'a jamais écrite** : 14 fiches sur 14 à `true`. Et son
 * intention est désormais contredite par la conception — la clôture de liquidation doit
 * précisément proposer une société dissoute.
 *
 * **Quatre cas et non trois.** `LiquidationCloturee` pourrait se déduire d'une date, mais le
 * délai de radiation se mesure depuis elle : en faire un état dérivé, c'est un état réel
 * qu'aucun `match` n'obligerait à traiter. Juridiquement aussi, la personne morale survit à la
 * clôture jusqu'à la radiation — elle peut encore être assignée.
 *
 * ⚠️ Les `match` de cet enum sont exhaustifs et **sans branche par défaut** : ajouter un cas
 * doit casser bruyamment ici plutôt que se propager en silence. Le miroir JS
 * (`resources/js/data/statutsSociete.js`) n'a, lui, aucun filet — un test de parité le tient.
 */
enum StatutSociete: string
{
    case Active              = 'active';
    case EnLiquidation       = 'en_liquidation';
    case LiquidationCloturee = 'liquidation_cloturee';
    case Radiee              = 'radiee';

    public function label(): string
    {
        return match ($this) {
            self::Active              => 'Active',
            self::EnLiquidation       => 'En liquidation',
            self::LiquidationCloturee => 'Liquidation clôturée',
            self::Radiee              => 'Radiée',
        };
    }

    /**
     * Phrase destinée au clerc, affichée sous le badge de statut au sélecteur de société.
     *
     * Le dépôt préfère « un blocage énuméré à un bouton grisé » : le sélecteur ne cache aucune
     * fiche, il explique. Sans ces phrases, une société radiée proposée dans une liste ressemble
     * à une société ordinaire.
     */
    public function explication(): string
    {
        return match ($this) {
            self::Active              => 'Société en activité.',
            self::EnLiquidation       => 'Dissolution prononcée ; les opérations de liquidation sont en cours.',
            self::LiquidationCloturee => 'Liquidation clôturée ; la radiation au RCCM reste à constater.',
            self::Radiee              => 'Radiée du RCCM : la personne morale a cessé d\'exister.',
        };
    }

    /**
     * Mention accolée à la dénomination **dans un acte**, parenthèses comprises.
     *
     * Les deux actes réels de l'étude ne s'accordaient pas : le procès-verbal écrit
     * « (EN COURS DE LIQUIDATION) », l'insertion au journal « (EN LIQUIDATION) », pour la même
     * société au même moment. La mention se dérive donc du statut de la fiche — une balise
     * `${soc.mention_liquidation}` plutôt que deux saisies libres qui divergent.
     *
     * Chaîne vide pour une société active : un acte de constitution n'accole rien.
     */
    public function mentionActe(): string
    {
        return match ($this) {
            self::Active              => '',
            self::EnLiquidation       => '(EN LIQUIDATION)',
            self::LiquidationCloturee => '(LIQUIDATION CLÔTURÉE)',
            self::Radiee              => '(RADIÉE)',
        };
    }

    /** Couleur du badge — miroir de la convention de `ETAPE_META` côté dossier. */
    public function couleur(): string
    {
        return match ($this) {
            self::Active              => 'emerald',
            self::EnLiquidation       => 'amber',
            self::LiquidationCloturee => 'orange',
            self::Radiee              => 'slate',
        };
    }

    /**
     * La dissolution a-t-elle été prononcée ?
     *
     * Vrai dès `EnLiquidation` : c'est l'état à partir duquel la société ne peut plus faire
     * l'objet d'une nouvelle dissolution, et à partir duquel les délais de liquidation courent.
     */
    public function estDissoute(): bool
    {
        return match ($this) {
            self::Active                                        => false,
            self::EnLiquidation,
            self::LiquidationCloturee,
            self::Radiee                                        => true,
        };
    }

    /**
     * Cette société a-t-elle encore une existence juridique ?
     *
     * La personne morale survit à la dissolution **pour les besoins de la liquidation**, et
     * jusqu'à la radiation. Seule la radiation y met fin.
     */
    public function existeEncore(): bool
    {
        return match ($this) {
            self::Active,
            self::EnLiquidation,
            self::LiquidationCloturee => true,
            self::Radiee              => false,
        };
    }

    /** @return array<int, array{valeur: string, label: string, couleur: string, explication: string}> */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => [
            'valeur'      => $c->value,
            'label'       => $c->label(),
            'couleur'     => $c->couleur(),
            'explication' => $c->explication(),
        ], self::cases());
    }
}
