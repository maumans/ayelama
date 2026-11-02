<?php

namespace Database\Seeders;

use App\Enums\OrganismeFormalite;
use App\Models\Bareme;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

/**
 * Les démarches d'une modification et d'une dissolution — tableau de validation signé le
 * 2026-10-28.
 *
 * ⚠️ **Mesuré le 2026-10-30, et c'est ce qui a motivé ce seeder** :
 *
 * ```
 * SOC-MOD : 12 barèmes, 5 actifs, 0 actif générant une formalité
 * SOC-DIS : 11 barèmes, 0 actif,  0 actif générant une formalité
 * ```
 *
 * Autrement dit : **aucune modification ni dissolution n'ouvrait la moindre démarche**. Le
 * formaliste ne voyait rien arriver, et le déplacement de l'application au registre vers « le
 * retour de la formalité RCCM » qu'annonce l'addendum v1.2 § 6.4 aurait gelé les deux
 * procédures pour toujours — il n'y avait pas de formalité pour revenir.
 *
 * Les neuf formalités `SOC-MOD` qui existaient en base étaient de surcroît des démarches
 * **APIP** (« Immatriculation RCCM », « Obtention NIF »), héritées de la constitution par un
 * filtre sur la catégorie. La ligne 12 du tableau signé les exclut sans ambiguïté :
 * « L'étude traite directement avec le greffe du tribunal de commerce. L'APIP n'intervient pas
 * dans les modifications. » Elles sont désactivées depuis le 2026-09-28 ; ce seeder pose ce
 * qui aurait dû les remplacer.
 *
 * **Ce que le tableau signé établit, ligne par ligne :**
 *
 * | Ligne | Démarche | Organisme |
 * |---|---|---|
 * | 1 à 5 | Publication de chaque modification au journal d'annonces légales | JAL |
 * | 12 | Modification du RCCM | Greffe du Tribunal de Commerce, **jamais l'APIP** |
 * | 13 | « Tous les actes authentiques de l'étude sont enregistrés aux impôts » | Impôts |
 * | 15 | Dissolution : publication au JAL, **puis** dépôt du PV et du journal au greffe, qui délivre un RCCM de dissolution | JAL puis Greffe |
 * | 17 | Clôture : le greffe délivre un RCCM de liquidation, la société est radiée. **Aucune publication** | Greffe |
 *
 * **Aucun montant n'est inventé.** Les lignes qui existaient déjà gardent le leur, sourcé du
 * compte rendu de juillet 2026 ; celles que ce seeder crée portent zéro et le marqueur
 * `a_confirmer` — l'étude n'a communiqué aucune grille de dissolution, et la ligne 14 du
 * tableau dit que la modification n'a « pas de forfait : une facture proforma est envoyée après
 * réception du questionnaire rempli, daté et signé ». Un zéro visible se corrige à l'écran ;
 * un montant inventé se découvre sur une facture.
 *
 * Idempotent : `updateOrCreate` sur (type d'acte, libellé).
 */
class ReglesGestionFormalitesSeeder extends Seeder
{
    private const SOURCE = 'Tableau de validation signé par Maître Bah le 2026-10-28';

    /**
     * Lignes existantes dont il suffit d'ouvrir la démarche.
     *
     * Elles portent déjà un montant sourcé : on ne touche ni au montant, ni au libellé — rien
     * qu'une facture émise pourrait citer. Seul `genere_formalite` bascule.
     */
    private const DEMARCHES_SUR_LIGNES_EXISTANTES = [
        [
            'type'           => 'SOC-MOD',
            'libelle'        => 'Enregistrement du procès-verbal',
            'retour_attendu' => 'Quittance d\'enregistrement',
            'delai_heures'   => 72,
            'pieces'         => ['Procès-verbal signé'],
            'donnees'        => ['quittance_numero'],
            // Ligne 13 : tout acte authentique de l'étude est enregistré. Le procès-verbal est
            // le seul acte que **toutes** les variantes de modification produisent — c'est donc
            // lui qui porte la démarche, et non les statuts mis à jour, absents dès que la
            // modification ne touche pas au pacte social.
        ],
        [
            'type'           => 'SOC-MOD',
            'libelle'        => 'Enregistrement au Tribunal de Commerce (RCCM)',
            'retour_attendu' => 'Extrait RCCM modifié',
            'delai_heures'   => 120,
            'pieces'         => ['Procès-verbal enregistré', 'Statuts mis à jour', 'Justificatif de parution au JAL'],
            'donnees'        => [],
            // Ligne 12. ⚠️ Aucune donnée captée au retour : le RCCM d'une modification **ne
            // change pas** le numéro de la société, il en atteste la mise à jour. Semer
            // `rccm_numero` ici réécrirait l'identifiant de la fiche à chaque modification —
            // c'est exactement le défaut que la décision 68 du devbook a corrigé.
        ],
    ];

    /**
     * Démarches à créer, faute de ligne de barème existante.
     *
     * `montant_fixe` à zéro et `a_confirmer` : la démarche doit exister pour que le formaliste
     * la voie, son coût reste à renseigner par l'étude.
     */
    private const DEMARCHES_A_CREER = [
        [
            'type'      => 'SOC-MOD',
            'organisme' => OrganismeFormalite::Jal,
            'libelle'   => "Publication de la modification au journal d'annonces légales",
            'condition' => null,
            'retour'    => 'Justificatif de parution au journal',
            'donnees'   => ['jal_journal', 'jal_date_parution'],
            'delai'     => 168,
            'pieces'    => ['Procès-verbal enregistré'],
            'ordre'     => 30,
            'motif'     => 'Lignes 1 à 5 : « Publication obligatoire au journal d\'annonces légales ».',
        ],
        [
            'type'      => 'SOC-DIS',
            'organisme' => OrganismeFormalite::Impots,
            'libelle'   => 'Enregistrement du procès-verbal de dissolution',
            'condition' => 'dissolution',
            'retour'    => "Quittance d'enregistrement",
            'donnees'   => ['quittance_numero'],
            'delai'     => 72,
            'pieces'    => ['Procès-verbal de dissolution signé'],
            'ordre'     => 10,
            'motif'     => 'Ligne 13 : tout acte authentique de l\'étude est enregistré aux impôts.',
        ],
        [
            'type'      => 'SOC-DIS',
            'organisme' => OrganismeFormalite::Jal,
            'libelle'   => "Publication de la dissolution au journal d'annonces légales",
            'condition' => 'dissolution',
            'retour'    => 'Justificatif de parution au journal',
            'donnees'   => ['jal_journal', 'jal_date_parution'],
            'delai'     => 168,
            'pieces'    => ['Procès-verbal de dissolution enregistré'],
            'ordre'     => 20,
            'motif'     => 'Ligne 15, point 2 : « Publication du PV dans un journal d\'annonces légales ».',
        ],
        [
            'type'      => 'SOC-DIS',
            'organisme' => OrganismeFormalite::Greffe,
            'libelle'   => 'Dépôt au greffe — RCCM de dissolution',
            'condition' => 'dissolution',
            'retour'    => 'RCCM de dissolution',
            'donnees'   => [],
            'delai'     => 120,
            'pieces'    => ['Procès-verbal de dissolution', 'Justificatif de parution au JAL'],
            'ordre'     => 30,
            // Ligne 15, points 3 et 4 : le dépôt réunit le PV **et** le journal, donc il suit
            // la publication. La dépendance est posée plus bas, pas devinée ici.
            'depend_de' => "Publication de la dissolution au journal d'annonces légales",
            'motif'     => 'Ligne 15, points 3 et 4 : dépôt du PV et du journal au greffe, qui délivre un RCCM de dissolution.',
        ],
        [
            'type'      => 'SOC-DIS',
            'organisme' => OrganismeFormalite::Greffe,
            'libelle'   => 'Dépôt au greffe — RCCM de liquidation et radiation',
            'condition' => 'cloture_liquidation',
            'retour'    => 'RCCM de liquidation et radiation',
            'donnees'   => [],
            'delai'     => 120,
            'pieces'    => ['Procès-verbal de clôture de liquidation'],
            'ordre'     => 40,
            // ⚠️ **Pas de publication pour la clôture.** La ligne 17 énumère ses quatre étapes
            // sans en citer aucune, là où la ligne 15 la cite pour la dissolution — et la
            // ligne 5 a été annotée à la main pour ajouter la publication là où elle manquait.
            // Une relectrice qui ajoute la mention où elle manque et ne l'ajoute pas ailleurs
            // répond deux fois.
            'motif'     => 'Ligne 17, points 3 et 4 : le greffe délivre un RCCM de liquidation, la société est radiée.',
        ],
    ];

    public function run(): void
    {
        $types = TypeActe::pluck('id', 'code');

        foreach (self::DEMARCHES_SUR_LIGNES_EXISTANTES as $d) {
            if (! isset($types[$d['type']])) {
                continue;
            }

            Bareme::where('type_acte_id', $types[$d['type']])
                ->where('libelle', $d['libelle'])
                ->update([
                    'actif'             => true,
                    'genere_formalite'  => true,
                    'retour_attendu'    => $d['retour_attendu'],
                    'delai_heures'      => $d['delai_heures'],
                    'pieces_requises'   => json_encode($d['pieces']),
                    'donnees_au_retour' => json_encode($d['donnees']),
                ]);
        }

        $crees = [];

        foreach (self::DEMARCHES_A_CREER as $d) {
            if (! isset($types[$d['type']])) {
                continue;
            }

            $crees[$d['type']][$d['libelle']] = Bareme::updateOrCreate(
                ['type_acte_id' => $types[$d['type']], 'libelle' => $d['libelle']],
                [
                    'organisme'              => $d['organisme']->libelleBareme(),
                    'montant_fixe'           => 0,
                    'base_calcul'            => 'montant_fixe',
                    'condition_modification' => $d['condition'],
                    'genere_formalite'       => true,
                    'retour_attendu'         => $d['retour'],
                    'donnees_au_retour'      => $d['donnees'],
                    'delai_heures'           => $d['delai'],
                    'pieces_requises'        => $d['pieces'],
                    'ordre'                  => $d['ordre'],
                    'actif'                  => true,
                    'description'            => $d['motif'],
                    'source'                 => self::SOURCE . ' — ' . $d['motif'],
                    // Le montant, lui, n'est pas sourcé : la démarche l'est, son coût non.
                    'a_confirmer'            => true,
                ],
            );
        }

        // Les dépendances, une fois toutes les lignes posées : on ne dépend pas d'une ligne
        // qui n'existe pas encore.
        foreach (self::DEMARCHES_A_CREER as $d) {
            if (! isset($d['depend_de'], $crees[$d['type']][$d['depend_de']], $crees[$d['type']][$d['libelle']])) {
                continue;
            }

            $crees[$d['type']][$d['libelle']]->update([
                'depend_de_bareme_id' => $crees[$d['type']][$d['depend_de']]->id,
            ]);
        }
    }
}
