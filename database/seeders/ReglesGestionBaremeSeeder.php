<?php

namespace Database\Seeders;

use App\Enums\TypeModificationStatutaire;
use App\Models\Bareme;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

/**
 * Tarifs officiels d'enregistrement — règles 10 et 11 du CR de juillet 2026
 * (`Regles_Gestion_Plateforme_Notariale.docx`).
 *
 * Séparé de `BaremeSeeder` (données de démonstration) : ces montants ont une source
 * documentaire datée, et devront être révisés quand les tarifs DGI ou du greffe changeront.
 * Les garder distincts rend visible ce qui vient du document et ce qui vient d'une
 * estimation initiale.
 *
 * **Idempotent** (`updateOrCreate` sur type d'acte + organisme + libellé) : relançable
 * sans créer de doublon, et corrige les montants si le document est mis à jour.
 *
 * ⚠️ Les factures **déjà émises** ne bougent pas : `FacturationService` copie les montants
 * dans `lignes_factures` à la génération. Modifier un barème n'affecte que les factures
 * générées ensuite — c'est le comportement voulu (une facture est un document daté).
 *
 * ---
 *
 * **Correction du 2026-08-11 — périmètre des tarifs.** La première version appliquait les
 * quatre tarifs à *tous* les types d'acte de société : une constitution de SARLU se voyait
 * donc facturer « Enregistrement des statuts **mis à jour** », et un dossier de modification
 * portant un simple transfert de siège se voyait facturer une DNSV. Le tableau 10 du document
 * est un tarif *de modification* — il n'énonce pas que toute société paie ces quatre droits.
 * Chaque tarif porte désormais son périmètre : les types d'acte concernés, et pour `SOC-MOD`
 * la `condition_modification` qui le restreint aux modifications qui l'exigent réellement
 * (voir {@see \App\Models\Bareme::estApplicableA()}).
 */
class ReglesGestionBaremeSeeder extends Seeder
{
    /** Le type d'acte de modification de statuts, seul concerné par les tarifs de modification. */
    private const CODE_MODIFICATION = 'SOC-MOD';

    /**
     * Tarifs de **modification statutaire** — `SOC-MOD` uniquement.
     *
     * `montant_fixe` en GNF. `condition_modification` à `null` signifie « toute modification » ;
     * sinon c'est une valeur de {@see TypeModificationStatutaire}.
     */
    private const TARIFS_MODIFICATION = [
        [
            'organisme'    => 'Impots',
            'libelle'      => 'Enregistrement des statuts mis à jour',
            'montant_fixe' => 500_000,
            'base_calcul'  => 'montant_fixe',
            // Toute modification hors gérant non statutaire met les statuts à jour ; le cas
            // exclu ne produit pas de statuts (TypeModificationStatutaire::impacteStatuts()).
            // Laissé inconditionnel : le seul cas exclu est rarement isolé, et facturer un
            // droit non dû est plus grave que l'inverse — à conditionner si l'étude constate
            // des dossiers portant *uniquement* un changement de gérant non statutaire.
            'condition'    => null,
            'description'  => 'Règle 10 — DGI, CR juillet 2026',
            'ordre'        => 10,
        ],
        [
            'organisme'    => 'Impots',
            'libelle'      => 'Enregistrement du procès-verbal',
            'montant_fixe' => 100_000,
            'base_calcul'  => 'montant_fixe',
            // Un PV est produit dans tous les cas, et un seul quel que soit le nombre de
            // résolutions : inconditionnel, et surtout facturé une seule fois.
            'condition'    => null,
            'description'  => 'Règle 10 — DGI, CR juillet 2026',
            'ordre'        => 11,
        ],
        [
            'organisme'    => 'Impots',
            'libelle'      => 'Enregistrement DNSV (déclaration de souscription et de versement)',
            'montant_fixe' => 100_000,
            'base_calcul'  => 'montant_fixe',
            // « Augmentation de capital : une DNSV est établie. Diminution de capital : aucune
            // DNSV n'est requise. » (notes de l'étude, 2026-08-11) — c'est exactement ce que
            // porte TypeModificationStatutaire::exigeDnsv().
            'condition'    => TypeModificationStatutaire::CapitalAugmentation,
            'description'  => 'Règle 10 — DGI, CR juillet 2026. Augmentation de capital seulement.',
            'ordre'        => 12,
        ],
        [
            'organisme'    => 'Impots',
            'libelle'      => 'Droit de cession de parts sociales',
            'taux'         => 2,
            'base_calcul'  => 'valeur_acte',
            'condition'    => TypeModificationStatutaire::CapitalCession,
            'description'  => "Règle 10 — 2 % de la valeur des parts cédées. L'assiette est la valeur saisie dans le dossier, pas le capital social.",
            'ordre'        => 14,
        ],
    ];

    /**
     * Enregistrement au greffe — s'applique à **tous** les types d'acte de société.
     *
     * Une constitution comme une modification passent au Tribunal de Commerce (règle 8 :
     * toutes les modifications impactent le RCCM, sans exception).
     */
    private const TARIF_GREFFE = [
        'organisme'    => 'Greffe',
        'libelle'      => 'Enregistrement au Tribunal de Commerce (RCCM)',
        'montant_fixe' => 180_000,
        'base_calcul'  => 'montant_fixe',
        'condition'    => null,
        'description'  => 'Règle 11 — greffe du Tribunal de Commerce, CR juillet 2026',
        'ordre'        => 13,
    ];

    public function run(): void
    {
        $typesSociete = TypeActe::where('categorie', 'societe')->get();

        foreach ($typesSociete as $type) {
            $this->appliquer($type, self::TARIF_GREFFE);
        }

        $modification = $typesSociete->firstWhere('code', self::CODE_MODIFICATION);
        if ($modification) {
            foreach (self::TARIFS_MODIFICATION as $tarif) {
                $this->appliquer($modification, $tarif);
            }
        }

        $this->corrigerAncienTarifGreffe();
        $this->desactiverTarifsModificationHorsPerimetre($typesSociete);
    }

    private function appliquer(TypeActe $type, array $tarif): void
    {
        Bareme::updateOrCreate(
            [
                'type_acte_id' => $type->id,
                'organisme'    => $tarif['organisme'],
                'libelle'      => $tarif['libelle'],
            ],
            [
                'taux'                   => $tarif['taux'] ?? null,
                'montant_fixe'           => $tarif['montant_fixe'] ?? null,
                'base_calcul'            => $tarif['base_calcul'],
                'condition_modification' => $tarif['condition']?->value,
                'description'            => $tarif['description'],
                'ordre'                  => $tarif['ordre'],
                'actif'                  => true,
            ],
        );
    }

    /**
     * La donnée de démonstration portait « Dépôt statuts au greffe : 100 000 GNF ».
     * Le document de juillet 2026 fixe l'enregistrement au Tribunal de Commerce à
     * 180 000 GNF — c'est lui qui fait foi (décision validée). Cette ancienne ligne est
     * désactivée plutôt que supprimée : les factures qui la référencent gardent ainsi une
     * trace lisible de l'origine de leur montant.
     */
    private function corrigerAncienTarifGreffe(): void
    {
        Bareme::where('organisme', 'Greffe')
            ->where('libelle', 'Dépôt statuts au greffe')
            ->update([
                'actif'       => false,
                'description' => 'Remplacé le 2026-08-05 par « Enregistrement au Tribunal de Commerce (RCCM) » à 180 000 GNF (CR juillet 2026).',
            ]);
    }

    /**
     * Désactive les tarifs de modification posés à tort sur les types d'acte de
     * **constitution** par la première version de ce seeder (2026-08-05).
     *
     * Désactivés et non supprimés, pour la même raison que le tarif greffe : une facture
     * générée entre-temps les référence, et son montant doit rester explicable.
     */
    private function desactiverTarifsModificationHorsPerimetre($typesSociete): void
    {
        $idsHorsPerimetre = $typesSociete
            ->where('code', '!=', self::CODE_MODIFICATION)
            ->pluck('id');

        if ($idsHorsPerimetre->isEmpty()) {
            return;
        }

        Bareme::whereIn('type_acte_id', $idsHorsPerimetre)
            ->whereIn('libelle', array_column(self::TARIFS_MODIFICATION, 'libelle'))
            ->update([
                'actif'       => false,
                'description' => 'Désactivé le 2026-08-11 : tarif de modification statutaire, hors périmètre d\'un acte de constitution (CR juillet 2026, tableau 10).',
            ]);
    }
}
