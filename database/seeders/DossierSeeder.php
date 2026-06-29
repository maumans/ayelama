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
        $notaire   = User::where('email', 'notaire@ayelema.gn')->first();
        $reviseur  = User::where('email', 'reviseur@ayelema.gn')->first();
        $clerc     = User::where('email', 'clerc@ayelema.gn')->first();
        $formaliste = User::where('email', 'formaliste@ayelema.gn')->first();

        $typeVente  = TypeActe::where('code', 'VTE-IMM')->first();
        $typeSarl   = TypeActe::where('code', 'SOC-SARL')->first();
        $typeHyp    = TypeActe::where('code', 'HYP-CON')->first();
        $typePro    = TypeActe::where('code', 'PRO-GEN')->first();
        $typeSuc    = TypeActe::where('code', 'SUC-DEC')->first();

        $dossiers = [
            [
                'reference'     => 'VTE-2026-0001',
                'type_acte_id'  => $typeVente?->id,
                'etape'         => EtapeDossier::SignatureNotaire,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Vente d\'un terrain à Kaloum, Conakry',
                'valeur'        => 85000000,
                'echeance'      => now()->addDays(5),
                'parties'       => [
                    ['nom' => 'Mamadou Kouyaté', 'role' => 'vendeur', 'cni' => 'CNI-GN-001234', 'telephone' => '+224 622 11 22 33'],
                    ['nom' => 'Aïcha Baldé',     'role' => 'acheteur', 'cni' => 'CNI-GN-005678', 'telephone' => '+224 622 44 55 66'],
                ],
                'journal'       => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Édition de l\'acte commencée', 'type' => 'etape'],
                    ['action' => 'Acte envoyé en révision', 'type' => 'etape'],
                    ['action' => 'Révision validée par ' . $reviseur?->name, 'type' => 'revision'],
                    ['action' => 'Signature client apposée', 'type' => 'signature'],
                ],
            ],
            [
                'reference'     => 'SOC-2026-0001',
                'type_acte_id'  => $typeSarl?->id,
                'etape'         => EtapeDossier::Revision,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Constitution SARL Négoce Import-Export Guinée',
                'valeur'        => 50000000,
                'echeance'      => now()->addDays(12),
                'parties'       => [
                    ['nom' => 'Ibrahima Barry',   'role' => 'gerant',       'cni' => 'CNI-GN-009876', 'telephone' => '+224 628 11 22 33'],
                    ['nom' => 'Mariama Touré',    'role' => 'associe',      'cni' => 'CNI-GN-009877', 'telephone' => '+224 628 44 55 66'],
                    ['nom' => 'Oumar Sylla',      'role' => 'associe',      'cni' => 'CNI-GN-009878', 'telephone' => '+224 628 77 88 99'],
                ],
                'journal'       => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Édition de l\'acte commencée', 'type' => 'etape'],
                    ['action' => 'Acte transmis en révision', 'type' => 'etape'],
                ],
            ],
            [
                'reference'     => 'HYP-2026-0001',
                'type_acte_id'  => $typeHyp?->id,
                'etape'         => EtapeDossier::Formalites,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'formaliste_id' => $formaliste?->id,
                'objet'         => 'Hypothèque villa résidentielle Ratoma',
                'valeur'        => 120000000,
                'echeance'      => now()->addDays(2),
                'formalites'    => [
                    [
                        'organisme'    => 'conservation_fonciere',
                        'statut'       => 'depose',
                        'montant_base' => 120000000,
                        'taux'         => 0.0025,
                        'depose_at'    => now()->subDays(3),
                        'echeance_at'  => now()->addDays(2),
                    ],
                    [
                        'organisme'   => 'impots',
                        'statut'      => 'a_deposer',
                        'montant_base' => 120000000,
                        'taux'        => 0.005,
                        'echeance_at' => now()->addDays(4),
                    ],
                ],
                'journal'       => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Acte édité et validé', 'type' => 'etape'],
                    ['action' => 'Révision approuvée', 'type' => 'revision'],
                    ['action' => 'Signatures apposées', 'type' => 'signature'],
                    ['action' => 'Dossier transmis aux formalités', 'type' => 'etape'],
                ],
            ],
            [
                'reference'     => 'PRO-2026-0001',
                'type_acte_id'  => $typePro?->id,
                'etape'         => EtapeDossier::Edition,
                'redacteur_id'  => $clerc?->id,
                'notaire_id'    => $notaire?->id,
                'objet'         => 'Procuration générale pour gestion immobilière',
                'valeur'        => null,
                'echeance'      => now()->addDays(3),
                'parties'       => [
                    ['nom' => 'Kadiatou Diallo', 'role' => 'mandant', 'cni' => 'CNI-GN-012345', 'telephone' => '+224 621 33 44 55'],
                ],
                'journal'       => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Édition en cours', 'type' => 'etape'],
                ],
            ],
            [
                'reference'     => 'SUC-2026-0001',
                'type_acte_id'  => $typeSuc?->id,
                'etape'         => EtapeDossier::Cloture,
                'redacteur_id'  => $clerc?->id,
                'reviseur_id'   => $reviseur?->id,
                'notaire_id'    => $notaire?->id,
                'formaliste_id' => $formaliste?->id,
                'objet'         => 'Déclaration de succession famille Konaté',
                'valeur'        => 200000000,
                'echeance'      => now()->subDays(5),
                'parties'       => [
                    ['nom' => 'Fatoumata Konaté', 'role' => 'heritier', 'cni' => 'CNI-GN-021111', 'telephone' => '+224 622 00 11 22'],
                    ['nom' => 'Sekou Konaté',     'role' => 'heritier', 'cni' => 'CNI-GN-021112', 'telephone' => '+224 622 00 11 23'],
                ],
                'journal'       => [
                    ['action' => 'Dossier créé', 'type' => 'creation'],
                    ['action' => 'Acte rédigé', 'type' => 'etape'],
                    ['action' => 'Révision validée', 'type' => 'revision'],
                    ['action' => 'Signatures complètes', 'type' => 'signature'],
                    ['action' => 'Formalités accomplies', 'type' => 'etape'],
                    ['action' => 'Dossier clôturé', 'type' => 'cloture'],
                ],
            ],
        ];

        foreach ($dossiers as $data) {
            if (Dossier::where('reference', $data['reference'])->exists()) {
                continue;
            }

            $dossier = Dossier::create([
                'reference'     => $data['reference'],
                'type_acte_id'  => $data['type_acte_id'],
                'etape'         => $data['etape'],
                'redacteur_id'  => $data['redacteur_id'],
                'reviseur_id'   => $data['reviseur_id'] ?? null,
                'notaire_id'    => $data['notaire_id'],
                'formaliste_id' => $data['formaliste_id'] ?? null,
                'objet'         => $data['objet'],
                'valeur'        => $data['valeur'],
                'echeance'      => $data['echeance'],
            ]);

            // Questionnaire
            Questionnaire::create([
                'dossier_id' => $dossier->id,
                'donnees'    => ['objet' => $data['objet'], 'valeur' => $data['valeur']],
            ]);

            // Parties
            foreach ($data['parties'] ?? [] as $partieData) {
                Partie::create(array_merge(['dossier_id' => $dossier->id], $partieData));
            }

            // Formalites
            foreach ($data['formalites'] ?? [] as $formaliteData) {
                $formalite = Formalite::create(array_merge(['dossier_id' => $dossier->id], $formaliteData));
                if ($formalite->taux && $formalite->montant_base) {
                    $formalite->calculerMontant();
                }
            }

            // Revision pour les dossiers en révision ou après
            $etapesAvecRevision = [
                EtapeDossier::Revision,
                EtapeDossier::SignatureClient,
                EtapeDossier::SignatureNotaire,
                EtapeDossier::Formalites,
                EtapeDossier::Expedition,
                EtapeDossier::Cloture,
            ];

            if (in_array($dossier->etape, $etapesAvecRevision)) {
                $statut = in_array($dossier->etape, [EtapeDossier::Revision])
                    ? StatutRevision::EnCours
                    : StatutRevision::Valide;

                Revision::create([
                    'dossier_id'       => $dossier->id,
                    'reviseur_id'      => $data['reviseur_id'] ?? null,
                    'statut'           => $statut,
                    'commentaire'      => null,
                ]);
            }

            // Journal
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
