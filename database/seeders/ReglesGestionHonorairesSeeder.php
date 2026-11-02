<?php

namespace Database\Seeders;

use App\Models\Bareme;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

/**
 * La grille de l'étude, relevée sur **ses propres notes de frais**.
 *
 * Source : les classeurs `FACTURE *.xls` du dossier « Documents reçus » remis par l'étude, un par
 * forme de société et un par type de vente ou de bail. Ce sont les notes que l'étude envoie
 * réellement à ses clients : elles font foi davantage qu'une estimation, et davantage que le
 * compte rendu d'atelier, qui ne chiffre pas tout.
 *
 * ⚠️ **Ce seeder corrige des montants que la plateforme facturait à tort.** Jusqu'au 2026-10-30,
 * `BaremeSeeder` posait **la même grille sur les sept formes** et la même sur les quatre types de
 * vente, parce qu'il bouclait sur la catégorie. Confronté aux notes de l'étude :
 *
 * | Ligne | Ce que la base appliquait | Ce que la note de l'étude porte |
 * |---|---|---|
 * | Honoraires de constitution | 4 500 000 partout | 4 500 000 en SARL et SARLU, **8 500 000** en SAS et SASU |
 * | Frais APIP et RCCM | 490 000 partout | **390 000** en SARL et SARLU, 490 000 en SAS et SASU |
 * | Timbres, rôles | un forfait de 50 000, ligne unique | **deux lignes**, à la page : 2 000 et 5 000 GNF |
 * | Enregistrement APIP sur une vente | 150 000 | **absent** de la note de vente |
 * | Mutation Conservation Foncière, vente sans titre | 2 % | **absente** — il n'y a pas de titre à muter |
 * | Honoraires de bail | aucun taux | **2 %** du montant des loyers |
 * | Taxe sur plus-value | aucun taux | **15 %** du montant de la plus-value |
 *
 * **Trois traitements, et la distinction est la règle de ce seeder :**
 *
 *  1. un montant que la note de l'étude donne → posé, `source` renseignée, `a_confirmer` faux ;
 *  2. un montant qu'**aucune** note ne donne et qu'aucune ne contredit → laissé en place, mais
 *     marqué `a_confirmer` : il reste facturable et le dit. C'est le cas des trois formes sans
 *     note (SA, SNC, GIE), de l'hypothèque et du bail commercial ;
 *  3. un montant qu'une note de l'étude **contredit** → désactivé. On ne facture pas contre un
 *     document fourni par l'étude elle-même. Jamais supprimé : une facture émise le référence,
 *     et son montant doit rester explicable.
 *
 * **Idempotent et relançable** — c'est aussi ce que fera le bouton « Réinitialiser » de l'écran
 * Barèmes. Relancer efface les écarts et restaure la grille relevée.
 *
 * ⚠️ **Une divergence à trancher par l'étude, laissée visible plutôt que résolue en silence** :
 * l'addendum v1.2 § 7.3 annonce un forfait de **5 500 000 GNF** pour une création de SARL et dit
 * remplacer l'estimation antérieure ; la note de frais SARL de l'étude porte **4 500 000**. Deux
 * documents du même client se contredisent. La note l'emporte ici — c'est elle qui part au
 * client, et son total est cohérent (5 140 000 GNF) — mais la ligne est marquée `a_confirmer`
 * pour que l'écart se voie au lieu de se perdre.
 */
class ReglesGestionHonorairesSeeder extends Seeder
{
    /** Le prix d'un timbre fiscal et d'un rôle, à la page. */
    private const TIMBRE_PAR_PAGE = 2_000;
    private const ROLE_PAR_PAGE   = 5_000;

    /**
     * La grille relevée, par type d'acte.
     *
     * `cle` identifie la ligne existante par son libellé en base — les libellés de la note de
     * frais ne sont pas tout à fait ceux du seeder d'origine, et renommer en masse casserait le
     * lien avec les factures déjà émises.
     */
    private const GRILLE = [
        // ── Constitutions : une note de frais par forme ──────────────────────
        'SOC-SARLU' => [
            ['cle' => 'Honoraires forfaitaires',  'montant_fixe' => 4_500_000],
            ['cle' => 'Frais APIP et RCCM',       'montant_fixe' => 390_000],
            ['cle' => 'Insertion JAL (Journal Annonces Légales)', 'montant_fixe' => 250_000],
            ['cle' => "Droits d'enregistrement des Statuts et de la DNSV", 'montant_fixe' => 0],
        ],
        'SOC-SARL' => [
            ['cle' => 'Honoraires forfaitaires',  'montant_fixe' => 4_500_000],
            ['cle' => 'Frais APIP et RCCM',       'montant_fixe' => 390_000],
            ['cle' => 'Insertion JAL (Journal Annonces Légales)', 'montant_fixe' => 250_000],
            ['cle' => "Droits d'enregistrement des Statuts et de la DNSV", 'montant_fixe' => 0],
        ],
        'SOC-SAS' => [
            ['cle' => 'Honoraires forfaitaires',  'montant_fixe' => 8_500_000],
            ['cle' => 'Frais APIP et RCCM',       'montant_fixe' => 490_000],
            ['cle' => 'Insertion JAL (Journal Annonces Légales)', 'montant_fixe' => 250_000],
            ['cle' => "Droits d'enregistrement des Statuts et de la DNSV", 'montant_fixe' => 0],
        ],
        'SOC-SASU' => [
            ['cle' => 'Honoraires forfaitaires',  'montant_fixe' => 8_500_000],
            ['cle' => 'Frais APIP et RCCM',       'montant_fixe' => 490_000],
            ['cle' => 'Insertion JAL (Journal Annonces Légales)', 'montant_fixe' => 250_000],
            ['cle' => "Droits d'enregistrement des Statuts et de la DNSV", 'montant_fixe' => 0],
        ],

        // ── Ventes : deux notes, avec et sans titre foncier ──────────────────
        'VTE-IMM' => [
            ['cle' => 'Honoraires du notaire',                      'taux' => 5],
            ['cle' => "Droits d'enregistrement",                    'taux' => 2],
            ['cle' => 'Frais de mutation Conservation Foncière',    'taux' => 2],
            ['cle' => 'Frais de virement',                          'montant_fixe' => 500_000],
            ['cle' => 'Taxe sur plus-value immobilière',            'taux' => 15],
        ],
        'VTE-SAN' => [
            ['cle' => 'Honoraires du notaire',   'taux' => 5],
            ['cle' => "Droits d'enregistrement", 'taux' => 2],
            ['cle' => 'Frais de virement',       'montant_fixe' => 500_000],
            ['cle' => 'Taxe sur plus-value immobilière', 'taux' => 15],
        ],

        // ── Baux : deux notes, habitation et construction ────────────────────
        'BAI-HAB' => [
            ['cle' => 'Honoraires du notaire',   'taux' => 2],
            ['cle' => "Droits d'enregistrement", 'taux' => 2],
        ],
        'BAI-CON' => [
            ['cle' => 'Honoraires du notaire',   'taux' => 2],
            ['cle' => "Droits d'enregistrement", 'taux' => 2],
        ],
    ];

    /**
     * Lignes que les notes de frais **contredisent** — désactivées, jamais supprimées.
     *
     * Chacune a sa raison, et aucune n'est une préférence de style : la note de vente de l'étude
     * compte six lignes et aucune n'est un enregistrement APIP ; la note « sans titre foncier »
     * n'a pas de ligne de mutation, parce qu'il n'y a pas de titre à muter ; la note de
     * constitution facture les timbres et les rôles **à la page**, en deux lignes, et jamais
     * en un forfait.
     */
    private const CONTREDITS = [
        [
            'types'   => ['VTE-IMM', 'VTE-SAN', 'VTE-FDS', 'VTE-VEH'],
            'libelle' => 'Enregistrement APIP',
            'motif'   => "Désactivé le 2026-10-30 : la note de frais de vente fournie par l'étude "
                . "ne porte aucun enregistrement APIP. Facturer 150 000 GNF qui ne figurent sur "
                . 'aucune de ses notes revenait à inventer un débours.',
        ],
        [
            'types'   => ['VTE-SAN'],
            'libelle' => 'Frais de mutation Conservation Foncière',
            'motif'   => "Désactivé le 2026-10-30 : la note « vente sans titre foncier » de l'étude "
                . "ne porte pas cette ligne — il n'y a pas de titre à muter. Elle venait d'une "
                . 'boucle sur la catégorie « vente », qui ne distinguait pas les deux cas.',
        ],
        [
            'types'   => ['SOC-SARLU', 'SOC-SARL', 'SOC-SA', 'SOC-SAS', 'SOC-SASU', 'SOC-SNC', 'SOC-GIE'],
            'libelle' => 'Timbres & rôles',
            'motif'   => "Désactivé le 2026-10-30 : les notes de l'étude facturent les timbres et "
                . 'les rôles séparément et **à la page** (2 000 et 5 000 GNF, relevés sur une note '
                . "de 76 pages). Le forfait de 50 000 GNF n'apparaît sur aucune note. Remplacé par "
                . 'les deux lignes à la page.',
        ],
        [
            'types'   => ['VTE-IMM', 'VTE-SAN', 'VTE-FDS', 'VTE-VEH'],
            'libelle' => 'Timbres fiscaux & rôles',
            'motif'   => "Désactivé le 2026-10-30 : même motif que pour les constitutions — les "
                . "notes de l'étude portent deux lignes distinctes, comptées à la page.",
        ],
    ];

    /**
     * Les deux lignes à la page, ajoutées là où la note de l'étude les porte.
     *
     * `quantite_defaut` à zéro, comme sur la note : le nombre de pages n'est connu qu'une fois
     * l'acte rédigé. Une quantité par défaut inventée ferait apparaître un montant faux sur
     * chaque proforma.
     */
    private const LIGNES_A_LA_PAGE = [
        [
            'organisme'       => 'Impots',
            'libelle'         => 'Timbres fiscaux (à la page)',
            'montant_fixe'    => self::TIMBRE_PAR_PAGE,
            'quantite_defaut' => 0,
            'base_calcul'     => 'montant_fixe',
            'description'     => 'Timbre fiscal : 2 000 GNF par page. Saisir le nombre de pages.',
            'ordre'           => 20,
        ],
        [
            'organisme'       => 'Impots',
            'libelle'         => 'Rôles (à la page)',
            'montant_fixe'    => self::ROLE_PAR_PAGE,
            'quantite_defaut' => 0,
            'base_calcul'     => 'montant_fixe',
            'description'     => 'Rôle : 5 000 GNF par page. Saisir le nombre de pages.',
            'ordre'           => 21,
        ],
    ];

    /** Types d'acte dont la note de frais porte des timbres et des rôles. */
    private const TYPES_AVEC_PAGES = [
        'SOC-SARLU', 'SOC-SARL', 'SOC-SA', 'SOC-SAS', 'SOC-SASU', 'SOC-SNC', 'SOC-GIE',
        'VTE-IMM', 'VTE-SAN', 'BAI-HAB', 'BAI-CON',
    ];

    private const SOURCE = "Note de frais de l'étude (Documents reçus, 2026)";

    public function run(): void
    {
        $types = TypeActe::pluck('id', 'code');

        // 1. Les montants relevés sur les notes.
        foreach (self::GRILLE as $code => $lignes) {
            if (! isset($types[$code])) {
                continue;
            }

            foreach ($lignes as $ligne) {
                Bareme::where('type_acte_id', $types[$code])
                    ->where('libelle', $ligne['cle'])
                    ->update(array_filter([
                        'taux'         => $ligne['taux'] ?? null,
                        'montant_fixe' => $ligne['montant_fixe'] ?? null,
                        'source'       => self::SOURCE . ' — ' . $code,
                        'a_confirmer'  => false,
                    ], fn ($v) => $v !== null));
            }
        }

        // 2. Les lignes à la page, là où la note les porte.
        foreach (self::TYPES_AVEC_PAGES as $code) {
            if (! isset($types[$code])) {
                continue;
            }

            foreach (self::LIGNES_A_LA_PAGE as $ligne) {
                Bareme::updateOrCreate(
                    ['type_acte_id' => $types[$code], 'libelle' => $ligne['libelle']],
                    [...$ligne, 'actif' => true, 'source' => self::SOURCE, 'a_confirmer' => false],
                );
            }
        }

        // 3. Ce que les notes contredisent.
        foreach (self::CONTREDITS as $contredit) {
            $ids = collect($contredit['types'])->map(fn ($c) => $types[$c] ?? null)->filter();

            if ($ids->isEmpty()) {
                continue;
            }

            Bareme::whereIn('type_acte_id', $ids)
                ->where('libelle', $contredit['libelle'])
                ->where('actif', true)
                ->update(['actif' => false, 'source' => $contredit['motif'], 'a_confirmer' => false]);
        }

        // 4. Tout tarif actif que ce seeder n'a pas sourcé reste facturable, mais le dit.
        $this->marquerLeReste();
    }

    /**
     * Marque « à confirmer » tout tarif actif dont aucune note de frais ne donne le montant.
     *
     * ⚠️ **C'est le cœur de la normalisation, et il est volontairement large.** On n'énumère pas
     * les lignes douteuses — on marque tout ce qui n'est pas sourcé, pour qu'une ligne ajoutée
     * demain sans source se signale toute seule. Une liste d'exceptions se périme ; une règle
     * par défaut, non.
     *
     * Concerne aujourd'hui : les trois formes sans note (SA, SNC, GIE), l'hypothèque, le bail
     * commercial, la cession de fonds de commerce, la vente de véhicule, et les tarifs de
     * modification issus du compte rendu de juillet 2026 — ceux-là ont bien une source écrite,
     * mais pas une note de frais, et la ligne 14 du tableau de validation signé le 2026-10-28
     * dit justement qu'une modification **n'a pas de forfait** : une proforma est établie au cas
     * par cas. Les laisser muets aurait fait partir des montants que l'étude n'applique pas.
     */
    private function marquerLeReste(): void
    {
        Bareme::where('actif', true)
            ->whereNull('source')
            ->update([
                'a_confirmer' => true,
                'source'      => "Aucune note de frais de l'étude ne donne ce montant (constat du 2026-10-30).",
            ]);
    }
}
