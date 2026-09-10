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

    /**
     * Convertit en ISO (`AAAA-MM-JJ`) les valeurs reçues au format français `JJ/MM/AAAA`, et ne
     * renvoie **que** les champs effectivement convertis — de quoi les fusionner dans une requête
     * sans écraser ce qui était déjà au bon format.
     *
     * Cette conversion doit précéder la validation : ni la règle `date` ni le cast Eloquent
     * n'interviennent à temps, et PHP lit `JJ/MM/AAAA` comme du **mois/jour américain** —
     *   - `13/05/1985` → `strtotime` échoue → une date valide est refusée ;
     *   - `01/04/1985` → devient le **4 janvier**, enregistré sans la moindre erreur.
     *
     * La règle vit ici, et non dans chaque contrôleur : deux détenteurs auraient divergé, et c'est
     * précisément la divergence de format qui a produit le défaut.
     *
     * @param  array<string, mixed>  $valeurs
     * @param  list<string>          $champs
     * @return array<string, string>
     */
    public static function datesEnISO(array $valeurs, array $champs): array
    {
        $converties = [];

        foreach ($champs as $champ) {
            $valeur = $valeurs[$champ] ?? null;

            if (is_string($valeur) && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', trim($valeur), $p)) {
                $converties[$champ] = "{$p[3]}-{$p[2]}-{$p[1]}";
            }
        }

        return $converties;
    }
}
