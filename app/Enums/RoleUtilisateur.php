<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case Clerc        = 'clerc';
    case Reviseur     = 'reviseur';
    case Notaire      = 'notaire';
    case Formaliste   = 'formaliste';
    case Administrateur = 'administrateur';

    public function label(): string
    {
        return match($this) {
            self::Clerc          => 'Clerc / Rédacteur',
            self::Reviseur       => 'Réviseur / Responsable',
            self::Notaire        => 'Notaire (Maître)',
            self::Formaliste     => 'Formaliste',
            self::Administrateur => 'Administrateur',
        };
    }

    public function peutOuvrir(): bool
    {
        return in_array($this, [self::Clerc, self::Administrateur]);
    }

    public function peutReviser(): bool
    {
        return in_array($this, [self::Reviseur, self::Notaire, self::Administrateur]);
    }

    public function peutSigner(): bool
    {
        return in_array($this, [self::Notaire, self::Administrateur]);
    }

    public function peutGererFormalites(): bool
    {
        return in_array($this, [self::Formaliste, self::Notaire, self::Administrateur]);
    }
}
