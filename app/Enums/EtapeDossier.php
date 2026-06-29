<?php

namespace App\Enums;

enum EtapeDossier: string
{
    case Initialisation    = 'initialisation';
    case Edition           = 'edition';
    case Revision          = 'revision';
    case SignatureClient   = 'signature_client';
    case SignatureNotaire  = 'signature_notaire';
    case Formalites        = 'formalites';
    case Expedition        = 'expedition';
    case Cloture           = 'cloture';

    public function label(): string
    {
        return match($this) {
            self::Initialisation   => 'Initialisation',
            self::Edition          => 'Édition actes',
            self::Revision         => 'Révision',
            self::SignatureClient  => 'Signature client',
            self::SignatureNotaire => 'Signature notaire',
            self::Formalites       => 'Formalités',
            self::Expedition       => 'Expédition',
            self::Cloture          => 'Clôturé',
        };
    }

    public function suivante(): ?self
    {
        return match($this) {
            self::Initialisation   => self::Edition,
            self::Edition          => self::Revision,
            self::Revision         => self::SignatureClient,
            self::SignatureClient  => self::SignatureNotaire,
            self::SignatureNotaire => self::Formalites,
            self::Formalites       => self::Expedition,
            self::Expedition       => self::Cloture,
            self::Cloture          => null,
        };
    }

    public function precedente(): ?self
    {
        return match($this) {
            self::Initialisation   => null,
            self::Edition          => self::Initialisation,
            self::Revision         => self::Edition,
            self::SignatureClient  => self::Revision,
            self::SignatureNotaire => self::SignatureClient,
            self::Formalites       => self::SignatureNotaire,
            self::Expedition       => self::Formalites,
            self::Cloture          => self::Expedition,
        };
    }

    public function ordre(): int
    {
        return match($this) {
            self::Initialisation   => 0,
            self::Edition          => 1,
            self::Revision         => 2,
            self::SignatureClient  => 3,
            self::SignatureNotaire => 4,
            self::Formalites       => 5,
            self::Expedition       => 6,
            self::Cloture          => 7,
        };
    }

    public static function ordered(): array
    {
        return [
            self::Initialisation,
            self::Edition,
            self::Revision,
            self::SignatureClient,
            self::SignatureNotaire,
            self::Formalites,
            self::Expedition,
            self::Cloture,
        ];
    }
}
