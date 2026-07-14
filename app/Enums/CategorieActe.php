<?php

namespace App\Enums;

enum CategorieActe: string
{
    case Societe    = 'societe';
    case Vente      = 'vente';
    case Hypotheque = 'hypotheque';
    case Bail       = 'bail';
    case Donation   = 'donation';
    case Succession = 'succession';
    case Mariage    = 'mariage';
    case Testament  = 'testament';
    case Procuration = 'procuration';
    case PriseEnCharge = 'prise_en_charge';

    public function label(): string
    {
        return match($this) {
            self::Societe      => 'Société',
            self::Vente        => 'Vente d\'immeubles',
            self::Hypotheque   => 'Contrat d\'hypothèque',
            self::Bail         => 'Bail',
            self::Donation     => 'Donation',
            self::Succession   => 'Succession',
            self::Mariage      => 'Contrat de mariage',
            self::Testament    => 'Testament',
            self::Procuration  => 'Procuration',
            self::PriseEnCharge => 'Prise en charge',
        };
    }

    public function prefixeReference(): string
    {
        return match($this) {
            self::Societe      => 'SOC',
            self::Vente        => 'VEN',
            self::Hypotheque   => 'HYP',
            self::Bail         => 'BAI',
            self::Donation     => 'DON',
            self::Succession   => 'SUC',
            self::Mariage      => 'MAR',
            self::Testament    => 'TES',
            self::Procuration  => 'PRO',
            self::PriseEnCharge => 'PEC',
        };
    }
}
