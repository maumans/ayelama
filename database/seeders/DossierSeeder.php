<?php

namespace Database\Seeders;

use App\Enums\EtapeDossier;
use App\Enums\StatutRevision;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\JournalActivite;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Revision;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Database\Seeder;

class DossierSeeder extends Seeder
{
    public function run(): void
    {
        $notaire    = User::where('email', 'ayelama.bah@notaire-guinee.com')->first();
        $reviseur   = User::where('email', 'reviseur@ayelama.gn')->first();
        $clerc      = User::where('email', 'nene-aissata.kante@notaire-guinee.com')->first();
        $formaliste = User::where('email', 'ibrahima-sory.fofana@notaire-guinee.com')->first();

        // ── Types d'actes ─────────────────────────────────────────────────────
        $typeVte   = TypeActe::where('code', 'VTE-IMM')->first();
        $typeSarl  = TypeActe::where('code', 'SOC-SARL')->first();
        $typeSarlu = TypeActe::where('code', 'SOC-SARLU')->first();
        $typeHyp   = TypeActe::where('code', 'HYP-CON')->first();
        $typePro   = TypeActe::where('code', 'PRO-GEN')->first();
        $typeSuc   = TypeActe::where('code', 'SUC-DEC')->first();
        $typeBai   = TypeActe::where('code', 'BAI-HAB')->first();
        $typeSas   = TypeActe::where('code', 'SOC-SAS')->first();

        // ─────────────────────────────────────────────────────────────────────
        $dossiers = [

            // ═══════════════════════════════════════════════════════════════
            // VTE-2026-0001 — Vente immobilière avec titre foncier
            // Questionnaire : vente_immeuble (pp.*, acq.*, bien.*, transaction.*)
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'VTE-2026-0001',
                'type_acte_id'  => $typeVte?->id,
                'etape'         => EtapeDossier::Formalites,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Vente terrain résidentiel 800 m² — Kaloum, Conakry',
                'valeur'        => 850000000,
                'echeance'      => now()->addDays(5),
                'notes'         => 'Titre foncier TF-2019-KAL-00421 vérifié à la Conservation Foncière. Pas de charges ni hypothèques.',
                'etape_changed_at' => now()->subDays(2),
                'donnees' => [
                    // Vendeur
                    'pp.civilite'        => 'M.',
                    'pp.prenom_nom'      => 'Mamadou Kouyaté',
                    'pp.nationalite'     => 'Guinéenne',
                    'pp.adresse'         => 'Almamya, Kaloum, Conakry',
                    'pp.piece_type'      => 'CNI CEDEAO',
                    'pp.piece_numero'    => 'GN00123456',
                    'pp.telephone'       => '+224 622 11 22 33',
                    'pp.email'           => 'kouyate.mamadou@gmail.com',
                    // Acquéreur
                    'acq.civilite'       => 'Mme',
                    'acq.prenom_nom'     => 'Aïcha Baldé',
                    'acq.nationalite'    => 'Guinéenne',
                    'acq.adresse'        => 'Nongo, Ratoma, Conakry',
                    'acq.piece_type'     => 'Passeport',
                    'acq.piece_numero'   => 'PA0045678',
                    'acq.telephone'      => '+224 622 44 55 66',
                    'acq.email'          => 'aichabalde@hotmail.com',
                    // Bien immobilier
                    'bien.parcelle_numero'    => 'P-0048',
                    'bien.lot'                => 'Lot 7',
                    'bien.lieu_de'            => 'Almamya, Kaloum, Conakry',
                    'bien.nature_terrain'     => 'Terrain nu',
                    'bien.usage'              => 'Résidentiel',
                    'bien.superficie'         => '800',
                    'bien.pcp'                => 'PCP-KAL-0048',
                    'bien.titre_foncier_numero' => 'TF-2019-KAL-00421',
                    'bien.limite_nord'        => 'Rue de la Paix',
                    'bien.limite_sud'         => 'Parcelle de M. Camara Sékou',
                    'bien.limite_est'         => 'Route Nationale RN1',
                    'bien.limite_ouest'       => "Domaine de l'État",
                    'bien.origine_propriete'  => "Achat selon acte notarié du 12 mars 2010 par-devant Maître Ibrahima SOW, notaire à Conakry",
                    // Transaction
                    'bien.prix_vente_chiffres'               => '850000000',
                    'transaction.taxe_plusvalue_chiffres'    => '42500000',
                    'transaction.provision_chiffres'         => '10000000',
                ],
                'parties' => [
                    [
                        'nom'      => 'Mamadou Kouyaté',
                        'role'     => 'vendeur',
                        'cni'      => 'GN00123456',
                        'telephone'=> '+224 622 11 22 33',
                        'email'    => 'kouyate.mamadou@gmail.com',
                        'adresse'  => 'Almamya, Kaloum, Conakry',
                    ],
                    [
                        'nom'      => 'Aïcha Baldé',
                        'role'     => 'acheteur',
                        'cni'      => 'PA0045678',
                        'telephone'=> '+224 622 44 55 66',
                        'email'    => 'aichabalde@hotmail.com',
                        'adresse'  => 'Nongo, Ratoma, Conakry',
                    ],
                ],
                'formalites' => [
                    [
                        'organisme'       => 'impots',
                        'statut'          => 'depose',
                        'montant_base'    => 850000000,
                        'taux'            => 0.0750,
                        'type_impot'      => 'Droits de mutation (taxe de plus-value)',
                        'retour_attendu'  => 'Quittance de paiement',
                        'delai_heures'    => 72,
                        'depose_at'       => now()->subDays(4),
                        'echeance_at'     => now()->addDays(3),
                    ],
                    [
                        'organisme'       => 'conservation_fonciere',
                        'statut'          => 'en_attente',
                        'montant_base'    => 850000000,
                        'taux'            => 0.0020,
                        'type_impot'      => 'Droit de transcription',
                        'retour_attendu'  => 'Attestation de transcription',
                        'delai_heures'    => 120,
                        'depose_at'       => now()->subDays(2),
                        'echeance_at'     => now()->addDays(5),
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Édition de l\'acte commencée', 'type' => 'etape'],
                    ['action' => 'Titre foncier vérifié à la Conservation Foncière', 'type' => 'etape'],
                    ['action' => 'Acte envoyé en révision', 'type' => 'etape'],
                    ['action' => 'Révision validée par ' . ($reviseur?->name ?? 'le réviseur'), 'type' => 'revision'],
                    ['action' => 'Signature client vendeur apposée', 'type' => 'signature'],
                    ['action' => 'Signature client acquéreur apposée', 'type' => 'signature'],
                    ['action' => 'Dossier transmis au notaire pour signature finale', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // SOC-2026-0001 — Constitution SARL multi-associés
            // Questionnaire : creation_sarl (soc.*, associes[], gerants[])
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'SOC-2026-0001',
                'type_acte_id'  => $typeSarl?->id,
                'etape'         => EtapeDossier::Revision,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Constitution SARL — Négoce Import-Export Guinée',
                'valeur'        => 50000000,
                'echeance'      => now()->addDays(12),
                'notes'         => 'Capital libéré intégralement à l\'ouverture du compte. Attestation Ecobank reçue.',
                'etape_changed_at' => now()->subDays(1),
                'donnees' => [
                    // Société
                    'soc.denomination'           => 'Négoce Import-Export Guinée SARL',
                    'soc.sigle'                  => 'NIEG',
                    'soc.capital_chiffres'       => '50000000',
                    'soc.nombre_parts'           => '1000',
                    'soc.valeur_nominale_chiffres' => '50000',
                    'soc.siege_quartier'         => 'Madina',
                    'soc.siege_commune'          => 'Matam',
                    'soc.siege_ville'            => 'Conakry',
                    'soc.objet_social'           => "Commerce général, négoce, importation et exportation de marchandises en tout genre, fournitures industrielles et commerciales, et toutes activités connexes ou annexes.",
                    'soc.duree'                  => '99',
                    'soc.premier_exercice_annee' => '2026',
                    'soc.email_societe'          => 'contact@nieg.gn',
                    'soc.telephone_societe'      => '+224 625 00 11 22',
                    'soc.commissaire_titulaire'  => 'Cabinet AUDIT CONSEIL GUINÉE',
                    'soc.commissaire_suppleant'  => 'M. Moussa KOUROUMA, Expert-comptable',
                    // Associés (bloc répétable)
                    'associes' => [
                        [
                            'nom'           => 'Ibrahima Barry',
                            'type_personne' => 'Personne physique',
                            'parts_chiffres'=> '600',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Madina, Matam, Conakry',
                            'cni'           => 'GN00998876',
                        ],
                        [
                            'nom'           => 'Mariama Touré',
                            'type_personne' => 'Personne physique',
                            'parts_chiffres'=> '250',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Cosa, Matoto, Conakry',
                            'cni'           => 'GN00887765',
                        ],
                        [
                            'nom'           => 'Oumar Sylla',
                            'type_personne' => 'Personne physique',
                            'parts_chiffres'=> '150',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Bonfi, Matam, Conakry',
                            'cni'           => 'GN00776654',
                        ],
                    ],
                    // Gérant(s) (bloc répétable)
                    'gerants' => [
                        [
                            'civilite'      => 'M.',
                            'prenom_nom'    => 'Ibrahima Barry',
                            'ne_a'          => 'Kindia',
                            'date_naissance'=> '12/04/1978',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Madina, Matam, Conakry',
                            'piece_numero'  => 'GN00998876',
                        ],
                    ],
                ],
                'parties' => [
                    [
                        'nom'      => 'Ibrahima Barry',
                        'role'     => 'gerant',
                        'cni'      => 'GN00998876',
                        'telephone'=> '+224 628 11 22 33',
                        'email'    => 'ibarry@nieg.gn',
                        'adresse'  => 'Madina, Matam, Conakry',
                    ],
                    [
                        'nom'      => 'Mariama Touré',
                        'role'     => 'associe',
                        'cni'      => 'GN00887765',
                        'telephone'=> '+224 628 44 55 66',
                        'email'    => 'mtoure@nieg.gn',
                        'adresse'  => 'Cosa, Matoto, Conakry',
                    ],
                    [
                        'nom'      => 'Oumar Sylla',
                        'role'     => 'associe',
                        'cni'      => 'GN00776654',
                        'telephone'=> '+224 628 77 88 99',
                        'email'    => 'osylla@nieg.gn',
                        'adresse'  => 'Bonfi, Matam, Conakry',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Questionnaire complété — 3 associés', 'type' => 'etape'],
                    ['action' => 'Édition des statuts commencée', 'type' => 'etape'],
                    ['action' => 'Acte transmis en révision', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // SOC-2026-0002 — Constitution SARLU (associé unique)
            // Questionnaire : creation_sarlu (soc.*, pp.*, ger.*)
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'SOC-2026-0002',
                'type_acte_id'  => $typeSarlu?->id,
                'etape'         => EtapeDossier::Edition,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Constitution SARLU — Faya Distribution',
                'valeur'        => 30000000,
                'echeance'      => now()->addDays(20),
                'notes'         => 'Première société du client. Compte bancaire à ouvrir après immatriculation.',
                'etape_changed_at' => now()->subHours(6),
                'donnees' => [
                    // Société
                    'soc.denomination'             => 'Faya Distribution SARLU',
                    'soc.sigle'                    => 'FD',
                    'soc.capital_chiffres'         => '30000000',
                    'soc.nombre_parts'             => '300',
                    'soc.valeur_nominale_chiffres' => '100000',
                    'soc.siege_quartier'           => 'Almamya',
                    'soc.siege_commune'            => 'Kaloum',
                    'soc.siege_ville'              => 'Conakry',
                    'soc.objet_social'             => "Distribution et vente au détail de produits alimentaires, cosmétiques et d'entretien ménager. Importation de marchandises diverses.",
                    'soc.duree'                    => '99',
                    'soc.premier_exercice_annee'   => '2026',
                    'soc.email_societe'            => 'contact@fayadistrib.gn',
                    'soc.telephone_societe'        => '+224 621 33 44 55',
                    // Associé unique (pp.*)
                    'pp.civilite'                  => 'M.',
                    'pp.prenom_nom'                => 'Amadou Fofana',
                    'pp.ne_a'                      => 'Labé',
                    'pp.date_naissance'            => '22/08/1985',
                    'pp.nationalite'               => 'Guinéenne',
                    'pp.situation_matrimoniale'    => 'Marié(e)',
                    'pp.regime_matrimonial'        => 'Communauté de biens',
                    'pp.quartier'                  => 'Almamya',
                    'pp.commune'                   => 'Kaloum',
                    'pp.demeurant_ville'           => 'Conakry',
                    'pp.pays'                      => 'Guinée',
                    'pp.piece_type'                => 'CNI CEDEAO',
                    'pp.piece_numero'              => 'GN00556677',
                    'pp.piece_delivree_le'         => '15/03/2021',
                    'pp.piece_delivree_a'          => 'Conakry',
                    'pp.piece_expire_le'           => '15/03/2031',
                    'pp.telephone'                 => '+224 621 33 44 55',
                    'pp.email'                     => 'amadou.fofana@gmail.com',
                    // Gérant = associé unique (ger.est_different = false → champs masqués)
                    'ger.est_different'            => false,
                ],
                'parties' => [
                    [
                        'nom'      => 'Amadou Fofana',
                        'role'     => 'associe_unique',
                        'cni'      => 'GN00556677',
                        'telephone'=> '+224 621 33 44 55',
                        'email'    => 'amadou.fofana@gmail.com',
                        'adresse'  => 'Almamya, Kaloum, Conakry',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Questionnaire complété — associé unique identifié', 'type' => 'etape'],
                    ['action' => 'Édition des statuts en cours', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // HYP-2026-0001 — Hypothèque conventionnelle (prêt immobilier)
            // Questionnaire : hypotheque_conv (pp.*, bq.*, bien.*)
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'HYP-2026-0001',
                'type_acte_id'  => $typeHyp?->id,
                'etape'         => EtapeDossier::Formalites,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'formaliste_id' => $formaliste?->id,
                'objet'         => 'Hypothèque villa résidentielle 700 m² — Ratoma, Conakry',
                'valeur'        => 1200000000,
                'echeance'      => now()->addDays(2),
                'notes'         => 'Crédit habitat Ecobank — 60 mois. Bien déjà expertisé (rapport Immogn du 10/06/2026).',
                'etape_changed_at' => now()->subDays(3),
                'donnees' => [
                    // Débiteur / Emprunteur
                    'pp.civilite'                => 'M.',
                    'pp.prenom_nom'              => 'Cellou Diallo',
                    'pp.ne_a'                    => 'Pita',
                    'pp.date_naissance'          => '05/11/1972',
                    'pp.nationalite'             => 'Guinéenne',
                    'pp.situation_matrimoniale'  => 'Marié(e)',
                    'pp.regime_matrimonial'      => 'Communauté de biens',
                    'pp.adresse'                 => 'Nongo, Ratoma, Conakry',
                    'pp.piece_type'              => 'CNI CEDEAO',
                    'pp.piece_numero'            => 'GN00334455',
                    'pp.piece_delivree_le'       => '20/01/2019',
                    'pp.piece_delivree_a'        => 'Conakry',
                    'pp.telephone'               => '+224 622 33 44 55',
                    'pp.email'                   => 'cellou.diallo@gmail.com',
                    // Banque / Créancier
                    'bq.denomination'            => 'Ecobank Guinée SA',
                    'bq.forme'                   => 'SA',
                    'bq.siege_quartier'          => 'Kaloum',
                    'bq.siege_commune'           => 'Kaloum',
                    'bq.siege_ville'             => 'Conakry',
                    'bq.representant_nom'        => 'M. Moussa Camara',
                    'bq.representant_qualite'    => 'Directeur Général',
                    'bq.montant_credit_chiffres' => '1200000000',
                    'bq.taux_interet'            => '14',
                    'bq.duree_credit_chiffres'   => '60',
                    'bq.type_garantie'           => 'Affectation hypothécaire de 1er rang',
                    'bq.rang_hypothecaire'       => '1er rang',
                    // Bien hypothéqué
                    'bien.titre_foncier_numero'  => 'TF-2015-RAT-00129',
                    'bien.superficie'            => '700',
                    'bien.lieu_de'               => 'Nongo, Ratoma, Conakry',
                    'bien.nature_terrain'        => 'Immeuble à usage résidentiel (villa R+1)',
                    'bien.limite_nord'           => 'Avenue de Nongo',
                    'bien.limite_sud'            => 'Terrain de M. Bah Ousmane',
                    'bien.limite_est'            => 'Parcelle n°24',
                    'bien.limite_ouest'          => 'Rue secondaire',
                ],
                'parties' => [
                    [
                        'nom'      => 'Cellou Diallo',
                        'role'     => 'debiteur',
                        'cni'      => 'GN00334455',
                        'telephone'=> '+224 622 33 44 55',
                        'email'    => 'cellou.diallo@gmail.com',
                        'adresse'  => 'Nongo, Ratoma, Conakry',
                    ],
                    [
                        'nom'      => 'Ecobank Guinée SA',
                        'role'     => 'creancier',
                        'cni'      => null,
                        'telephone'=> '+224 631 00 00 01',
                        'email'    => 'info@ecobank.com.gn',
                        'adresse'  => 'Avenue de la République, Kaloum, Conakry',
                    ],
                ],
                'formalites' => [
                    [
                        'organisme'      => 'conservation_fonciere',
                        'statut'         => 'depose',
                        'montant_base'   => 1200000000,
                        'taux'           => 0.0025,
                        'type_impot'     => 'Inscription hypothécaire',
                        'retour_attendu' => 'Bordereau d\'inscription',
                        'delai_heures'   => 96,
                        'depose_at'      => now()->subDays(3),
                        'echeance_at'    => now()->addDays(2),
                    ],
                    [
                        'organisme'      => 'impots',
                        'statut'         => 'a_deposer',
                        'montant_base'   => 1200000000,
                        'taux'           => 0.0050,
                        'type_impot'     => 'Droits d\'enregistrement hypothèque',
                        'retour_attendu' => 'Quittance de paiement',
                        'delai_heures'   => 48,
                        'echeance_at'    => now()->addDays(4),
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Acte d\'hypothèque édité', 'type' => 'etape'],
                    ['action' => 'Révision approuvée', 'type' => 'revision'],
                    ['action' => 'Signature client (emprunteur) apposée', 'type' => 'signature'],
                    ['action' => 'Signature représentant Ecobank apposée', 'type' => 'signature'],
                    ['action' => 'Signature notaire apposée', 'type' => 'signature'],
                    ['action' => 'Dossier transmis aux formalités', 'type' => 'etape'],
                    ['action' => 'Dossier déposé à la Conservation Foncière', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // PRO-2026-0001 — Procuration générale
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'PRO-2026-0001',
                'type_acte_id'  => $typePro?->id,
                'etape'         => EtapeDossier::Edition,
                'redacteur_id'  => $clerc?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Procuration générale pour gestion immobilière — Kadiatou Diallo',
                'valeur'        => null,
                'echeance'      => now()->addDays(3),
                'notes'         => 'Mandante basée à Paris. Procuration pour gestion du patrimoine immobilier à Conakry (vente, location, perception de loyers).',
                'etape_changed_at' => now()->subHours(12),
                'donnees' => [
                    'pp.civilite'      => 'Mme',
                    'pp.prenom_nom'    => 'Kadiatou Diallo',
                    'pp.nationalite'   => 'Franco-Guinéenne',
                    'pp.adresse'       => '15 Rue de la Paix, 75001 Paris, France',
                    'pp.piece_type'    => 'Passeport',
                    'pp.piece_numero'  => 'PA0078345',
                    'pp.telephone'     => '+33 6 12 34 56 78',
                    'pp.email'         => 'kadiatou.diallo@email.fr',
                    'mandataire'       => 'Boubacar Diallo',
                    'mandataire_cni'   => 'GN00445566',
                    'mandataire_adr'   => 'Kipé, Ratoma, Conakry',
                    'objet_pouvoir'    => 'Gérer, administrer et disposer de tous biens immobiliers en Guinée : vente, achat, location, signature de tout acte notarié, perception de loyers.',
                ],
                'parties' => [
                    [
                        'nom'      => 'Kadiatou Diallo',
                        'role'     => 'mandant',
                        'cni'      => 'PA0078345',
                        'telephone'=> '+33 6 12 34 56 78',
                        'email'    => 'kadiatou.diallo@email.fr',
                        'adresse'  => '15 Rue de la Paix, 75001 Paris',
                    ],
                    [
                        'nom'      => 'Boubacar Diallo',
                        'role'     => 'mandataire',
                        'cni'      => 'GN00445566',
                        'telephone'=> '+224 621 55 66 77',
                        'email'    => null,
                        'adresse'  => 'Kipé, Ratoma, Conakry',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé — procuration internationale', 'type' => 'creation'],
                    ['action' => 'Pièces d\'identité mandante vérifiées', 'type' => 'etape'],
                    ['action' => 'Édition de la procuration en cours', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // SUC-2026-0001 — Déclaration de succession
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'SUC-2026-0001',
                'type_acte_id'  => $typeSuc?->id,
                'etape'         => EtapeDossier::Cloture,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'formaliste_id' => $formaliste?->id,
                'objet'         => 'Succession de feu Sory Konaté — masse successorale estimée',
                'valeur'        => 200000000,
                'echeance'      => now()->subDays(5),
                'notes'         => 'Héritiers unanimes sur le partage. Pas de contestation. Succession clôturée.',
                'etape_changed_at' => now()->subDays(6),
                'donnees' => [
                    'defunt.civilite'         => 'M.',
                    'defunt.prenom_nom'       => 'Sory Konaté',
                    'defunt.date_naissance'   => '10/05/1945',
                    'defunt.date_deces'       => '14/03/2026',
                    'defunt.lieu_deces'       => 'CHU Ignace Deen, Conakry',
                    'defunt.nationalite'      => 'Guinéenne',
                    'defunt.adresse'          => 'Kipé, Ratoma, Conakry',
                    'defunt.situation_matrimoniale' => 'Marié(e)',
                    'defunt.regime_matrimonial'     => 'Communauté de biens',
                    'masse.biens_immobiliers' => '180000000',
                    'masse.biens_mobiliers'   => '20000000',
                    'masse.dettes'            => '0',
                    'partage.modalite'        => 'Partage égal entre les héritiers',
                ],
                'parties' => [
                    [
                        'nom'      => 'Fatoumata Konaté',
                        'role'     => 'heritier',
                        'cni'      => 'GN00211111',
                        'telephone'=> '+224 622 00 11 22',
                        'email'    => 'fatoumata.konate@gmail.com',
                        'adresse'  => 'Kipé, Ratoma, Conakry',
                    ],
                    [
                        'nom'      => 'Sekou Konaté',
                        'role'     => 'heritier',
                        'cni'      => 'GN00211112',
                        'telephone'=> '+224 622 00 11 23',
                        'email'    => null,
                        'adresse'  => 'Bambeto, Ratoma, Conakry',
                    ],
                    [
                        'nom'      => 'Aminata Konaté',
                        'role'     => 'heritier',
                        'cni'      => 'GN00211113',
                        'telephone'=> '+224 622 00 11 24',
                        'email'    => null,
                        'adresse'  => 'Matam, Conakry',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé — ouverture de succession', 'type' => 'creation'],
                    ['action' => 'Acte de décès et pièces héritiers vérifiés', 'type' => 'etape'],
                    ['action' => 'Déclaration de succession rédigée', 'type' => 'etape'],
                    ['action' => 'Révision validée', 'type' => 'revision'],
                    ['action' => 'Signatures des 3 héritiers apposées', 'type' => 'signature'],
                    ['action' => 'Déclaration transmise aux Impôts', 'type' => 'etape'],
                    ['action' => 'Quittance fiscale reçue', 'type' => 'etape'],
                    ['action' => 'Dossier clôturé', 'type' => 'cloture'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // BAI-2026-0001 — Bail d'habitation notarié
            // Questionnaire : bail_habitation (pp.*, loc.*, bien.*, bail.*)
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'BAI-2026-0001',
                'type_acte_id'  => $typeBai?->id,
                'etape'         => EtapeDossier::Revision,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Bail habitation — Villa 5 pièces Kipé, Ratoma',
                'valeur'        => 144000000,
                'echeance'      => now()->addDays(7),
                'notes'         => 'Caution de 3 mois versée. Remise des clés prévue au 01/08/2026.',
                'etape_changed_at' => now()->subDays(1),
                'donnees' => [
                    // Bailleur
                    'pp.civilite'        => 'M. et Mme',
                    'pp.prenom_nom'      => 'Elhadj Boubacar Bah et Mariama Diallo',
                    'pp.nationalite'     => 'Guinéenne',
                    'pp.adresse'         => 'Kipé, Ratoma, Conakry',
                    'pp.piece_type'      => 'CNI CEDEAO',
                    'pp.piece_numero'    => 'GN00667788',
                    'pp.telephone'       => '+224 622 88 99 00',
                    'pp.email'           => 'elhbah@gmail.com',
                    // Locataire
                    'loc.civilite'       => 'Société',
                    'loc.prenom_nom'     => 'Orange Guinée SA',
                    'loc.nationalite'    => 'Guinée',
                    'loc.adresse'        => 'Coleah, Matam, Conakry',
                    'loc.piece_type'     => 'RCCM',
                    'loc.piece_numero'   => 'GN-CON-2004-B-00012',
                    'loc.telephone'      => '+224 631 60 00 00',
                    'loc.email'          => 'juridique@orange.gn',
                    // Bien
                    'bien.adresse'       => 'Kipé, Ratoma, Conakry',
                    'bien.description'   => 'Villa R+1, 5 pièces, salon, cuisine, 2 salles de bain, piscine, gardiennage. Surface totale : 420 m².',
                    'bien.superficie'    => '420',
                    'bien.usage'         => 'Résidentiel',
                    // Conditions du bail
                    'bail.date_prise_effet'  => '01/08/2026',
                    'bail.duree_chiffres'    => '3',
                    'bail.loyer_chiffres'    => '4000000',
                    'bail.periodicite'       => 'Mensuel',
                    'bail.caution_chiffres'  => '12000000',
                    'bail.avance_loyer'      => '3',
                    'bail.destination'       => 'Logement de fonction pour cadre expatrié',
                ],
                'parties' => [
                    [
                        'nom'      => 'Elhadj Boubacar Bah',
                        'role'     => 'bailleur',
                        'cni'      => 'GN00667788',
                        'telephone'=> '+224 622 88 99 00',
                        'email'    => 'elhbah@gmail.com',
                        'adresse'  => 'Kipé, Ratoma, Conakry',
                    ],
                    [
                        'nom'      => 'Orange Guinée SA',
                        'role'     => 'locataire',
                        'cni'      => 'GN-CON-2004-B-00012',
                        'telephone'=> '+224 631 60 00 00',
                        'email'    => 'juridique@orange.gn',
                        'adresse'  => 'Coleah, Matam, Conakry',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Bail rédigé — durée 3 ans', 'type' => 'etape'],
                    ['action' => 'Révision approuvée', 'type' => 'revision'],
                    ['action' => 'Signature bailleur apposée', 'type' => 'signature'],
                    ['action' => 'En attente signature locataire (Orange Guinée)', 'type' => 'etape'],
                ],
            ],

            // ═══════════════════════════════════════════════════════════════
            // SOC-2026-0003 — Constitution SAS
            // Questionnaire : creation_sas (soc.*, associes[], president)
            // ═══════════════════════════════════════════════════════════════
            [
                'reference'     => 'SOC-2026-0003',
                'type_acte_id'  => $typeSas?->id,
                'etape'         => EtapeDossier::Initialisation,
                'redacteur_id'  => $clerc?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Constitution SAS — Guinée Tech Innovation',
                'valeur'        => 200000000,
                'echeance'      => now()->addDays(30),
                'notes'         => 'Nouveau dossier. Apport en industrie envisagé pour l\'un des associés. Vérifier conditions OHADA.',
                'etape_changed_at' => now(),
                'donnees' => [
                    'soc.denomination'             => 'Guinée Tech Innovation SAS',
                    'soc.sigle'                    => 'GTI',
                    'soc.capital_chiffres'         => '200000000',
                    'soc.nombre_parts'             => '2000',
                    'soc.valeur_nominale_chiffres' => '100000',
                    'soc.siege_quartier'           => 'Kipé',
                    'soc.siege_commune'            => 'Ratoma',
                    'soc.siege_ville'              => 'Conakry',
                    'soc.objet_social'             => "Développement de logiciels et applications informatiques, conseil en systèmes d'information, formation professionnelle en technologies numériques.",
                    'soc.duree'                    => '99',
                    'soc.premier_exercice_annee'   => '2026',
                    'soc.email_societe'            => 'contact@guineetech.gn',
                    'soc.telephone_societe'        => '+224 625 77 88 99',
                    // Associés
                    'associes' => [
                        [
                            'nom'           => 'Mamadou Alpha Diallo',
                            'type_personne' => 'Personne physique',
                            'parts_chiffres'=> '1000',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Kipé, Ratoma, Conakry',
                            'cni'           => 'GN00112233',
                        ],
                        [
                            'nom'           => 'Fatoumata Sylla',
                            'type_personne' => 'Personne physique',
                            'parts_chiffres'=> '700',
                            'nationalite'   => 'Guinéenne',
                            'adresse'       => 'Kaloum, Conakry',
                            'cni'           => 'GN00223344',
                        ],
                        [
                            'nom'           => 'Investissement Numérique Afrique SARL',
                            'type_personne' => 'Personne morale',
                            'parts_chiffres'=> '300',
                            'nationalite'   => 'Sénégal',
                            'adresse'       => 'Dakar, Sénégal',
                            'cni'           => 'SN-DKR-2022-B-04567',
                        ],
                    ],
                    // Président
                    'soc.president_nom'           => 'Mamadou Alpha Diallo',
                    'soc.president_civilite'      => 'M.',
                    'soc.president_ne_a'          => 'Conakry',
                    'soc.president_date_naissance'=> '03/03/1988',
                    'soc.president_nationalite'   => 'Guinéenne',
                    'soc.president_adresse'       => 'Kipé, Ratoma, Conakry',
                    'soc.president_piece_numero'  => 'GN00112233',
                ],
                'parties' => [
                    [
                        'nom'      => 'Mamadou Alpha Diallo',
                        'role'     => 'president',
                        'cni'      => 'GN00112233',
                        'telephone'=> '+224 625 77 88 99',
                        'email'    => 'malpha@guineetech.gn',
                        'adresse'  => 'Kipé, Ratoma, Conakry',
                    ],
                    [
                        'nom'      => 'Fatoumata Sylla',
                        'role'     => 'associe',
                        'cni'      => 'GN00223344',
                        'telephone'=> '+224 624 11 22 33',
                        'email'    => 'fsylla@gmail.com',
                        'adresse'  => 'Kaloum, Conakry',
                    ],
                    [
                        'nom'      => 'Investissement Numérique Afrique SARL',
                        'role'     => 'associe',
                        'cni'      => 'SN-DKR-2022-B-04567',
                        'telephone'=> '+221 33 123 45 67',
                        'email'    => 'contact@ina.sn',
                        'adresse'  => 'Dakar, Sénégal',
                    ],
                ],
                'journal' => [
                    ['action' => 'Dossier créé — SAS avec associé personne morale étrangère', 'type' => 'creation'],
                ],
            ],
        ];

        // ── Création en base ──────────────────────────────────────────────────
        foreach ($dossiers as $data) {
            if (Dossier::where('reference', $data['reference'])->exists()) {
                continue;
            }

            // Ne pas créer si le TypeActe est manquant en base
            if (! $data['type_acte_id']) {
                continue;
            }

            $dossier = Dossier::create([
                'reference'        => $data['reference'],
                'type_acte_id'     => $data['type_acte_id'],
                'etape'            => $data['etape'],
                'redacteur_id'     => $data['redacteur_id'],
                'reviseur_id'      => $data['reviseur_id'] ?? null,
                'notaire_id'       => $data['notaire_id'] ?? null,
                'formaliste_id'    => $data['formaliste_id'] ?? null,
                'objet'            => $data['objet'],
                'valeur'           => $data['valeur'],
                'echeance'         => $data['echeance'],
                'notes'            => $data['notes'] ?? null,
                'etape_changed_at' => $data['etape_changed_at'] ?? null,
            ]);

            // Questionnaire
            Questionnaire::create([
                'dossier_id' => $dossier->id,
                'donnees'    => $data['donnees'] ?? [],
            ]);

            // Parties
            foreach ($data['parties'] ?? [] as $partieData) {
                Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));
            }

            // Formalités
            foreach ($data['formalites'] ?? [] as $formaliteData) {
                $formalite = Formalite::create(array_merge(['dossier_id' => $dossier->id], $formaliteData));
                if ($formalite->taux && $formalite->montant_base) {
                    $formalite->calculerMontant();
                }
            }

            // Révision pour les étapes post-édition
            $etapesAvecRevision = [
                EtapeDossier::Revision,
                EtapeDossier::Formalites,
                EtapeDossier::Expedition,
                EtapeDossier::Cloture,
            ];

            if (in_array($dossier->etape, $etapesAvecRevision)) {
                $statut = $dossier->etape === EtapeDossier::Revision
                    ? StatutRevision::EnCours
                    : StatutRevision::Valide;

                Revision::create([
                    'dossier_id'  => $dossier->id,
                    'reviseur_id' => $data['reviseur_id'] ?? null,
                    'statut'      => $statut,
                    'commentaire' => null,
                ]);
            }

            // Journal d'activités
            foreach ($data['journal'] as $entry) {
                JournalActivite::create([
                    'dossier_id' => $dossier->id,
                    'user_id'    => $clerc?->id,
                    'action'     => $entry['action'],
                    'type'       => $entry['type'],
                    'meta'       => [],
                    'created_at' => now()->subMinutes(rand(10, 10000)),
                ]);
            }
        }
    }
}
