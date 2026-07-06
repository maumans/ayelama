<?php

namespace Database\Seeders;

use App\Models\Bareme;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

/**
 * Seeder des barèmes officiels de l'Office Notarial Ayelama BAH.
 * 
 * Source : Section D du document "Analyse_et_Prompt_Generation_Modeles_Ayelema.md"
 * Cadre juridique : OHADA — Guinée, monnaie GNF.
 *
 * ⚠️ Ces taux sont PARAMÉTRABLES en base via l'interface d'administration.
 *    Ce seeder sert uniquement de pré-remplissage initial.
 */
class BaremeSeeder extends Seeder
{
    public function run(): void
    {
        // ═════════════════════════════════════════════════════════════════
        // VENTE D'IMMEUBLE
        // ═════════════════════════════════════════════════════════════════
        $ventesTypes = TypeActe::where('categorie', 'vente')->pluck('id');

        foreach ($ventesTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'taux'         => 5.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '5% du prix de vente',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => "Droits d'enregistrement",
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du prix de vente',
                    'ordre'        => 2,
                ],
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Frais de mutation Conservation Foncière',
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du prix de vente',
                    'ordre'        => 3,
                ],
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Frais de virement',
                    'montant_fixe' => 500000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais fixes de virement — 500 000 GNF',
                    'ordre'        => 4,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Timbres fiscaux & rôles',
                    'montant_fixe' => 50000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Variable selon nombre de pages',
                    'ordre'        => 5,
                ],
            ]);
        }

        // Barème Plus-Value (ajouté séparément si un type "plus_value" existe)
        // Sinon on le met sur les types de vente existants
        foreach ($ventesTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Taxe sur plus-value immobilière',
                    'taux'         => 15.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '15% du montant de la plus-value (à calculer séparément)',
                    'ordre'        => 10,
                    'actif'        => false, // désactivé par défaut, applicable si plus-value
                ],
            ]);
        }

        // ═════════════════════════════════════════════════════════════════
        // BAUX (habitation, professionnel, à construction)
        // ═════════════════════════════════════════════════════════════════
        $bauxTypes = TypeActe::where('categorie', 'bail')->pluck('id');

        foreach ($bauxTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du montant total des loyers sur la durée du bail',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => "Droits d'enregistrement",
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du montant total des loyers sur la durée du bail',
                    'ordre'        => 2,
                ],
            ]);
        }

        // ═════════════════════════════════════════════════════════════════
        // SOCIÉTÉS (SARL, SARLU, SAS, SASU)
        // ═════════════════════════════════════════════════════════════════
        $societeTypes = TypeActe::where('categorie', 'societe')->pluck('id');

        foreach ($societeTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires forfaitaires',
                    'montant_fixe' => 4500000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Forfait honoraires notaire (variable selon capital, à ajuster)',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Frais APIP + RCCM',
                    'montant_fixe' => 390000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais de constitution APIP et immatriculation RCCM',
                    'ordre'        => 2,
                ],
                [
                    'organisme'    => 'Autre',
                    'libelle'      => 'Insertion JAL (Journal Annonces Légales)',
                    'montant_fixe' => 250000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Publication de l\'avis de constitution dans le JAL',
                    'ordre'        => 3,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => "Droits d'enregistrement statuts + DNSV",
                    'montant_fixe' => 200000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Droits d\'enregistrement des statuts et de la DNSV (montant variable)',
                    'ordre'        => 4,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Timbres & rôles',
                    'montant_fixe' => 50000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Timbres fiscaux et rôles selon nombre de pages',
                    'ordre'        => 5,
                ],
            ]);
        }

        // ═════════════════════════════════════════════════════════════════
        // HYPOTHÈQUE
        // ═════════════════════════════════════════════════════════════════
        $hypoTypes = TypeActe::where('categorie', 'hypotheque')->pluck('id');

        foreach ($hypoTypes as $typeActeId) {
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Conservation foncière',
                    'taux'         => 1.5000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '1,5% du montant du crédit',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Impôts hypothécaires',
                    'taux'         => 0.1000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '0,10% du montant du crédit',
                    'ordre'        => 2,
                ],
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires du notaire',
                    'montant_fixe' => 3000000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Forfait honoraires hypothèque (à ajuster selon le montant)',
                    'ordre'        => 3,
                ],
            ]);
        }

        $this->command->info('✅ Barèmes initiaux insérés avec succès.');
    }

    /**
     * Insère les barèmes pour un type d'acte donné, en évitant les doublons.
     */
    private function creerBaremes(int $typeActeId, array $baremes): void
    {
        foreach ($baremes as $bareme) {
            Bareme::firstOrCreate(
                [
                    'type_acte_id' => $typeActeId,
                    'organisme'    => $bareme['organisme'],
                    'libelle'      => $bareme['libelle'],
                ],
                array_merge($bareme, [
                    'type_acte_id' => $typeActeId,
                    'actif'        => $bareme['actif'] ?? true,
                ])
            );
        }
    }
}
