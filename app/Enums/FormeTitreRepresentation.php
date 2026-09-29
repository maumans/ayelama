<?php

namespace App\Enums;

/**
 * Sous quelle forme le titre qui habilite le représentant a été établi.
 *
 * Elle commande la **rédaction de la comparution** : une procuration reçue par un confrère
 * se vise « dont une expédition demeurera annexée », une procuration sous seing privé
 * « laquelle demeurera annexée ». Voir MentionComparutionService.
 *
 * ⚠️ Ces quatre formes couvrent la pratique ouest-africaine, mais **la règle guinéenne n'est
 * pas arbitrée** : le parallélisme des formes voudrait qu'une procuration servant un acte
 * authentique soit elle-même authentique ou à signature légalisée, et l'APIP attend en général
 * une procuration *spéciale* pour une constitution de société. Aucune de ces exigences n'est
 * imposée ici — une contrainte inventée serait pire qu'une contrainte absente. Le jour où
 * l'étude tranchera, la règle se posera dans ReglesRepresentationService, à un seul endroit.
 */
enum FormeTitreRepresentation: string
{
    case SousSeingPrive = 'sous_seing_prive';
    case Notariee       = 'notariee';
    case Legalisee      = 'legalisee';
    /** Établie par un poste consulaire — le cas courant quand le mandant vit à l'étranger. */
    case Consulaire     = 'consulaire';

    public function label(): string
    {
        return match ($this) {
            self::SousSeingPrive => 'Sous seing privé',
            self::Notariee       => 'Notariée',
            self::Legalisee      => 'Sous seing privé, signature légalisée',
            self::Consulaire     => 'Consulaire',
        };
    }

    /**
     * L'autorité qui l'a établie doit-elle être nommée dans l'acte ?
     *
     * Une procuration sous seing privé n'en a aucune ; les trois autres se visent par
     * l'autorité qui les a reçues ou légalisées.
     */
    public function nommeUneAutorite(): bool
    {
        return match ($this) {
            self::SousSeingPrive => false,
            self::Notariee, self::Legalisee, self::Consulaire => true,
        };
    }

    /** @return array<int, array{value: string, label: string}> Pour le frontend. */
    public static function toutes(): array
    {
        return array_map(
            fn (self $f) => ['value' => $f->value, 'label' => $f->label()],
            self::cases(),
        );
    }
}
