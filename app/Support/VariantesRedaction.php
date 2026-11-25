<?php

namespace App\Support;

use App\Services\NombreEnLettres;
use Illuminate\Support\Carbon;

/**
 * Ce que les actes **écrivent** mais que personne ne saisit : la civilité en toutes lettres,
 * l'accord au féminin, une date d'état civil rédigée, le nombre de parts en lettres, l'objet
 * social en liste, la numérotation des parts…
 *
 * Né le 2026-11-20 de la réception des modèles **finaux** de l'étude (`docs/ACTES/`, détail au
 * `docs/kit-modeles/06`). Jusque-là les gabarits étaient balisés sur des formulaires à
 * pointillés, et le masculin, « M. » ou la date en capitales passaient inaperçus. Confrontés à
 * deux dossiers réels, ils produisaient un acte visiblement **différent** de celui de l'étude :
 * « M. Ousman JALLOH … Né à … le QUINZE FÉVRIER MILLE NEUF CENT QUATRE-VINGT » là où l'étude
 * écrit « Monsieur Ousman JALLOH … Né à … le quinze Février mille neuf cent quatre-vingt ».
 *
 * **Toutes ces valeurs sont dérivées, aucune n'est un champ.** Les faire saisir revenait à
 * demander au clerc un accord grammatical ou une addition.
 *
 * ⚠️ **Ce qu'on ne sait pas, on ne l'écrit pas.** Une civilité vide (11 questionnaires de
 * société sur 28, mesuré le 2026-11-20) ne donne **ni** « Né » **ni** « Née » : la balise reste
 * vide, et `consignerChampsManquants()` le dit dans l'historique du dossier. Écrire le masculin
 * par défaut serait une supposition — exactement ce que les gabarits faisaient en dur.
 * Même règle pour le nom de famille : il n'est **jamais** découpé dans `prenom_nom` (l'ordre
 * des noms varie en Guinée, voir `BalisesGabaritsActifsTest::MANQUES_CONNUS`) ; seule la fiche
 * client liée le fournit.
 *
 * ⚠️ **Miroir assumé** : `tools/generer-balises-resolvables.mjs` déclare les mêmes noms. Une
 * variante ajoutée ici sans y être déclarée est refusée par `BalisesGabaritsActifsTest` dès
 * qu'un gabarit l'emploie — c'est voulu.
 */
final class VariantesRedaction
{
    private const FEMININ  = ['Mme', 'Mlle', 'Madame', 'Mademoiselle'];
    private const MASCULIN = ['M.', 'M', 'Monsieur'];

    private const CIVILITE_LONGUE = [
        'M.' => 'Monsieur', 'M' => 'Monsieur', 'Monsieur' => 'Monsieur',
        'Mme' => 'Madame', 'Madame' => 'Madame',
        'Mlle' => 'Mademoiselle', 'Mademoiselle' => 'Mademoiselle',
    ];

    private const MOIS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril', 5 => 'Mai', 6 => 'Juin',
        7 => 'Juillet', 8 => 'Août', 9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    /**
     * Les variantes de la racine du questionnaire, à **ajouter** aux données (jamais à
     * substituer : une valeur saisie l'emporte toujours).
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    public static function racine(array $donnees): array
    {
        $ajouts = [];

        // Personnes des sections scalaires : toute clé `préfixe.civilite`.
        foreach ($donnees as $cle => $valeur) {
            if (preg_match('/^([a-z_]+)\.civilite$/', $cle, $m) && is_string($valeur)) {
                foreach (self::personne($valeur) as $suffixe => $v) {
                    $ajouts["{$m[1]}.{$suffixe}"] = $v;
                }
            }
        }

        foreach (['nombre_parts', 'nombre_actions', 'premier_exercice_annee'] as $champ) {
            $valeur = $donnees["soc.{$champ}"] ?? null;
            if (is_numeric($valeur) && (float) $valeur > 0) {
                $ajouts["soc.{$champ}_lettres"] = NombreEnLettres::convertir((float) $valeur, '');
            }
        }

        $activites = self::activites($donnees['soc.objet_social'] ?? null);
        if ($activites !== []) {
            $ajouts['soc.activites'] = array_map(fn (string $a) => ['libelle' => $a], $activites);
            // En ligne (formulaire RCCM), seule la première activité prend la majuscule :
            // « L'import et l'export, l'industrie, le commerce général ».
            $ajouts['soc.objet_social_ligne'] = implode(', ', array_map(
                fn (string $a, int $i) => $i === 0 ? $a : mb_strtolower(mb_substr($a, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($a, 1, null, 'UTF-8'),
                $activites,
                array_keys($activites),
            ));
        }

        if (($souscripteurs = self::souscripteurs($donnees)) !== null) {
            $ajouts['soc.souscripteurs'] = $souscripteurs;
        }

        return array_diff_key($ajouts, array_filter($donnees, fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * Les items d'un bloc répétable, enrichis : variantes de personne, et pour les associés la
     * numérotation des parts et l'apport.
     *
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $donnees
     * @return list<array<string, mixed>>
     */
    public static function bloc(array $items, array $donnees): array
    {
        $vn = $donnees['soc.valeur_nominale_chiffres'] ?? null;

        // « numérotées de 1 à 50 ; … de 51 à 100 » : un cumul dans l'ordre de saisie, qui n'a de
        // sens que si **tous** les nombres de parts sont connus. Un seul manquant décalerait les
        // suivants : on n'écrit alors aucune numérotation.
        $toutesConnues = $items !== [] && collect($items)->every(
            fn ($i) => is_array($i) && is_numeric($i['parts_chiffres'] ?? null)
        );

        $cumul = 0;

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $ajouts = is_string($item['civilite'] ?? null) ? self::personne($item['civilite']) : [];

            // « 1°) Monsieur … 2°) Monsieur … » : le rang dans la liste, que le gabarit fait
            // suivre de son « °) ».
            $ajouts['numero'] = (string) ($index + 1);

            if ($toutesConnues) {
                $parts = (int) $item['parts_chiffres'];
                $ajouts['parts_debut'] = (string) ($cumul + 1);
                $ajouts['parts_fin'] = (string) ($cumul + $parts);
                $cumul += $parts;
            }

            if (is_numeric($item['parts_chiffres'] ?? null) && is_numeric($vn)) {
                $ajouts['apport_chiffres'] = (string) ((int) $item['parts_chiffres'] * (float) $vn);
            }

            $items[$index] = $item + array_diff_key($ajouts, array_filter($item, fn ($v) => $v !== null && $v !== ''));
        }

        return $items;
    }

    /**
     * Une date JJ/MM/AAAA rédigée comme l'étude l'écrit dans une comparution :
     * « quinze Février mille neuf cent quatre-vingt », « premier Mars deux mille ».
     * Distincte de `_lettres` (capitales), qui sert les formules de date d'acte.
     */
    public static function dateRedigee(string $jma): ?string
    {
        if (! preg_match('#^\d{2}/\d{2}/\d{4}$#', $jma)) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $jma);
        } catch (\Exception) {
            return null;
        }

        $jour = (int) $date->day;
        $minuscules = fn (float $n) => mb_strtolower(NombreEnLettres::convertir($n, ''), 'UTF-8');

        // L'année s'écrit sans le « s » final de vingt et cent : « mille neuf cent
        // quatre-vingt », comme dans les actes de l'étude (DNSV et statuts KOUMI). Le pluriel
        // de `convertir()` est juste pour un montant, pas pour un millésime.
        $annee = preg_replace('/(vingt|cent)s$/u', '$1', $minuscules($date->year));

        return ($jour === 1 ? 'premier' : $minuscules($jour))
            . ' ' . self::MOIS[(int) $date->month]
            . ' ' . $annee;
    }

    /**
     * « 19 Novembre 2026 », « 06 Mars 1993 » : la date des en-têtes de lettre, des attestations
     * et du formulaire RCCM (modèles finaux du 2026-11-20). Accepte une date JJ/MM/AAAA ou un
     * objet date (la date de l'acte).
     */
    public static function dateCourte(string|\DateTimeInterface $date): ?string
    {
        if (is_string($date)) {
            if (! preg_match('#^\d{2}/\d{2}/\d{4}$#', $date)) {
                return null;
            }
            try {
                $date = Carbon::createFromFormat('d/m/Y', $date);
            } catch (\Exception) {
                return null;
            }
        }

        return sprintf('%02d %s %d', (int) $date->format('j'), self::MOIS[(int) $date->format('n')], (int) $date->format('Y'));
    }

    /**
     * Civilité longue et accords. Vide pour une civilité inconnue **ou** une société : une
     * personne morale n'est ni « née » ni « présente » au sens d'une comparution.
     *
     * @return array<string, string>
     */
    private static function personne(string $civilite): array
    {
        $civilite = trim($civilite);
        $feminin = in_array($civilite, self::FEMININ, true);

        if (! $feminin && ! in_array($civilite, self::MASCULIN, true)) {
            return [];
        }

        return [
            'civilite_longue' => self::CIVILITE_LONGUE[$civilite],
            'ne'              => $feminin ? 'Née' : 'Né',
            'accord'          => $feminin ? 'e' : '',
            'lequel'          => $feminin ? 'Laquelle' : 'Lequel',
            'il'              => $feminin ? 'Elle' : 'Il',
        ];
    }

    /**
     * L'objet social saisi, une activité par ligne (ou séparées par « ; »), sans puce ni
     * ponctuation finale — le gabarit apporte la puce et le « ; ».
     *
     * Mesuré le 2026-11-20 : les 28 objets sociaux en base tiennent tous sur une ligne ; ils
     * donnent donc une puce chacun, ce qui reste correct. Le découpage sert les saisies à venir.
     *
     * @return list<string>
     */
    private static function activites(mixed $objet): array
    {
        if (! is_string($objet) || trim($objet) === '') {
            return [];
        }

        $lignes = preg_split('/\r\n|\r|\n|;/u', $objet);
        $lignes = array_map(fn ($l) => rtrim(trim(preg_replace('/^[\s\-–—•*·]+/u', '', $l)), ' .;'), $lignes);

        return array_values(array_map(
            fn ($l) => mb_strtoupper(mb_substr($l, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($l, 1, null, 'UTF-8'),
            array_filter($lignes, fn ($l) => $l !== ''),
        ));
    }

    /**
     * « une (01) personne physique », « deux (02) personnes physiques et une (01) personne
     * morale » — la DNSV dit **qui** a souscrit le capital.
     *
     * `null` dès qu'un souscripteur est de nature inconnue : compter « deux personnes
     * physiques » quand l'une est peut-être une société serait affirmer ce qu'on ignore.
     *
     * @param  array<string, mixed>  $donnees
     */
    private static function souscripteurs(array $donnees): ?string
    {
        $liste = null;
        foreach (['associes', 'actionnaires'] as $bloc) {
            if (is_array($donnees[$bloc] ?? null) && $donnees[$bloc] !== []) {
                $liste = $donnees[$bloc];
                break;
            }
        }

        if ($liste === null) {
            // Associé unique (SARLU, SASU) : la section `pp`. Le champ « Nature » est récent —
            // les dossiers antérieurs ne l'ont pas, et l'associé unique y était forcément une
            // personne physique (`physiqueParDefaut`, voir PP_ASSOCIE_UNIQUE).
            if (empty($donnees['pp.prenom_nom']) && empty($donnees['pp.denomination'])) {
                return null;
            }
            $liste = [['type_personne' => $donnees['pp.type_personne'] ?? 'Personne physique']];
        }

        $compte = ['physique' => 0, 'morale' => 0];
        foreach ($liste as $item) {
            $nature = self::nature(is_array($item) ? $item : []);
            if ($nature === null) {
                return null;
            }
            $compte[$nature]++;
        }

        $morceaux = [];
        foreach ($compte as $nature => $n) {
            if ($n === 0) {
                continue;
            }
            $lettres = mb_strtolower(NombreEnLettres::convertir((float) $n, ''), 'UTF-8');
            $lettres = preg_replace('/\bun$/u', 'une', $lettres); // « personne » est féminin
            $morceaux[] = sprintf('%s (%02d) %s %s', $lettres, $n, $n > 1 ? 'personnes' : 'personne', $n > 1 ? "{$nature}s" : $nature);
        }

        return implode(' et ', $morceaux);
    }

    /** @param array<string, mixed> $item */
    private static function nature(array $item): ?string
    {
        return match (true) {
            ($item['type_personne'] ?? null) === 'Personne physique' => 'physique',
            ($item['type_personne'] ?? null) === 'Personne morale'   => 'morale',
            ($item['civilite'] ?? null) === 'Société'                => 'morale',
            in_array($item['civilite'] ?? null, [...self::FEMININ, ...self::MASCULIN], true) => 'physique',
            default => null,
        };
    }
}
