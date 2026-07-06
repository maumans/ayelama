<?php

namespace App\Enums;

enum EtapeDossier: string
{
    case Initialisation = 'initialisation';
    case Edition        = 'edition';
    case Revision       = 'revision';
    case Formalites     = 'formalites';
    case Expedition     = 'expedition';
    case Cloture        = 'cloture';

    public function label(): string
    {
        return match($this) {
            self::Initialisation => 'Initialisation',
            self::Edition        => 'Édition actes',
            self::Revision       => 'Révision',
            self::Formalites     => 'Formalités',
            self::Expedition     => 'Expédition',
            self::Cloture        => 'Clôturé',
        };
    }

    public function suivante(): ?self
    {
        return match($this) {
            self::Initialisation => self::Edition,
            self::Edition        => self::Revision,
            self::Revision       => self::Formalites,
            self::Formalites     => self::Expedition,
            self::Expedition     => self::Cloture,
            self::Cloture        => null,
        };
    }

    public function precedente(): ?self
    {
        return match($this) {
            self::Initialisation => null,
            self::Edition        => self::Initialisation,
            self::Revision       => self::Edition,
            self::Formalites     => self::Revision,
            self::Expedition     => self::Formalites,
            self::Cloture        => self::Expedition,
        };
    }

    public function ordre(): int
    {
        return match($this) {
            self::Initialisation => 0,
            self::Edition        => 1,
            self::Revision       => 2,
            self::Formalites     => 3,
            self::Expedition     => 4,
            self::Cloture        => 5,
        };
    }

    public static function ordered(): array
    {
        return [
            self::Initialisation,
            self::Edition,
            self::Revision,
            self::Formalites,
            self::Expedition,
            self::Cloture,
        ];
    }
}
