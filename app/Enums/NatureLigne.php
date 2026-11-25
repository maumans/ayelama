<?php

namespace App\Enums;

/**
 * Ce qu'une ligne de facture représente pour l'étude — la distinction qui fonde la comptabilité
 * des dossiers (2026-11-23).
 *
 *  - **honoraire** : la rémunération de l'étude. C'est son produit ; elle peut le remiser.
 *  - **débours** : l'argent qu'elle avance ou reverse pour le client — impôts, greffe, APIP,
 *    conservation foncière, journal d'annonces, timbres, rôles. Elle ne le garde pas.
 *
 * Jusqu'ici la distinction ne se lisait que par `baremes.organisme = 'Notaire'`, et pas du tout
 * sur les 26 lignes saisies à la main (toutes des débours, mesuré le 2026-11-23 : JAL, RCCM,
 * DNSV, « Debours »). Elle est désormais portée par le barème **et copiée sur la ligne** : une
 * facture émise garde la nature du jour où elle a été établie.
 */
enum NatureLigne: string
{
    case Honoraire = 'honoraire';
    case Debours   = 'debours';

    public function label(): string
    {
        return match ($this) {
            self::Honoraire => 'Honoraire',
            self::Debours   => 'Débours',
        };
    }

    /** Un honoraire se remise (geste commercial) ; un débours non (argent réel reversé). */
    public function remisableParDefaut(): bool
    {
        return match ($this) {
            self::Honoraire => true,
            self::Debours   => false,
        };
    }

    /** La nature d'un barème qui n'en déclare pas : l'organisme « Notaire » est l'étude elle-même. */
    public static function pourOrganisme(?string $organisme): self
    {
        return $organisme === 'Notaire' ? self::Honoraire : self::Debours;
    }

    /** @return list<array{valeur: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['valeur' => $c->value, 'label' => $c->label()], self::cases());
    }
}
