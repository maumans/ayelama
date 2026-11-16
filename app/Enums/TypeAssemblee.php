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

    /**
     * Libellé de la nature de décision d'une société **unipersonnelle** : il n'y a pas
     * d'assemblée, l'associé unique décide seul. Option de `ag.type` dans questionnaires.js.
     */
    public const LIBELLE_ASSOCIE_UNIQUE = "Décision de l'associé unique";

    /**
     * Nature de la décision qu'un PV de modification devrait porter (retour R05, 2026-11-16).
     *
     * Arbitré par l'étude : une forme unipersonnelle (SARLU, SASU, SAU) donne « Décision de
     * l'associé unique » ; sinon l'opération la plus exigeante tranche — AGE dès qu'une opération
     * touche aux statuts, AGO sinon (règle C2). Mesuré avant : 6 PV de SARLU sur 6 portaient
     * « AGE » ou « AGO », et SOC-2026-0024 une AGO pour un changement de gérant statutaire et une
     * augmentation de capital.
     *
     * L'assistant **préremplit** avec ce résultat, sans l'imposer : la valeur reste modifiable.
     *
     * @param  array<int, string> $typesChoisis libellés de `modif.types`
     */
    public static function natureDeduite(?string $forme, array $typesChoisis): ?string
    {
        $regles = self::reglesNatureDecision();

        if ($forme !== null && in_array($forme, $regles['formesAssocieUnique'], true)) {
            return $regles['libelles']['associe_unique'];
        }

        $assemblees = array_values(array_filter(array_map(
            fn (string $type) => isset($regles['assembleeParType'][$type]) ? self::from($regles['assembleeParType'][$type]) : null,
            $typesChoisis,
        )));

        $retenue = self::laPlusExigeante($assemblees);

        return $retenue ? $regles['libelles'][$retenue->value] : null;
    }

    /**
     * Ce que l'assistant lit pour préremplir `ag.type` — servi, jamais recomposé en JavaScript.
     * `natureDeduite()` compose à partir de **ces mêmes** données : un seul détenteur.
     *
     * @return array{libelles: array<string, string>, formesAssocieUnique: list<string>, assembleeParType: array<string, string>}
     */
    public static function reglesNatureDecision(): array
    {
        return [
            'libelles' => [
                self::Age->value  => self::Age->label(),
                self::Ago->value  => self::Ago->label(),
                'associe_unique'  => self::LIBELLE_ASSOCIE_UNIQUE,
            ],
            'formesAssocieUnique' => array_values(array_map(
                fn (FormeSociete $f) => $f->value,
                array_filter(FormeSociete::cases(), fn (FormeSociete $f) => $f->exigeAssocieUnique()),
            )),
            'assembleeParType' => collect(TypeModificationStatutaire::cases())
                ->mapWithKeys(fn (TypeModificationStatutaire $t) => [$t->label() => $t->assemblee()->value])
                ->all(),
        ];
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
