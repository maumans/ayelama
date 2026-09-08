<?php

namespace App\Support;

class Normalisation
{
    /**
     * Forme comparable d'un libellé saisi à la main : minuscules, espaces réduits, accents retirés.
     *
     * Extraite de `Societe::normaliserDenomination()` (règle 4 — unicité de la dénomination) le jour
     * où le référentiel de lieux a eu besoin de la même comparaison. Deux détenteurs auraient
     * divergé, et « Forécariah » cesserait alors d'être reconnu comme « Forecariah ».
     *
     * ⚠️ Les **accents sont retirés ici** alors que `normaliserDenomination()` les gardait : sur des
     * noms de lieux, « Forécariah » et « Forecariah » désignent le même endroit et doivent se
     * rapprocher. `Societe::normaliserDenomination()` délègue désormais à cette méthode — le
     * comportement s'en trouve légèrement élargi, ce qui va dans le sens de la règle 4 (moins de
     * doublons acceptés), jamais dans l'autre.
     */
    public static function comparable(mixed $valeur): string
    {
        $texte = mb_strtolower(trim((string) $valeur));

        // translittération ASCII : « é » → « e », « ô » → « o »… `iconv` échoue sur certains
        // caractères selon la locale, d'où le repli sur la chaîne d'origine.
        $sansAccents = @iconv('UTF-8', 'ASCII//TRANSLIT', $texte);
        if ($sansAccents !== false) {
            // `//TRANSLIT` produit parfois des formes composées (« e' », « ~n ») selon la
            // plateforme : on ne garde que lettres, chiffres et espaces.
            $texte = preg_replace('/[^a-z0-9 ]/', '', mb_strtolower($sansAccents));
        }

        return preg_replace('/\s+/u', ' ', trim($texte));
    }
}
