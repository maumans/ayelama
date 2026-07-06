<?php

namespace App\Services;

class NombreEnLettres
{
    private static $unites = [
        0 => 'zéro', 1 => 'un', 2 => 'deux', 3 => 'trois', 4 => 'quatre',
        5 => 'cinq', 6 => 'six', 7 => 'sept', 8 => 'huit', 9 => 'neuf',
        10 => 'dix', 11 => 'onze', 12 => 'douze', 13 => 'treize',
        14 => 'quatorze', 15 => 'quinze', 16 => 'seize', 17 => 'dix-sept',
        18 => 'dix-huit', 19 => 'dix-neuf'
    ];

    private static $dizaines = [
        20 => 'vingt', 30 => 'trente', 40 => 'quarante', 50 => 'cinquante',
        60 => 'soixante', 70 => 'soixante-dix', 80 => 'quatre-vingt', 90 => 'quatre-vingt-dix'
    ];

    public static function convertir(float $montant, string $devise = 'Francs Guinéens'): string
    {
        if ($montant < 0) {
            return 'moins ' . self::convertir(abs($montant), $devise);
        }
        
        $entier = (int) floor($montant);
        $decimal = (int) round(($montant - $entier) * 100);
        
        $resultat = self::convertirEntier($entier);
        
        if ($devise !== '') {
            $resultat .= ' ' . $devise;
        }

        if ($decimal > 0) {
            $resultat .= ' et ' . self::convertirEntier($decimal) . ' centimes'; // A ajuster selon devise (FG n'a pas de centimes en général)
        }

        return mb_strtoupper($resultat, 'UTF-8');
    }

    private static function convertirEntier(int $n): string
    {
        if ($n < 20) {
            return self::$unites[$n];
        }
        
        if ($n < 100) {
            $d = (int) floor($n / 10) * 10;
            $u = $n % 10;
            
            if ($d == 70 || $d == 90) {
                $d -= 10;
                $u += 10;
            }
            
            $res = self::$dizaines[$d];
            if ($u > 0) {
                $liaison = ($u == 1 || $u == 11) ? ' et ' : '-';
                if ($d == 80) $liaison = '-'; // quatre-vingt-un
                $res .= $liaison . self::$unites[$u];
            } else if ($d == 80) {
                $res .= 's'; // quatre-vingts
            }
            return $res;
        }
        
        if ($n < 1000) {
            $c = (int) floor($n / 100);
            $r = $n % 100;
            $res = ($c == 1) ? 'cent' : self::$unites[$c] . ' cent';
            if ($r == 0 && $c > 1) $res .= 's';
            if ($r > 0) $res .= ' ' . self::convertirEntier($r);
            return $res;
        }
        
        if ($n < 1000000) {
            $m = (int) floor($n / 1000);
            $r = $n % 1000;
            $res = ($m == 1) ? 'mille' : self::convertirEntier($m) . ' mille';
            if ($r > 0) $res .= ' ' . self::convertirEntier($r);
            return $res;
        }
        
        if ($n < 1000000000) {
            $m = (int) floor($n / 1000000);
            $r = $n % 1000000;
            $res = self::convertirEntier($m) . ' million';
            if ($m > 1) $res .= 's';
            if ($r > 0) $res .= ' ' . self::convertirEntier($r);
            return $res;
        }

        if ($n < 1000000000000) {
            $m = (int) floor($n / 1000000000);
            $r = $n % 1000000000;
            $res = self::convertirEntier($m) . ' milliard';
            if ($m > 1) $res .= 's';
            if ($r > 0) $res .= ' ' . self::convertirEntier($r);
            return $res;
        }
        
        return (string) $n; // Fallback pour très grands nombres
    }
}
