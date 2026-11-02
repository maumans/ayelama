<?php

namespace App\Enums;

/**
 * Le type d'assemblée qu'une opération sur une société exige.
 *
 * Addendum v1.2 § 2 : une **AGO** décide de ce qui ne touche pas aux statuts (nommer un gérant
 * non statutaire, consentir une hypothèque) ; une **AGE** modifie les statuts ou prononce la
 * dissolution ; l'**assemblée de clôture** approuve les comptes du liquidateur.
 *
 * ⚠️ **Déduit, et non plus saisi.** Jusqu'ici `ag.type` était un `select` libre que le clerc
 * remplissait à la main : rien ne garantissait qu'une cession de parts — qui modifie les
 * statuts — soit bien tenue en AGE. Le type se déduit désormais des opérations choisies
 * (règle C2 de la spec : AGE si au moins une opération l'exige, sinon AGO).
 */
enum TypeAssemblee: string
{
    case Ago       = 'AGO';
    case Age       = 'AGE';
    case AgCloture = 'AG_CLOTURE';

    public function label(): string
    {
        return match ($this) {
            self::Ago       => 'Assemblée générale ordinaire',
            self::Age       => 'Assemblée générale extraordinaire',
            self::AgCloture => 'Assemblée de clôture de liquidation',
        };
    }

    public function abrege(): string
    {
        return match ($this) {
            self::Ago       => 'AGO',
            self::Age       => 'AGE',
            self::AgCloture => 'AG de clôture',
        };
    }

    /**
     * L'assemblée d'un dossier portant plusieurs opérations — règle C2.
     *
     * ⚠️ **La clôture l'emporte sur tout**, et c'est sans conséquence : `CLOTURE_LIQUIDATION`
     * est exclusive, elle ne se combine avec rien. L'ordre est posé pour que la règle reste
     * vraie si l'exclusivité changeait un jour — une règle qui ne tient que grâce à une autre
     * est une règle fragile.
     *
     * @param  array<int, self> $assemblees
     */
    public static function laPlusExigeante(array $assemblees): ?self
    {
        foreach ([self::AgCloture, self::Age, self::Ago] as $candidate) {
            if (in_array($candidate, $assemblees, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /** @return array<int, array{valeur: string, label: string}> */
    public static function toutes(): array
    {
        return array_map(
            fn (self $t) => ['valeur' => $t->value, 'label' => $t->label()],
            self::cases(),
        );
    }
}
