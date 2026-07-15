<?php

namespace App\Enums;

enum RoleUtilisateur: string
{
    case Clerc        = 'clerc';
    case Reviseur     = 'reviseur';
    case Notaire      = 'notaire';
    case Formaliste   = 'formaliste';
    case Comptable    = 'comptable';
    case Administrateur = 'administrateur';

    public function label(): string
    {
        return match($this) {
            self::Clerc          => 'Clerc / Rédacteur',
            self::Reviseur       => 'Réviseur / Responsable',
            self::Notaire        => 'Notaire (Maître)',
            self::Formaliste     => 'Formaliste',
            self::Comptable      => 'Comptable',
            self::Administrateur => 'Administrateur',
        };
    }

    /** @return self[] */
    public static function peuventOuvrir(): array
    {
        return [self::Clerc, self::Notaire, self::Administrateur];
    }

    /** @return self[] */
    public static function peuventReviser(): array
    {
        return [self::Reviseur, self::Notaire, self::Administrateur];
    }

    /** @return self[] */
    public static function peuventSigner(): array
    {
        return [self::Notaire, self::Administrateur];
    }

    /** @return self[] */
    public static function peuventGererFormalites(): array
    {
        return [self::Formaliste, self::Notaire, self::Administrateur];
    }

    /** @return self[] */
    public static function peuventGererFacturation(): array
    {
        return [self::Comptable, self::Notaire, self::Administrateur];
    }
}
