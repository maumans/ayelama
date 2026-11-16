<?php

namespace App\Support;

/**
 * Les nombres **dans les documents** : séparateur de milliers « . » (retours R13 et R16,
 * arbitrés par l'étude le 2026-11-16 — « 10.000.000 », partout).
 *
 * Avant ce helper, trois écritures coexistaient : l'espace fine insécable des actes
 * (`_formate`), l'espace ordinaire de la facture, du reçu et de la fiche de recueil, et **aucun**
 * séparateur sur les balises brutes des actes — `${soc.capital_chiffres}` (17 occurrences dans
 * les modèles actifs) et `${soc.nombre_parts}` (12) sortaient « 50000000 ».
 *
 * Les **écrans** gardent `toLocaleString('fr-FR')` : la demande porte sur les documents.
 */
final class FormatNombre
{
    /**
     * Clés de questionnaire qui portent un montant ou un compte sans finir par `_chiffres`.
     * Lues par leur **dernier segment** : `soc.nombre_parts` comme le champ `prix_cession` d'une
     * ligne de bloc répétable. Liste fermée, déclarée ici seulement — relevée dans
     * questionnaires.js le 2026-11-16. Une année ou un téléphone n'y figure pas, et aucune clé
     * `_chiffres` n'en porte.
     */
    public const CLES_EN_MILLIERS = [
        'nombre_parts', 'nombre_actions', 'superficie', 'avance_loyer',
        'augmentation_montant', 'diminution_montant', 'montant_souscrit', 'prix_cession', 'prix_paye',
    ];

    /** 50000000 → « 50.000.000 » ; 1500,5 → « 1.500,5 ». Rend la valeur telle quelle si ce n'est pas un nombre. */
    public static function milliers(mixed $valeur): string
    {
        $texte = trim((string) $valeur);
        $compact = preg_replace('/[\s\x{202F}\x{00A0}]/u', '', $texte);

        // Déjà écrit avec des points de milliers (« 1.000.000 ») : le lire comme 1,0 serait une
        // perte silencieuse. Mesuré le 2026-11-16 : les 108 valeurs numériques en base sont des
        // entiers nus — garde-fou, pas correctif.
        if (preg_match('/^\d{1,3}(\.\d{3})+$/', $compact)) {
            $compact = str_replace('.', '', $compact);
        }

        $normalise = str_replace(',', '.', $compact);

        if (! is_numeric($normalise)) {
            return $texte;
        }

        $decimales = str_contains($normalise, '.') ? strlen(rtrim(substr(strrchr($normalise, '.'), 1), '0')) : 0;

        return number_format((float) $normalise, min($decimales, 2), ',', '.');
    }

    /** Cette clé (ou ce champ de bloc) porte-t-elle un nombre à écrire avec séparateur ? */
    public static function doitFormater(string $cle): bool
    {
        if (str_ends_with($cle, '_chiffres')) {
            return true;
        }

        $dernier = str_contains($cle, '.') ? substr($cle, strrpos($cle, '.') + 1) : $cle;

        return in_array($dernier, self::CLES_EN_MILLIERS, true);
    }
}
