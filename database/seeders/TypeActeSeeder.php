<?php

namespace Database\Seeders;

use App\Enums\CategorieActe;
use App\Models\TypeActe;
use Illuminate\Database\Seeder;

class TypeActeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            // Société
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SARLU', 'label' => 'Constitution SARLU',             'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => 'Acte de constitution de SARLU (associé unique)'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SARL',  'label' => 'Constitution SARL',              'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => 'Acte de constitution de société à responsabilité limitée (multi-associés)'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SA',    'label' => 'Constitution SA',                'prefixe_reference' => 'SOC', 'delai_jours' => 45, 'description' => 'Acte de constitution de société anonyme (capital min. 140 000 000 GNF)'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SAS',   'label' => 'Constitution SAS',               'prefixe_reference' => 'SOC', 'delai_jours' => 45, 'description' => 'Acte de constitution de société par actions simplifiées (multi-associés)'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SASU',  'label' => 'Constitution SASU',              'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => 'Acte de constitution de SASU (associé unique)'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-SNC',   'label' => 'Constitution SNC',               'prefixe_reference' => 'SOC', 'delai_jours' => 20, 'description' => 'Acte de constitution de société en nom collectif'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-GIE',   'label' => 'Constitution GIE',               'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => "Acte de constitution de groupement d'intérêt économique"],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-DIS',   'label' => 'Dissolution de société',         'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => 'Acte de dissolution et liquidation de société'],
            ['categorie' => CategorieActe::Societe, 'code' => 'SOC-MOD',   'label' => 'Modification de statuts',        'prefixe_reference' => 'SOC', 'delai_jours' => 30, 'description' => "Modification des statuts d'une société existante (capital, gérant, siège, objet…)", 'fiche_modification_obligatoire' => true],

            // Vente
            ['categorie' => CategorieActe::Vente, 'code' => 'VTE-IMM',   'label' => 'Vente immobilière (avec TF)',     'prefixe_reference' => 'VTE', 'delai_jours' => 21, 'description' => 'Acte authentique de vente immobilière avec titre foncier'],
            ['categorie' => CategorieActe::Vente, 'code' => 'VTE-SAN',   'label' => 'Vente immobilière (sans TF)',     'prefixe_reference' => 'VTE', 'delai_jours' => 21, 'description' => 'Acte authentique de vente immobilière sans titre foncier'],
            ['categorie' => CategorieActe::Vente, 'code' => 'VTE-FDS',   'label' => 'Cession fonds de commerce',      'prefixe_reference' => 'VTE', 'delai_jours' => 30, 'description' => 'Cession de fonds de commerce et éléments incorporels'],
            ['categorie' => CategorieActe::Vente, 'code' => 'VTE-VEH',   'label' => 'Vente de véhicule',              'prefixe_reference' => 'VTE', 'delai_jours' => 7,  'description' => 'Acte notarié de vente de véhicule'],

            // Hypothèque
            ['categorie' => CategorieActe::Hypotheque, 'code' => 'HYP-CON', 'label' => 'Constitution hypothèque',     'prefixe_reference' => 'HYP', 'delai_jours' => 15, 'description' => 'Constitution de garantie hypothécaire'],
            ['categorie' => CategorieActe::Hypotheque, 'code' => 'HYP-MAI', 'label' => 'Mainlevée hypothèque',        'prefixe_reference' => 'HYP', 'delai_jours' => 10, 'description' => 'Mainlevée et radiation d\'hypothèque'],

            // Bail
            ['categorie' => CategorieActe::Bail, 'code' => 'BAI-CON', 'label' => 'Bail à construction',               'prefixe_reference' => 'BAI', 'delai_jours' => 14, 'description' => 'Bail à construction notarié'],
            ['categorie' => CategorieActe::Bail, 'code' => 'BAI-COM', 'label' => 'Bail commercial',                   'prefixe_reference' => 'BAI', 'delai_jours' => 14, 'description' => 'Bail commercial notarié'],
            ['categorie' => CategorieActe::Bail, 'code' => 'BAI-HAB', 'label' => 'Bail d\'habitation',               'prefixe_reference' => 'BAI', 'delai_jours' => 7,  'description' => 'Bail d\'habitation notarié'],

            // Succession
            ['categorie' => CategorieActe::Succession, 'code' => 'SUC-DEC', 'label' => 'Déclaration de succession',  'prefixe_reference' => 'SUC', 'delai_jours' => 60, 'description' => 'Déclaration et partage de succession'],
            ['categorie' => CategorieActe::Succession, 'code' => 'SUC-PAR', 'label' => 'Partage successoral',        'prefixe_reference' => 'SUC', 'delai_jours' => 45, 'description' => 'Acte de partage de biens successoraux'],

            // Donation
            ['categorie' => CategorieActe::Donation, 'code' => 'DON-SIM', 'label' => 'Donation simple',              'prefixe_reference' => 'DON', 'delai_jours' => 14, 'description' => 'Acte de donation entre vifs'],
            ['categorie' => CategorieActe::Donation, 'code' => 'DON-PAR', 'label' => 'Donation-partage',             'prefixe_reference' => 'DON', 'delai_jours' => 21, 'description' => 'Donation-partage à titre anticipatif'],

            // Mariage
            ['categorie' => CategorieActe::Mariage, 'code' => 'MAR-COM', 'label' => 'Contrat de mariage',            'prefixe_reference' => 'MAR', 'delai_jours' => 7,  'description' => 'Contrat de mariage notarié'],
            ['categorie' => CategorieActe::Mariage, 'code' => 'MAR-DIV', 'label' => 'Convention de divorce',         'prefixe_reference' => 'MAR', 'delai_jours' => 30, 'description' => 'Convention de divorce par consentement mutuel'],

            // Procuration
            ['categorie' => CategorieActe::Procuration, 'code' => 'PRO-GEN', 'label' => 'Procuration générale',      'prefixe_reference' => 'PRO', 'delai_jours' => 3,  'description' => 'Procuration générale notariée'],
            ['categorie' => CategorieActe::Procuration, 'code' => 'PRO-SPE', 'label' => 'Procuration spéciale',      'prefixe_reference' => 'PRO', 'delai_jours' => 2,  'description' => 'Procuration pour acte spécifique'],

            // Courrier
            ['categorie' => CategorieActe::Courrier, 'code' => 'COU-CER', 'label' => 'Courrier de transmission',    'prefixe_reference' => 'COU', 'delai_jours' => 5,  'description' => 'Lettres de transmission, accusés de réception et réquisitions liés à l\'expédition d\'un dossier'],

            // Prise en charge
            ['categorie' => CategorieActe::PriseEnCharge, 'code' => 'PEC-MIN', 'label' => 'Prise en charge d\'un mineur',      'prefixe_reference' => 'PEC', 'delai_jours' => 7,  'description' => 'Acte notarial de prise en charge et tutelle d\'un enfant mineur'],
            ['categorie' => CategorieActe::PriseEnCharge, 'code' => 'PEC-ADT', 'label' => 'Prise en charge d\'un adulte',      'prefixe_reference' => 'PEC', 'delai_jours' => 7,  'description' => 'Acte notarial de prise en charge d\'une personne adulte dépendante'],
            ['categorie' => CategorieActe::PriseEnCharge, 'code' => 'PEC-FIN', 'label' => 'Engagement de prise en charge financière', 'prefixe_reference' => 'PEC', 'delai_jours' => 5,  'description' => 'Engagement notarié de prise en charge des frais et obligations financières'],
            ['categorie' => CategorieActe::PriseEnCharge, 'code' => 'PEC-SCO', 'label' => 'Prise en charge scolaire',          'prefixe_reference' => 'PEC', 'delai_jours' => 5,  'description' => 'Acte notarial d\'engagement de prise en charge des frais de scolarité'],
        ];

        foreach ($types as $data) {
            TypeActe::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['actif' => true])
            );
        }
    }
}
