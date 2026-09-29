<?php

namespace App\Support;

use App\Contracts\VarianteTypeActe;
use App\Enums\TypeModificationStatutaire;
use App\Enums\VarianteDissolution;
use App\Models\Dossier;

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
        'SOC-DIS' => VarianteDissolution::class,
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

    /**
     * Variantes **décidées par ce dossier**, lues dans son questionnaire.
     *
     * Lecteur unique. Avant le 2026-09-28, chacun des cinq appelants portait sa propre copie de
     * `donnees['modif.types'] ?? donnees['modif.type']`, et deux d'entre eux y ajoutaient un
     * `!== 'SOC-MOD'` codé en dur : déclarer une seconde catégorie déclinée demandait de
     * retrouver sept endroits, dont aucun n'échouait si on en oubliait un.
     *
     * Rend `[]` pour un type d'acte sans variantes — créations, ventes, baux, hypothèques —
     * dont le comportement reste exactement celui d'avant.
     *
     * @return array<int, VarianteTypeActe>
     */
    public static function duDossier(?Dossier $dossier): array
    {
        $enum = self::REGISTRE[$dossier?->typeActe?->code] ?? null;

        if (!$enum) {
            return [];
        }

        $dossier->loadMissing('questionnaire');
        $donnees = $dossier->questionnaire?->donnees ?? [];

        foreach ($enum::clesQuestionnaire() as $cle) {
            if (array_key_exists($cle, $donnees) && filled($donnees[$cle])) {
                return $enum::depuisDonnees($donnees[$cle]);
            }
        }

        return [];
    }

    /**
     * Valeurs techniques des variantes du dossier — forme attendue par la génération d'actes.
     *
     * @return array<int, string>
     */
    public static function valeursDuDossier(?Dossier $dossier): array
    {
        return array_map(fn (VarianteTypeActe $v) => $v->valeur(), self::duDossier($dossier));
    }

    /**
     * Documents imposés par une référence écrite pour ce dossier — `null` s'il n'en existe pas.
     *
     * **Union** des exigences de chaque variante décidée : trois résolutions d'une même
     * assemblée ne donnent qu'un seul procès-verbal.
     *
     * Rend `null` dès qu'**aucune** variante retenue ne déclare de référence — le cas de la
     * dissolution, dont le compte rendu de juillet 2026 ne parle pas. `null` ne signifie pas
     * « aucun document » mais « aucune règle écrite ne dit lesquels » : l'appelant retombe alors
     * sur les gabarits rattachés.
     *
     * @return array<string, string>|null
     */
    public static function documentsReferencePour(?Dossier $dossier): ?array
    {
        $variantes = self::duDossier($dossier);
        $documents = null;

        foreach ($variantes as $variante) {
            $reference = $variante->documentsReference();

            if ($reference === null) {
                continue;
            }

            $documents = [...($documents ?? []), ...$reference];
        }

        return $documents;
    }
}
