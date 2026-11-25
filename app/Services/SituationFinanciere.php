<?php

namespace App\Services;

use App\Enums\NatureLigne;
use App\Models\Decaissement;
use App\Models\Dossier;
use App\Models\Facture;

/**
 * La situation financière d'un dossier — le cœur de la comptabilité des dossiers (2026-11-23).
 *
 * Elle répond aux questions que l'étude se pose sur chaque dossier, et à elles seules (la
 * comptabilité générale n'est pas demandée) :
 *
 *  - qu'a-t-on facturé, et qu'est-ce qui revient à l'étude (honoraires) ou aux organismes (débours) ;
 *  - qu'a encaissé le client, et que doit-il encore ;
 *  - qu'a-t-on déjà versé aux organismes, et que reste-t-il à leur verser ;
 *  - combien l'étude **détient** pour ce client (encaissé − versé), ou combien elle lui **avance**.
 *
 * ⚠️ **Seules les factures émises comptent comme facturé.** Une proforma n'est pas due : elle est
 * montrée à part (`enProforma`). Les paiements, eux, ne peuvent porter que sur une facture émise.
 *
 * Calcul pur sur les relations chargées : aucune écriture, et le même pour la fiche dossier et la
 * vue d'ensemble — deux calculs finiraient par diverger.
 */
final class SituationFinanciere
{
    /** Relations à charger pour calculer sans requête par dossier. */
    public const RELATIONS = ['factures.lignes', 'factures.paiements', 'decaissements'];

    /** @return array<string, mixed> */
    public static function pour(Dossier $dossier): array
    {
        $dossier->loadMissing(self::RELATIONS);

        $emises    = $dossier->factures->reject(fn (Facture $f) => $f->estProforma());
        $proformas = $dossier->factures->filter(fn (Facture $f) => $f->estProforma());

        $honoraires     = round($emises->sum(fn (Facture $f) => $f->totalParNature(NatureLigne::Honoraire)), 2);
        $deboursFactures = round($emises->sum(fn (Facture $f) => $f->totalParNature(NatureLigne::Debours)), 2);
        $totalFacture   = round($emises->sum(fn (Facture $f) => (float) $f->total_chiffres), 2);
        $encaisse       = round($dossier->factures->sum(fn (Facture $f) => $f->totalPaye()), 2);
        $deboursVerses  = round($dossier->decaissements->sum(fn (Decaissement $d) => (float) $d->montant), 2);

        // Ce que l'étude tient en caisse pour ce client : positif, une provision ; négatif, une
        // avance de sa propre trésorerie.
        $solde = round($encaisse - $deboursVerses, 2);

        $situation = [
            'honorairesFactures'    => $honoraires,
            'deboursFactures'       => $deboursFactures,
            'totalFacture'          => $totalFacture,
            'enProforma'            => round($proformas->sum(fn (Facture $f) => (float) $f->total_chiffres), 2),
            'encaisse'              => $encaisse,
            'resteDu'               => max(0.0, round($totalFacture - $encaisse, 2)),
            'deboursVerses'         => $deboursVerses,
            'deboursAVerser'        => max(0.0, round($deboursFactures - $deboursVerses, 2)),
            'versesNonFactures'     => max(0.0, round($deboursVerses - $deboursFactures, 2)),
            'provision'             => max(0.0, $solde),
            'avance'                => max(0.0, -$solde),
        ];

        $situation['alertes'] = self::alertes($situation);

        return $situation;
    }

    /**
     * Ce qui mérite d'être vu — dit, jamais opposé : aucune de ces situations n'est interdite
     * (l'étude avance parfois des frais), mais aucune ne doit passer inaperçue.
     *
     * @param  array<string, float>  $s
     * @return list<string>
     */
    private static function alertes(array $s): array
    {
        $fmt = fn (float $n) => \App\Support\FormatNombre::milliers(round($n));
        $alertes = [];

        if ($s['avance'] > 0) {
            $alertes[] = "L'étude avance {$fmt($s['avance'])} GNF : elle a versé aux organismes plus qu'elle n'a encaissé du client.";
        }
        if ($s['versesNonFactures'] > 0) {
            $alertes[] = "{$fmt($s['versesNonFactures'])} GNF versés aux organismes ne figurent sur aucune facture émise : à refacturer au client.";
        }

        return $alertes;
    }
}
