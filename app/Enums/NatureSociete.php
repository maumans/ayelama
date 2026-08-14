<?php

namespace App\Enums;

/**
 * Classification des sociétés (règle 3 du CR de juillet 2026).
 *
 * Détermine le régime de responsabilité des associés — et, par ricochet, la capacité
 * juridique exigée d'eux : la majorité est requise pour être associé d'une société de
 * personnes, pas d'une société de capitaux (règle 7).
 */
enum NatureSociete: string
{
    case Capitaux   = 'capitaux';
    case Personnes  = 'personnes';
    /**
     * Le GIE n'est ni l'un ni l'autre : groupement d'intérêt économique, il peut être
     * constitué sans capital. Le forcer dans « capitaux » ou « personnes » aurait fait
     * appliquer des règles qui ne le concernent pas.
     */
    case Groupement = 'groupement';

    public function label(): string
    {
        return match($this) {
            self::Capitaux   => 'Société de capitaux',
            self::Personnes  => 'Société de personnes',
            self::Groupement => 'Groupement',
        };
    }

    public function responsabilite(): string
    {
        return match($this) {
            self::Capitaux   => 'Responsabilité limitée aux apports',
            self::Personnes  => 'Responsabilité illimitée, solidaire et indéfinie',
            self::Groupement => 'Responsabilité définie par le contrat de groupement',
        };
    }

    /**
     * La majorité est-elle requise pour être associé ?
     *
     * Règle 7 : aucun âge minimum en société de capitaux (un mineur peut y être associé,
     * représenté par son tuteur), majorité requise en société de personnes — la
     * responsabilité y étant illimitée et solidaire, elle ne peut engager un mineur.
     */
    public function exigeMajoritePourEtreAssocie(): bool
    {
        return match($this) {
            self::Personnes  => true,
            self::Capitaux,
            self::Groupement => false,
        };
    }
}
