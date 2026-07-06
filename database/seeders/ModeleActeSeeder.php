<?php

namespace Database\Seeders;

use App\Models\ModeleActe;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seed les modèles d'actes en copiant les fichiers depuis Documents reçus/.
 *
 * Règle :
 *   - source .docx existante → copie vers storage/local, est_actif = true
 *   - source .doc  ou introuvable → pas de copie, est_actif = false (à convertir)
 *   - source .xls  → ignoré (factures Excel gérées séparément)
 */
class ModeleActeSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'administrateur')->first();
        $types = TypeActe::pluck('id', 'code');

        // Répertoire source — relatif à la racine du projet
        $srcBase = base_path('Documents reçus');

        // ──────────────────────────────────────────────────────────────────────
        // Mapping : source → destination + métadonnées ModeleActe
        //   'src'  : chemin relatif à $srcBase (séparateur /)
        //   'dest' : chemin dans Storage::disk('local') (= modeles/...)
        // ──────────────────────────────────────────────────────────────────────
        $mappings = [

            // ════════════════════════════════════════════════════════════════
            // SARLU
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SOC-SARLU', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Statuts SARLU',
                'src'  => 'SARLU/STATUTS_SARLU_balises.docx',   // version avec balises
                'dest' => 'modeles/societe/statuts-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde SARLU',
                'src'  => 'SARLU/PAGE DE GARDE.doc',             // .doc → inactif
                'dest' => 'modeles/societe/page-garde-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'attestation', 'version' => '1.0',
                'nom'  => 'Attestation de dépôt du capital SARLU',
                'src'  => 'SARLU/ATTESTATION DE DEPÔT DU CAPITAL.docx',
                'dest' => 'modeles/societe/attestation-capital-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'declaration', 'version' => '1.0',
                'nom'  => "Déclaration sur l'honneur SARLU",
                'src'  => "SARLU/DECLARATION SUR L'HONNEUR -.doc", // .doc → inactif
                'dest' => 'modeles/societe/declaration-honneur-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'dnsv', 'version' => '1.0',
                'nom'  => 'DNSV SARLU',
                'src'  => 'SARLU/DNSV.docx',
                'dest' => 'modeles/societe/dnsv-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SARLU',
                'src'  => 'SARLU/INSERTION.doc',                 // .doc → inactif
                'dest' => 'modeles/societe/insertion-sarlu.docx',
            ],
            [
                'code' => 'SOC-SARLU', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SARLU',
                'src'  => 'SARLU/RCCM.doc',                     // .doc → inactif
                'dest' => 'modeles/societe/rccm-sarlu.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // SARL
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SOC-SARL', 'type_document' => 'acte_principal', 'version' => '2.1',
                'nom'  => 'Statuts de SARL',
                'src'  => 'SARL/STATUTS.doc',                   // .doc → inactif
                'dest' => 'modeles/societe/statuts-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde SARL',
                'src'  => 'SARL/PAGE DE GARDE.doc',              // .doc → inactif
                'dest' => 'modeles/societe/page-garde-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'attestation', 'version' => '1.0',
                'nom'  => 'Attestation de dépôt du capital SARL',
                'src'  => 'SARL/ATTESTATION DE DEPÔT DU CAPITAL.docx',
                'dest' => 'modeles/societe/attestation-capital-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'declaration', 'version' => '1.0',
                'nom'  => "Déclaration sur l'honneur SARL",
                'src'  => "SARL/DECLARATION SUR L'HONNEUR -.doc", // .doc → inactif
                'dest' => 'modeles/societe/declaration-honneur-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'dnsv', 'version' => '1.0',
                'nom'  => 'DNSV SARL',
                'src'  => 'SARL/DNSV.docx',
                'dest' => 'modeles/societe/dnsv-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SARL',
                'src'  => 'SARL/INSERTION.doc',                  // .doc → inactif
                'dest' => 'modeles/societe/insertion-sarl.docx',
            ],
            [
                'code' => 'SOC-SARL', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SARL',
                'src'  => 'SARL/RCCM 1.doc',                    // .doc → inactif
                'dest' => 'modeles/societe/rccm-sarl.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // SAS
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SOC-SAS', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Statuts SAS',
                'src'  => 'SAS/STATUTS.doc',                    // .doc → inactif
                'dest' => 'modeles/societe/statuts-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde SAS',
                'src'  => 'SAS/PAGE DE GARDE.doc',              // .doc → inactif
                'dest' => 'modeles/societe/page-garde-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'attestation', 'version' => '1.0',
                'nom'  => 'Attestation de dépôt du capital SAS',
                'src'  => 'SAS/ATTESTATION DE DEPOT.docx',
                'dest' => 'modeles/societe/attestation-capital-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'declaration', 'version' => '1.0',
                'nom'  => "Déclaration sur l'honneur SAS",
                'src'  => "SAS/DECLARATION SUR L'HONNEUR -.doc", // .doc → inactif
                'dest' => 'modeles/societe/declaration-honneur-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'dnsv', 'version' => '1.0',
                'nom'  => 'DNSV SAS',
                'src'  => 'SAS/DNSV.docx',
                'dest' => 'modeles/societe/dnsv-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SAS',
                'src'  => 'SAS/INSERTION.doc',                  // .doc → inactif
                'dest' => 'modeles/societe/insertion-sas.docx',
            ],
            [
                'code' => 'SOC-SAS', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SAS',
                'src'  => 'SAS/RCCM 1.doc',                    // .doc → inactif
                'dest' => 'modeles/societe/rccm-sas.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // SASU
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SOC-SASU', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Statuts SASU',
                'src'  => 'SASU/STATUTS SASU.doc',              // .doc → inactif
                'dest' => 'modeles/societe/statuts-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde SASU',
                'src'  => 'SASU/PAGE DE GARDE.doc',             // .doc → inactif
                'dest' => 'modeles/societe/page-garde-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'attestation', 'version' => '1.0',
                'nom'  => 'Attestation de dépôt du capital SASU',
                'src'  => 'SASU/ATTESTATION DE DEPOT.docx',
                'dest' => 'modeles/societe/attestation-capital-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'declaration', 'version' => '1.0',
                'nom'  => "Déclaration sur l'honneur SASU",
                'src'  => "SASU/DECLARATION SUR L'HONNEUR -.doc", // .doc → inactif
                'dest' => 'modeles/societe/declaration-honneur-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'dnsv', 'version' => '1.0',
                'nom'  => 'DNSV SASU',
                'src'  => 'SASU/DNSV.docx',
                'dest' => 'modeles/societe/dnsv-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SASU',
                'src'  => 'SASU/INSERTION.doc',                 // .doc → inactif
                'dest' => 'modeles/societe/insertion-sasu.docx',
            ],
            [
                'code' => 'SOC-SASU', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SASU',
                'src'  => 'SASU/RCCM 1.doc',                   // .doc → inactif
                'dest' => 'modeles/societe/rccm-sasu.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // SA, SNC, GIE, DIS  — pas de fichiers sources, placeholder actif
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SOC-SA', 'type_document' => 'acte_principal', 'version' => '1.5',
                'nom'  => 'Statuts de SA',
                'src'  => null,
                'dest' => 'modeles/societe/statuts-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde SA',
                'src'  => null,
                'dest' => 'modeles/societe/page-garde-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'attestation', 'version' => '1.0',
                'nom'  => 'Attestation de dépôt du capital SA',
                'src'  => null,
                'dest' => 'modeles/societe/attestation-capital-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'declaration', 'version' => '1.0',
                'nom'  => "Déclaration sur l'honneur SA",
                'src'  => null,
                'dest' => 'modeles/societe/declaration-honneur-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'dnsv', 'version' => '1.0',
                'nom'  => 'DNSV SA',
                'src'  => null,
                'dest' => 'modeles/societe/dnsv-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SA',
                'src'  => null,
                'dest' => 'modeles/societe/insertion-sa.docx',
            ],
            [
                'code' => 'SOC-SA', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SA',
                'src'  => null,
                'dest' => 'modeles/societe/rccm-sa.docx',
            ],
            [
                'code' => 'SOC-SNC', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Statuts SNC',
                'src'  => null,
                'dest' => 'modeles/societe/statuts-snc.docx',
            ],
            [
                'code' => 'SOC-SNC', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG SNC',
                'src'  => null,
                'dest' => 'modeles/societe/insertion-snc.docx',
            ],
            [
                'code' => 'SOC-SNC', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM SNC',
                'src'  => null,
                'dest' => 'modeles/societe/rccm-snc.docx',
            ],
            [
                'code' => 'SOC-GIE', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Acte constitutif GIE',
                'src'  => null,
                'dest' => 'modeles/societe/statuts-gie.docx',
            ],
            [
                'code' => 'SOC-GIE', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG GIE',
                'src'  => null,
                'dest' => 'modeles/societe/insertion-gie.docx',
            ],
            [
                'code' => 'SOC-GIE', 'type_document' => 'rccm', 'version' => '1.0',
                'nom'  => 'RCCM GIE',
                'src'  => null,
                'dest' => 'modeles/societe/rccm-gie.docx',
            ],
            [
                'code' => 'SOC-DIS', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Acte de dissolution et liquidation',
                'src'  => null,
                'dest' => 'modeles/societe/dissolution.docx',
            ],
            [
                'code' => 'SOC-DIS', 'type_document' => 'insertion', 'version' => '1.0',
                'nom'  => 'Insertion au JORG dissolution',
                'src'  => null,
                'dest' => 'modeles/societe/insertion-dissolution.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // VENTE AVEC TITRE FONCIER (VTE-IMM)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'VTE-IMM', 'type_document' => 'acte_principal', 'version' => '3.0',
                'nom'  => 'Acte authentique de vente immobilière',
                'src'  => "Modèles d'actes de vente avec titre foncier/CONTRAT DE VENTE .doc", // .doc → inactif
                'dest' => 'modeles/vente/vente-immobiliere.docx',
            ],
            [
                'code' => 'VTE-IMM', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde vente immobilière',
                'src'  => "Modèles d'actes de vente avec titre foncier/PAGE DE GARDE.docx",
                'dest' => 'modeles/vente/page-garde-vente-tf.docx',
            ],
            [
                'code' => 'VTE-IMM', 'type_document' => 'bordereau', 'version' => '1.0',
                'nom'  => 'Tableau de bordereau vente',
                'src'  => "Modèles d'actes de vente avec titre foncier/TABLEAU DE BORDEREAU.doc", // .doc → inactif
                'dest' => 'modeles/vente/bordereau-vente.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // VENTE SANS TITRE FONCIER (VTE-SAN)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'VTE-SAN', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Acte authentique de vente immobilière sans titre foncier',
                'src'  => "Modèles d'actes de vente sans titre foncier/CONTRAT DE VENTE .doc", // .doc → inactif
                'dest' => 'modeles/vente/vente-sans-titre.docx',
            ],
            [
                'code' => 'VTE-SAN', 'type_document' => 'page_garde', 'version' => '1.0',
                'nom'  => 'Page de garde vente sans titre foncier',
                'src'  => "Modèles d'actes de vente sans titre foncier/PAGE DE GARDE.docx",
                'dest' => 'modeles/vente/page-garde-vente-san.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // CESSION FONDS DE COMMERCE (VTE-FDS)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'VTE-FDS', 'type_document' => 'acte_principal', 'version' => '1.8',
                'nom'  => 'Cession de fonds de commerce',
                'src'  => null,
                'dest' => 'modeles/vente/cession-fonds.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // BAIL D'HABITATION (BAI-HAB)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'BAI-HAB', 'type_document' => 'acte_principal', 'version' => '1.5',
                'nom'  => "Bail d'habitation notarié",
                'src'  => 'Contrat de bail à habitation/CONTRAT DE BAIL.docx',
                'dest' => 'modeles/bail/bail-habitation.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // BAIL COMMERCIAL (BAI-COM)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'BAI-COM', 'type_document' => 'acte_principal', 'version' => '2.0',
                'nom'  => 'Bail commercial notarié',
                'src'  => null,
                'dest' => 'modeles/bail/bail-commercial.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // BAIL À CONSTRUCTION (BAI-CON)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'BAI-CON', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Bail à construction notarié',
                'src'  => 'modele bail a construction/BAIL A CONSTRUCTION.docx',
                'dest' => 'modeles/bail/bail-construction.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // HYPOTHÈQUE CONVENTIONNELLE (HYP-CON)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'HYP-CON', 'type_document' => 'acte_principal', 'version' => '2.2',
                'nom'  => "Constitution d'hypothèque",
                'src'  => null,
                'dest' => 'modeles/hypotheque/constitution-hyp.docx',
            ],
            [
                'code' => 'HYP-CON', 'type_document' => 'procedure', 'version' => '1.0',
                'nom'  => 'Note de procédure de sûreté réelle',
                'src'  => null,
                'dest' => 'modeles/hypotheque/procedure-surete.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // MAINLEVÉE (HYP-MAI)
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'HYP-MAI', 'type_document' => 'acte_principal', 'version' => '1.3',
                'nom'  => "Mainlevée d'hypothèque",
                'src'  => null,
                'dest' => 'modeles/hypotheque/mainlevee.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // SUCCESSION / DONATION / MARIAGE
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'SUC-DEC', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Déclaration de succession',
                'src'  => null,
                'dest' => 'modeles/succession/declaration.docx',
            ],
            [
                'code' => 'SUC-PAR', 'type_document' => 'acte_principal', 'version' => '1.2',
                'nom'  => 'Acte de partage successoral',
                'src'  => null,
                'dest' => 'modeles/succession/partage.docx',
            ],
            [
                'code' => 'DON-SIM', 'type_document' => 'acte_principal', 'version' => '1.5',
                'nom'  => 'Acte de donation simple',
                'src'  => null,
                'dest' => 'modeles/donation/donation-simple.docx',
            ],
            [
                'code' => 'DON-PAR', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Donation-partage à titre anticipatif',
                'src'  => null,
                'dest' => 'modeles/donation/donation-partage.docx',
            ],
            [
                'code' => 'MAR-COM', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Contrat de mariage (régime de communauté)',
                'src'  => null,
                'dest' => 'modeles/mariage/contrat-mariage.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // PROCURATIONS
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'PRO-GEN', 'type_document' => 'acte_principal', 'version' => '2.3',
                'nom'  => 'Procuration générale',
                'src'  => null,
                'dest' => 'modeles/procuration/procuration-generale.docx',
            ],
            [
                'code' => 'PRO-SPE', 'type_document' => 'acte_principal', 'version' => '1.1',
                'nom'  => 'Procuration spéciale de vente',
                'src'  => null,
                'dest' => 'modeles/procuration/procuration-vente.docx',
            ],

            // ════════════════════════════════════════════════════════════════
            // COURRIER / TRANSMISSION (COU-CER) — tous .docx → tous actifs
            // ════════════════════════════════════════════════════════════════
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Transmission vente bien immeuble',
                'src'  => 'Courrier de transmission/TRANSMISSION VENTE BIEN IMMEUBLE.docx',
                'dest' => 'modeles/courrier/transmission-vente-bien-immeuble.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Transmission actes (minute)',
                'src'  => 'Courrier de transmission/TRANSMISSION ACTES (MINUTE).docx',
                'dest' => 'modeles/courrier/transmission-actes-minute.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Transmission certificat authentique société',
                'src'  => 'Courrier de transmission/TRANSMISSION C.AUTH. SOCIETE.docx',
                'dest' => 'modeles/courrier/transmission-cauth-societe.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Transmission modification société',
                'src'  => 'Courrier de transmission/Transmission Modification société.docx',
                'dest' => 'modeles/courrier/transmission-modification-societe.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Transmission modification avec acte de dépôt du PV',
                'src'  => 'Courrier de transmission/Transmission MODIF AVEC ACTE DE DEPOT DU PV.docx',
                'dest' => 'modeles/courrier/transmission-modif-acte-depot-pv.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => "Transmission mainlevée d'hypothèque",
                'src'  => "Courrier de transmission/Transmission Mainlevée d'hypo..docx",
                'dest' => 'modeles/courrier/transmission-mainlevee-hypo.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Accusé de réception banque',
                'src'  => 'Courrier de transmission/Accusé de reception BANQUE.docx',
                'dest' => 'modeles/courrier/accuse-reception-banque.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Accusé de réception société',
                'src'  => 'Courrier de transmission/Accusé reception SOCIETE.docx',
                'dest' => 'modeles/courrier/accuse-reception-societe.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Accusé de réception contrat de prêt immobilier',
                'src'  => 'Courrier de transmission/ACCUSEE RECEPTION CONTRAT DE PRET IMMOBILIER.docx',
                'dest' => 'modeles/courrier/accuse-reception-pret-immobilier.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Réquisition conservation foncière',
                'src'  => 'Courrier de transmission/Réquisition  conservation foncière.docx',
                'dest' => 'modeles/courrier/requisition-conservation-fonciere.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'lettre', 'version' => '1.0',
                'nom'  => 'Mise en place de crédit',
                'src'  => 'Courrier de transmission/MISE EN PLACE  DE CREDIT.docx',
                'dest' => 'modeles/courrier/mise-en-place-credit.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'acte_principal', 'version' => '1.0',
                'nom'  => 'Contrat de prêt bancaire',
                'src'  => 'Courrier de transmission/CONTRAT DE PRET LA BANQUE.docx',
                'dest' => 'modeles/courrier/contrat-pret-banque.docx',
            ],
            [
                'code' => 'COU-CER', 'type_document' => 'recepisse', 'version' => '1.0',
                'nom'  => 'Récépissé de dépôt de dossier',
                'src'  => null,
                'dest' => 'modeles/courrier/recepisse-depot.docx',
            ],
        ];

        // ──────────────────────────────────────────────────────────────────────
        // Traitement : copie + création des enregistrements
        // ──────────────────────────────────────────────────────────────────────
        foreach ($mappings as $m) {
            $typeActeId = $types[$m['code']] ?? null;
            if (! $typeActeId) {
                continue;
            }

            $estActif = false;

            // Tenter la copie si une source est définie
            if ($m['src'] !== null) {
                $srcAbs = $srcBase . DIRECTORY_SEPARATOR
                    . str_replace('/', DIRECTORY_SEPARATOR, $m['src']);

                $srcExt = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));

                if ($srcExt === 'docx' && file_exists($srcAbs)) {
                    $destAbs = Storage::disk('local')->path($m['dest']);
                    $destDir = dirname($destAbs);

                    if (! is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }

                    if (! file_exists($destAbs)) {
                        copy($srcAbs, $destAbs);
                    }

                    $estActif = true;
                }
                // .doc existant → enregistrement inactif, fichier non copié
                // (PhpWord ne supporte pas le format .doc binaire)
            }
            // src === null → placeholder, est_actif = false

            ModeleActe::firstOrCreate(
                ['nom' => $m['nom'], 'type_acte_id' => $typeActeId],
                [
                    'type_document'  => $m['type_document'],
                    'chemin_fichier' => $m['dest'],
                    'version'        => $m['version'],
                    'est_actif'      => $estActif,
                    'updated_by'     => $admin?->id,
                ]
            );
        }
    }
}
