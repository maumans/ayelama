<?php

namespace App\Enums;

/**
 * Une facture naît **proforma** et devient **émise** (retour R20, 2026-11-16).
 *
 * Jusqu'ici, chaque dossier consommait un numéro de facture dès sa création, alors que la note
 * produite à ce moment-là tenait lieu de proforma : un dossier abandonné laissait un numéro émis
 * sans objet. Le numéro `NNN/MAB/AA` n'est désormais attribué qu'à l'émission.
 */
enum StatutFacture: string
{
    case Proforma = 'proforma';
    case Emise    = 'emise';

    public function label(): string
    {
        return match ($this) {
            self::Proforma => 'Proforma',
            self::Emise    => 'Émise',
        };
    }

    /** Titre imprimé en tête du document. */
    public function titreDocument(): string
    {
        return match ($this) {
            self::Proforma => 'FACTURE PROFORMA',
            self::Emise    => 'FACTURE', // titre de l'exemplaire fourni par l'étude
        };
    }
}
