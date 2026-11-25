<?php

namespace App\Enums;

/**
 * Une facture de dossier est la **principale** (calculée sur les barèmes, régénérable tant
 * qu'elle est proforma) ou une **complémentaire**, établie après coup pour un montant que
 * l'organisme seul connaît.
 *
 * Décision de l'étude du 2026-11-23 : les droits d'enregistrement d'une constitution ne sont
 * connus qu'au **retour des Impôts**, quand la facture principale est déjà émise — figée. Ils
 * font donc l'objet d'une facture complémentaire, numérotée dans le même registre.
 */
enum NatureFacture: string
{
    case Principale     = 'principale';
    case Complementaire = 'complementaire';

    public function label(): string
    {
        return match ($this) {
            self::Principale     => 'Facture principale',
            self::Complementaire => 'Facture complémentaire',
        };
    }
}
