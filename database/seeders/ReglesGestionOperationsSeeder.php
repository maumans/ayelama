<?php

namespace Database\Seeders;

use App\Models\OperationSociete;
use Illuminate\Database\Seeder;

/**
 * Catalogue officiel des opérations sur les sociétés — addendum v1.2 § 5 (Tableaux 9 et 10).
 *
 * Dépose la copie initiale éditable des 11 opérations de référence (9 de modification,
 * 2 de dissolution) ainsi que les 3 opérations réservées hors périmètre V1.
 *
 * Idempotent (`updateOrCreate` sur `code`) : relançable sans créer de doublon.
 */
class ReglesGestionOperationsSeeder extends Seeder
{
    private const OPERATIONS = [
        // ── Modifications statutaires (Tableau 9) ───────────────────────────
        [
            'code'               => 'MOD_DENOMINATION',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'denomination_sociale',
            'libelle'            => 'Changement de dénomination',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 10,
        ],
        [
            'code'               => 'MOD_SIEGE',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'siege_social',
            'libelle'            => 'Transfert de siège social',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 20,
        ],
        [
            'code'               => 'MOD_OBJET',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'objet_social',
            'libelle'            => "Modification de l'objet social",
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 30,
        ],
        [
            'code'               => 'MOD_FORME',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'forme_juridique',
            'libelle'            => 'Changement de forme juridique',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => false,
            'description_statut' => "Rapport du commissaire aux comptes exigé (art. 374 AUSCGIE) ; majorités inconnues",
            'est_actif'          => true,
            'ordre'              => 40,
        ],
        [
            'code'               => 'AUG_CAPITAL',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'capital_augmentation',
            'libelle'            => 'Augmentation de capital',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 50,
        ],
        [
            // ⚠️ **Absente du § 5 de l'addendum**, qui la range au Tableau 10 (« hors
            // périmètre V1 ») — mais présente au **tableau de validation** du 2026-10-28, dont
            // la ligne 2 réunit « Augmentation ou réduction de capital ». C'est donc bien une
            // opération de l'étude, et le dépôt la pratique depuis l'origine : cas d'enum, bloc
            // de questionnaire (`modif_capital_diminution`), case cochable, ligne de barème.
            // La laisser inactive aurait fait dire au catalogue « non proposée » d'une
            // opération que le formulaire propose. La case « Oui » de la ligne 2 étant restée
            // vide, elle est active mais non validée.
            //
            // Code aligné sur `TypeModificationStatutaire::CapitalDiminution->codeOperation()`.
            // Il valait `RED_CAPITAL` : `referenceEnum()` ne trouvait alors aucune référence,
            // donc `divergeDeLaReference()` rendait `false` — une divergence invisible par
            // construction. Écart à remonter à Thierno.
            'code'               => 'DIM_CAPITAL',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'capital_diminution',
            'libelle'            => 'Diminution de capital',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => false,
            'description_statut' => "Ligne 2 du tableau de validation : case non cochée",
            'est_actif'          => true,
            'ordre'              => 55,
        ],
        [
            'code'               => 'CESSION_PARTS',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'capital_cession',
            'libelle'            => 'Cession de parts sociales',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => false,
            'description_statut' => "Ligne 3 du tableau de validation : case non cochée",
            'est_actif'          => true,
            'ordre'              => 60,
        ],
        [
            'code'               => 'CHG_GERANT_STAT',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'gerant_statutaire',
            'libelle'            => 'Changement de gérant statutaire',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 70,
        ],
        [
            'code'               => 'NOM_GERANT',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'gerant_non_statutaire',
            'libelle'            => "Nomination d'un gérant non statutaire",
            'assemblee'          => 'AGO',
            'impacte_statuts'    => false,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 80,
        ],
        [
            'code'               => 'DECISION_GESTION',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => 'decision_gestion',
            'libelle'            => 'Décision de fonctionnement (ex. hypothèque)',
            'assemblee'          => 'AGO',
            'impacte_statuts'    => false,
            'impacte_rccm'       => false,
            'publication_jal'    => false,
            'exclusive'          => false,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 90,
        ],

        // ── Dissolution et liquidation (Tableau 9) ──────────────────────────
        [
            'code'               => 'DISSOLUTION',
            'type_acte_code'     => 'SOC-DIS',
            'variante'           => 'dissolution',
            'libelle'            => 'Dissolution anticipée ou au terme',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => false,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => true,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 100,
        ],
        [
            'code'               => 'CLOTURE_LIQUIDATION',
            'type_acte_code'     => 'SOC-DIS',
            'variante'           => 'cloture_liquidation',
            'libelle'            => 'Clôture de liquidation et radiation',
            'assemblee'          => 'AG_CLOTURE',
            'impacte_statuts'    => false,
            'impacte_rccm'       => true,
            'publication_jal'    => false,
            'exclusive'          => true,
            'regle_validee'      => true,
            'description_statut' => null,
            'est_actif'          => true,
            'ordre'              => 110,
        ],

        // ── Hors périmètre V1 (Tableau 10) ──────────────────────────────────
        [
            'code'               => 'PROROGATION',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => null,
            'libelle'            => 'Prolongation de la durée',
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => false,
            'description_statut' => 'Hors périmètre V1 — non décrit en atelier',
            'est_actif'          => false,
            'ordre'              => 130,
        ],
        [
            'code'               => 'ANNULATION_DECISION',
            'type_acte_code'     => 'SOC-MOD',
            'variante'           => null,
            'libelle'            => "Annulation d'une décision antérieure",
            'assemblee'          => 'AGE',
            'impacte_statuts'    => true,
            'impacte_rccm'       => true,
            'publication_jal'    => true,
            'exclusive'          => false,
            'regle_validee'      => false,
            'description_statut' => 'Hors périmètre V1 — non décrit en atelier',
            'est_actif'          => false,
            'ordre'              => 140,
        ],
    ];

    public function run(): void
    {
        foreach (self::OPERATIONS as $data) {
            OperationSociete::updateOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }

        // Une ligne dont le code a disparu de la référence n'est plus réglable par personne :
        // l'écran d'administration l'affiche sans pouvoir la comparer à quoi que ce soit, et
        // `referenceEnum()` rend `null`, donc `divergeDeLaReference()` rend `false`. Elle
        // mentirait par omission. `RED_CAPITAL`, renommé `DIM_CAPITAL`, est le premier cas.
        OperationSociete::whereNotIn('code', array_column(self::OPERATIONS, 'code'))->delete();
    }

    /**
     * Réinitialise **une** opération à la règle de référence — le bouton « Réinitialiser » de
     * l'écran d'administration.
     *
     * Même geste que {@see ReglesGestionDocumentsSeeder::reinitialiser()} : la configuration
     * éditable redevient la copie du code, sans redéploiement et sans toucher aux autres
     * lignes. Rend `false` si le code n'est pas au catalogue — l'appelant le signale plutôt que
     * de faire croire à une remise à zéro silencieuse.
     */
    public static function reinitialiser(string $code): bool
    {
        foreach (self::OPERATIONS as $data) {
            if ($data['code'] === $code) {
                OperationSociete::updateOrCreate(['code' => $code], $data);

                return true;
            }
        }

        return false;
    }

    /** Le catalogue de référence, tel que le code le déclare. @return array<int, array<string, mixed>> */
    public static function reference(): array
    {
        return self::OPERATIONS;
    }
}

