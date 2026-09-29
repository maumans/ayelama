<?php

namespace App\Enums;

/**
 * Comment une remise de ligne de facture est **exprimée**.
 *
 * Deux façons de dire la même chose : « 500 000 GNF de remise » ou « 10 % de remise ». Le
 * formulaire lie les deux — on saisit l'une, l'autre se calcule — mais **une seule est
 * stockée**, celle que l'utilisateur a réellement choisie.
 *
 * ⚠️ Ce n'est pas une coquetterie : les deux ne se comportent pas pareil quand le tarif change.
 * Une remise en **pourcentage** suit le nouveau montant (10 % de 5 M restent 10 % de 4 M), une
 * remise en **montant** reste ce qu'elle est. Stocker le montant calculé au lieu du choix
 * d'origine ferait perdre cette distinction à la première régénération de facture — et la
 * facture est régénérée par `FacturationService::genererFacture()`, qui supprime et recrée tout.
 */
enum TypeRemise: string
{
    case Montant     = 'montant';
    case Pourcentage = 'pourcentage';

    public function label(): string
    {
        return match ($this) {
            self::Montant     => 'Montant (GNF)',
            self::Pourcentage => 'Pourcentage (%)',
        };
    }

    /** Symbole affiché à côté du champ de saisie. */
    public function suffixe(): string
    {
        return match ($this) {
            self::Montant     => 'GNF',
            self::Pourcentage => '%',
        };
    }

    /**
     * Montant de la remise, en francs, pour un montant brut donné.
     *
     * ⚠️ **Plafonné au brut** : une remise ne peut pas rendre une ligne négative. La validation
     * la refuse déjà en amont, mais un tarif qui baisse après coup — une régénération de
     * facture — peut rendre une remise en montant supérieure à la nouvelle ligne. Plafonner ici
     * évite qu'une facture affiche un total négatif ; l'écart est signalé par ailleurs.
     */
    public function montantSurBrut(float $valeur, float $brut): float
    {
        if ($brut <= 0 || $valeur <= 0) {
            return 0.0;
        }

        $remise = match ($this) {
            self::Montant     => $valeur,
            self::Pourcentage => $brut * $valeur / 100,
        };

        return round(min($remise, $brut), 2);
    }

    /** @return array<int, array{valeur: string, label: string, suffixe: string}> */
    public static function toutes(): array
    {
        return array_map(fn (self $c) => [
            'valeur'  => $c->value,
            'label'   => $c->label(),
            'suffixe' => $c->suffixe(),
        ], self::cases());
    }
}
