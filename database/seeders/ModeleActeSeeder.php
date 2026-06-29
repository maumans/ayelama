<?php

namespace Database\Seeders;

use App\Models\ModeleActe;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Database\Seeder;

class ModeleActeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'administrateur')->first();
        $types = TypeActe::pluck('id', 'code');

        $modeles = [
            // ── Société (SOC-SARL) ───────────────────────────────────────
            ['code' => 'SOC-SARL', 'type_document' => 'acte_principal', 'version' => '2.1',
             'nom' => 'Statuts de SARL', 'chemin_fichier' => 'modeles/societe/statuts-sarl.docx'],
            ['code' => 'SOC-SARL', 'type_document' => 'annexe', 'version' => '1.2',
             'nom' => "PV d'assemblée générale constitutive SARL", 'chemin_fichier' => 'modeles/societe/pv-agc-sarl.docx'],
            ['code' => 'SOC-SARL', 'type_document' => 'annexe', 'version' => '1.0',
             'nom' => 'Liste des souscripteurs et parts', 'chemin_fichier' => 'modeles/societe/liste-souscripteurs.docx'],

            // ── Société (SOC-SA) ─────────────────────────────────────────
            ['code' => 'SOC-SA', 'type_document' => 'acte_principal', 'version' => '1.5',
             'nom' => 'Statuts de SA', 'chemin_fichier' => 'modeles/societe/statuts-sa.docx'],
            ['code' => 'SOC-SA', 'type_document' => 'annexe', 'version' => '1.0',
             'nom' => 'Registre des actionnaires SA', 'chemin_fichier' => 'modeles/societe/registre-actionnaires.docx'],

            // ── Dissolution (SOC-DIS) ────────────────────────────────────
            ['code' => 'SOC-DIS', 'type_document' => 'acte_principal', 'version' => '1.0',
             'nom' => 'Acte de dissolution et liquidation', 'chemin_fichier' => 'modeles/societe/dissolution.docx'],

            // ── Vente immobilière (VTE-IMM) ──────────────────────────────
            ['code' => 'VTE-IMM', 'type_document' => 'acte_principal', 'version' => '3.0',
             'nom' => 'Acte authentique de vente immobilière', 'chemin_fichier' => 'modeles/vente/vente-immobiliere.docx'],
            ['code' => 'VTE-IMM', 'type_document' => 'acte_principal', 'version' => '2.0',
             'nom' => 'Promesse synallagmatique de vente', 'chemin_fichier' => 'modeles/vente/promesse-vente.docx'],
            ['code' => 'VTE-IMM', 'type_document' => 'annexe', 'version' => '1.1',
             'nom' => 'Quittance du prix de vente', 'chemin_fichier' => 'modeles/vente/quittance-prix.docx'],

            // ── Cession fonds (VTE-FDS) ──────────────────────────────────
            ['code' => 'VTE-FDS', 'type_document' => 'acte_principal', 'version' => '1.8',
             'nom' => 'Cession de fonds de commerce', 'chemin_fichier' => 'modeles/vente/cession-fonds.docx'],

            // ── Hypothèque (HYP-CON) ─────────────────────────────────────
            ['code' => 'HYP-CON', 'type_document' => 'acte_principal', 'version' => '2.2',
             'nom' => "Constitution d'hypothèque", 'chemin_fichier' => 'modeles/hypotheque/constitution-hyp.docx'],
            ['code' => 'HYP-CON', 'type_document' => 'procedure', 'version' => '1.0',
             'nom' => 'Note de procédure de sûreté réelle', 'chemin_fichier' => 'modeles/hypotheque/procedure-surete.docx'],

            // ── Mainlevée (HYP-MAI) ──────────────────────────────────────
            ['code' => 'HYP-MAI', 'type_document' => 'acte_principal', 'version' => '1.3',
             'nom' => "Mainlevée d'hypothèque", 'chemin_fichier' => 'modeles/hypotheque/mainlevee.docx'],

            // ── Bail commercial (BAI-COM) ─────────────────────────────────
            ['code' => 'BAI-COM', 'type_document' => 'acte_principal', 'version' => '2.0',
             'nom' => 'Bail commercial notarié', 'chemin_fichier' => 'modeles/bail/bail-commercial.docx'],

            // ── Bail habitation (BAI-HAB) ─────────────────────────────────
            ['code' => 'BAI-HAB', 'type_document' => 'acte_principal', 'version' => '1.5',
             'nom' => "Bail d'habitation notarié", 'chemin_fichier' => 'modeles/bail/bail-habitation.docx'],

            // ── Succession (SUC-DEC) ──────────────────────────────────────
            ['code' => 'SUC-DEC', 'type_document' => 'acte_principal', 'version' => '1.0',
             'nom' => 'Déclaration de succession', 'chemin_fichier' => 'modeles/succession/declaration.docx'],
            ['code' => 'SUC-PAR', 'type_document' => 'acte_principal', 'version' => '1.2',
             'nom' => 'Acte de partage successoral', 'chemin_fichier' => 'modeles/succession/partage.docx'],

            // ── Donation (DON-SIM) ────────────────────────────────────────
            ['code' => 'DON-SIM', 'type_document' => 'acte_principal', 'version' => '1.5',
             'nom' => 'Acte de donation simple', 'chemin_fichier' => 'modeles/donation/donation-simple.docx'],
            ['code' => 'DON-PAR', 'type_document' => 'acte_principal', 'version' => '1.0',
             'nom' => 'Donation-partage à titre anticipatif', 'chemin_fichier' => 'modeles/donation/donation-partage.docx'],

            // ── Mariage (MAR-COM) ─────────────────────────────────────────
            ['code' => 'MAR-COM', 'type_document' => 'acte_principal', 'version' => '1.0',
             'nom' => 'Contrat de mariage (régime de communauté)', 'chemin_fichier' => 'modeles/mariage/contrat-mariage.docx'],

            // ── Procurations ──────────────────────────────────────────────
            ['code' => 'PRO-GEN', 'type_document' => 'acte_principal', 'version' => '2.3',
             'nom' => 'Procuration générale', 'chemin_fichier' => 'modeles/procuration/procuration-generale.docx'],
            ['code' => 'PRO-SPE', 'type_document' => 'acte_principal', 'version' => '1.1',
             'nom' => 'Procuration spéciale de vente', 'chemin_fichier' => 'modeles/procuration/procuration-vente.docx'],

            // ── Certificats (COU-CER) ─────────────────────────────────────
            ['code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
             'nom' => 'Certificat de coutume', 'chemin_fichier' => 'modeles/courrier/certificat-coutume.docx'],
            ['code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
             'nom' => 'Lettre de transmission APIP', 'chemin_fichier' => 'modeles/courrier/lettre-transmission-apip.docx'],
            ['code' => 'COU-CER', 'type_document' => 'recepisse', 'version' => '1.0',
             'nom' => 'Récépissé de dépôt de dossier', 'chemin_fichier' => 'modeles/courrier/recepisse-depot.docx'],
        ];

        foreach ($modeles as $data) {
            $typeActeId = $types[$data['code']] ?? null;
            if (! $typeActeId) {
                continue;
            }

            ModeleActe::firstOrCreate(
                ['nom' => $data['nom'], 'type_acte_id' => $typeActeId],
                [
                    'type_document'  => $data['type_document'],
                    'chemin_fichier' => $data['chemin_fichier'],
                    'version'        => $data['version'],
                    'est_actif'      => true,
                    'updated_by'     => $admin?->id,
                ]
            );
        }
    }
}
