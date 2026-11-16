<?php

namespace App\Support;

use App\Services\NombreEnLettres;

/**
 * Une heure de questionnaire — `ag.heure` d'abord (retour R02, 2026-11-16).
 *
 * Le champ était un texte libre ; mesuré le jour du passage au type `time` : « 10H », « 10h00 »,
 * « 10H30 », « 10h30 », « 10H00 » sur six PV. Le questionnaire est un **instantané** : ces
 * valeurs ne sont pas réécrites en base, elles sont **lues** sous leur forme canonique `HH:MM`,
 * ici pour les actes et par `resources/js/lib/heures.js` pour l'écran — même expression, mêmes
 * bornes, `HeureTest` et le contrôle de l'écran portent sur les mêmes exemples.
 */
final class Heure
{
    /** « 10H », « 10 h 30 », « 10h30 », « 10:30 », « 10 heures 30 » → « 10:30 » ; null sinon. */
    public static function normaliser(mixed $valeur): ?string
    {
        if (! is_string($valeur) && ! is_int($valeur)) {
            return null;
        }

        if (! preg_match('/^\s*(\d{1,2})\s*(?:h|heures?|:)\s*(\d{2})?\s*(?:min)?\s*$/iu', (string) $valeur, $m)) {
            return null;
        }

        $heures  = (int) $m[1];
        $minutes = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;

        if ($heures > 23 || $minutes > 59) {
            return null;
        }

        return sprintf('%02d:%02d', $heures, $minutes);
    }

    /** « 10:30 » → « 10 h 30 », « 10:00 » → « 10 h ». */
    public static function formatee(mixed $valeur): ?string
    {
        $h = self::normaliser($valeur);
        if ($h === null) return null;

        [$heures, $minutes] = array_map('intval', explode(':', $h));

        return $minutes === 0 ? "{$heures} h" : sprintf('%d h %02d', $heures, $minutes);
    }

    /**
     * « 10:30 » → « DIX HEURES TRENTE » ; « 01:00 » → « UNE HEURE ». Majuscules, comme les autres
     * `_lettres` des actes (`NombreEnLettres`).
     *
     * Heure et minute sont du féminin : « une heure », « vingt et une heures », « une heure une ».
     */
    public static function enLettres(mixed $valeur): ?string
    {
        $h = self::normaliser($valeur);
        if ($h === null) return null;

        [$heures, $minutes] = array_map('intval', explode(':', $h));

        $texte = self::feminin(NombreEnLettres::convertir((float) $heures, ''))
            . ($heures <= 1 ? ' HEURE' : ' HEURES');

        if ($minutes > 0) {
            $texte .= ' ' . self::feminin(NombreEnLettres::convertir((float) $minutes, ''));
        }

        return $texte;
    }

    private static function feminin(string $nombre): string
    {
        return preg_replace('/\bUN$/u', 'UNE', trim($nombre));
    }
}
