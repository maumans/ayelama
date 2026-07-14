<?php

namespace App\Enums;

enum StatutFormalite: string
{
    case ADeposer    = 'a_deposer';
    case Depose      = 'depose';
    case EnAttente   = 'en_attente';
    case RetourRecu  = 'retour_recu';
    case Rejete      = 'rejete';
    case Cloture     = 'cloture';

    public function label(): string
    {
        return match($this) {
            self::ADeposer   => 'À déposer',
            self::Depose     => 'Déposé',
            self::EnAttente  => 'En attente de retour',
            self::RetourRecu => 'Retour reçu',
            self::Rejete     => 'Rejeté — à corriger',
            self::Cloture    => 'Clôturé',
        };
    }
}
