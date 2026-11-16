<?php

namespace App\Support;

/**
 * L'empreinte du contenu d'un questionnaire — ce sur quoi porte l'accord du client (R23).
 *
 * SHA-256 d'une forme **canonique** : clés triées à tous les niveaux, sans dépendre de l'ordre
 * dans lequel l'écran a envoyé les champs. Deux questionnaires identiques ont la même empreinte ;
 * le moindre changement de valeur la change, et rend l'accord caduc.
 *
 * Les listes (blocs répétables) gardent leur ordre : déplacer un associé change le contenu.
 */
final class EmpreinteQuestionnaire
{
    public static function calculer(?array $donnees): string
    {
        return hash('sha256', json_encode(
            self::canonique($donnees ?? []),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        ));
    }

    private static function canonique(mixed $valeur): mixed
    {
        if (! is_array($valeur)) {
            return $valeur;
        }
        if (! array_is_list($valeur)) {
            ksort($valeur, SORT_STRING);
        }

        return array_map(self::canonique(...), $valeur);
    }
}
