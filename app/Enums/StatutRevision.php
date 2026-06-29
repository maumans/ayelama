<?php

namespace App\Enums;

enum StatutRevision: string
{
    case EnAttente = 'en_attente';
    case EnCours   = 'en_cours';
    case Valide    = 'valide';
    case Renvoye   = 'renvoye';

    public function label(): string
    {
        return match($this) {
            self::EnAttente => 'En attente',
            self::EnCours   => 'En cours',
            self::Valide    => 'Validé',
            self::Renvoye   => 'Renvoyé en correction',
        };
    }
}
