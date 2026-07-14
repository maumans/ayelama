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
                    // Génère aussi la formalité correspondante (ex-FormaliteTypeSeeder VTE-IMM).
                    'genere_formalite' => true,
                    'type_impot'       => 'droits_enregistrement',
                    'delai_heures'     => 120,
                    'pieces_requises'  => ['Acte de vente signé'],
                ],
                [
                    'organisme'    => 'Conservation',
                    'libelle'      => 'Frais de mutation Conservation Foncière',
                    'taux'         => 2.0000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '2% du prix de vente',
                    'ordre'        => 3,
                    'genere_formalite' => true,
                    'retour_attendu'   => 'Titre foncier mis à jour',
                    'delai_heures'     => 240,
                    'pieces_requises'  => ['Titre foncier original', 'Certificat de situation juridique'],
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
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Enregistrement APIP',
                    'montant_fixe' => 150000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais fixes d\'enregistrement APIP',
                    'ordre'        => 6,
                    'genere_formalite' => true,
                    'delai_heures'     => 72,
                    'pieces_requises'  => ['Copie CNI vendeur', 'Copie CNI acquéreur', 'Titre foncier original'],
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
            $baremes = $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Notaire',
                    'libelle'      => 'Honoraires forfaitaires',
                    'montant_fixe' => 4500000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Forfait honoraires notaire (variable selon capital, à ajuster)',
                    'ordre'        => 1,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Enregistrement fiscal acte',
                    'montant_fixe' => 70000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => "Droits d'enregistrement des statuts",
                    'ordre'        => 2,
                    'genere_formalite' => true,
                    'type_impot'       => 'droits_enregistrement',
                    'delai_heures'     => 24,
                    'pieces_requises'  => ['Statuts signés', "Formulaire d'enregistrement"],
                ],
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Immatriculation RCCM',
                    'montant_fixe' => 150000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Frais de constitution APIP et immatriculation RCCM',
                    'ordre'        => 3,
                    'genere_formalite' => true,
                    'retour_attendu'   => 'Extrait RCCM définitif',
                    'delai_heures'     => 72,
                    'pieces_requises'  => ['Statuts signés', 'Formulaire APIP', 'Copie CNI gérant'],
                ],
                [
                    'organisme'    => 'APIP',
                    'libelle'      => 'Obtention NIF',
                    'montant_fixe' => 75000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => "Obtention du Numéro d'Identification Fiscale",
                    'ordre'        => 4,
                    'genere_formalite' => true,
                    'retour_attendu'   => 'NIF attribué',
                    'delai_heures'     => 72,
                    'pieces_requises'  => ['Formulaire NIF', 'Copie statuts', 'Copie RCCM provisoire'],
                ],
                [
                    'organisme'    => 'Autre',
                    'libelle'      => 'Insertion JAL (Journal Annonces Légales)',
                    'montant_fixe' => 250000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Publication de l\'avis de constitution dans le JAL',
                    'ordre'        => 5,
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Timbres & rôles',
                    'montant_fixe' => 50000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Timbres fiscaux et rôles selon nombre de pages',
                    'ordre'        => 6,
                ],
            ]);

            // Le dépôt des statuts au Greffe ne peut se faire qu'après réception
            // de l'extrait RCCM définitif (retour de la démarche APIP correspondante).
            $this->creerBaremes($typeActeId, [
                [
                    'organisme'    => 'Greffe',
                    'libelle'      => 'Dépôt statuts au greffe',
                    'montant_fixe' => 100000.00,
                    'base_calcul'  => 'montant_fixe',
                    'description'  => 'Dépôt des statuts définitifs auprès du greffe du tribunal de commerce',
                    'ordre'        => 7,
                    'genere_formalite'    => true,
                    'depend_de_bareme_id' => $baremes['Immatriculation RCCM']->id,
                    'delai_heures'        => 48,
                    'pieces_requises'     => ['Extrait RCCM définitif'],
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
                    // Génère aussi la formalité correspondante (ex-FormaliteTypeSeeder HYP-CON).
                    'genere_formalite' => true,
                    'delai_heures'     => 168,
                    'pieces_requises'  => ['Titre foncier', 'Contrat de prêt'],
                ],
                [
                    'organisme'    => 'Impots',
                    'libelle'      => 'Impôts hypothécaires',
                    'taux'         => 0.1000,
                    'base_calcul'  => 'valeur_acte',
                    'description'  => '0,10% du montant du crédit',
                    'ordre'        => 2,
                    'genere_formalite' => true,
                    'delai_heures'     => 120,
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
     * Retourne les Bareme indexés par libellé, pour permettre à l'appelant de
     * référencer leur id (ex. depend_de_bareme_id d'une démarche suivante).
     *
     * @return array<string, Bareme>
     */
    private function creerBaremes(int $typeActeId, array $baremes): array
    {
        $resultat = [];

        foreach ($baremes as $bareme) {
            $resultat[$bareme['libelle']] = Bareme::firstOrCreate(
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

        return $resultat;
    }
}
