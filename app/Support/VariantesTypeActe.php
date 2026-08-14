<?php

namespace App\Support;

use App\Contracts\VarianteTypeActe;
use App\Enums\TypeModificationStatutaire;

/**
 * Registre des types d'actes qui se déclinent en variantes.
 *
 * **Seul endroit à modifier** pour déclarer une nouvelle catégorie déclinée : on associe un code de
 * type d'acte à l'enum de ses variantes, et l'écran de configuration, le rattachement des gabarits
 * et la génération suivent sans code supplémentaire.
 *
 * Un type absent de cette table n'a pas de variantes — c'est le cas de toutes les créations, ventes,
 * baux et hypothèques, dont le comportement reste inchangé.
 */
final class VariantesTypeActe
{
    /** @var array<string, class-string<VarianteTypeActe>> code de type d'acte ⇒ enum de variantes */
    private const REGISTRE = [
        'SOC-MOD' => TypeModificationStatutaire::class,
    ];

    /** Ce type d'acte se décline-t-il ? */
    public static function existePour(?string $codeTypeActe): bool
    {
        return $codeTypeActe !== null && isset(self::REGISTRE[$codeTypeActe]);
    }

    /**
     * Variantes de ce type d'acte, dans leur ordre de déclaration.
     *
     * @return array<int, VarianteTypeActe>
     */
    public static function pour(?string $codeTypeActe): array
    {
        $enum = self::REGISTRE[$codeTypeActe] ?? null;

        return $enum ? $enum::cases() : [];
    }

    /**
     * Variantes prêtes pour l'affichage.
     *
     * @return array<int, array{valeur: string, label: string}>
     */
    public static function options(?string $codeTypeActe): array
    {
        return array_map(
            fn (VarianteTypeActe $v) => ['valeur' => $v->valeur(), 'label' => $v->label()],
            self::pour($codeTypeActe),
        );
    }

    /** Retrouve une variante depuis sa valeur technique — `null` si elle n'appartient pas au type. */
    public static function resoudre(?string $codeTypeActe, ?string $valeur): ?VarianteTypeActe
    {
        if ($valeur === null) {
            return null;
        }

        foreach (self::pour($codeTypeActe) as $variante) {
            if ($variante->valeur() === $valeur) {
                return $variante;
            }
        }

        return null;
    }

    /** Valeurs techniques valides pour ce type d'acte — sert aux règles de validation. */
    public static function valeurs(?string $codeTypeActe): array
    {
        return array_map(fn (VarianteTypeActe $v) => $v->valeur(), self::pour($codeTypeActe));
    }
}
