<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\FormeSociete;
use App\Enums\NatureSociete;
use App\Enums\RoleUtilisateur;
use App\Enums\TypeModificationStatutaire;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ReglesSocieteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Règles de gestion notariales — `Regles_Gestion_Plateforme_Notariale.docx` (CR juillet 2026).
 *
 * Un test par règle, nommé d'après elle : quand une règle changera, on saura lequel
 * toucher. Ces règles sont **bloquantes** (branchées dans
 * `DossierStepService::erreursDeConstitution()`) — produire les statuts d'une SA
 * sous-capitalisée expose l'office.
 */
class ReglesSocieteTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ReglesSocieteService
    {
        return app(ReglesSocieteService::class);
    }

    /**
     * Dossier de société dont la forme est déduite du **code du type d'acte** — source
     * prioritaire, `soc.forme` étant absent des questionnaires réels.
     */
    private function dossier(string $codeTypeActe, array $donnees = [], string $categorie = 'societe'): Dossier
    {
        // firstOrCreate : `types_actes.code` est unique, et plusieurs dossiers du même test
        // partagent le même type d'acte (deux SARLU pour tester l'unicité de dénomination).
        $typeActe = TypeActe::firstOrCreate(
            ['code' => $codeTypeActe],
            ['label' => 'Type de test ' . $codeTypeActe, 'categorie' => $categorie],
        );

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour les règles de gestion notariales',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    /** Ajoute un associé, éventuellement rattaché à une fiche client. */
    private function associe(Dossier $dossier, ?array $client = null, string $role = 'associe'): Partie
    {
        return Partie::create([
            'dossier_id' => $dossier->id,
            'nom'        => $client['prenom_nom'] ?? 'Associé de test',
            'role'       => $role,
            'client_id'  => $client ? Client::create(array_merge(['type' => 'physique'], $client))->id : null,
        ]);
    }

    private function commissaire(Dossier $dossier, string $role = 'commissaire_titulaire'): Partie
    {
        return Partie::create([
            'dossier_id' => $dossier->id,
            'nom'        => 'Cabinet de test',
            'role'       => $role,
        ]);
    }

    // ── Règle 1 : capital minimum ─────────────────────────────────────────────

    public function test_regle1_une_sa_sous_capitalisee_est_bloquee(): void
    {
        $dossier = $this->dossier('SOC-SA', ['soc.capital_chiffres' => 100_000_000]);

        $anomalies = $this->service()->anomalies($dossier);

        $this->assertArrayHasKey('soc_capital', $anomalies);
        $this->assertStringContainsString('140 000 000', $anomalies['soc_capital'][0]);
    }

    public function test_regle1_une_sa_au_capital_minimum_passe(): void
    {
        $dossier = $this->dossier('SOC-SA', [
            'soc.capital_chiffres'      => 140_000_000,
        ]);
        $this->commissaire($dossier);

        $this->assertArrayNotHasKey('soc_capital', $this->service()->anomalies($dossier));
    }

    public function test_regle1_une_sarl_na_pas_de_capital_minimum(): void
    {
        // Le document est explicite : « Pas de capital minimum requis ».
        $dossier = $this->dossier('SOC-SARL', ['soc.capital_chiffres' => 1_000]);

        $this->assertArrayNotHasKey('soc_capital', $this->service()->anomalies($dossier));
    }

    public function test_regle1_un_gie_peut_etre_cree_sans_capital(): void
    {
        $dossier = $this->dossier('SOC-GIE', ['soc.capital_chiffres' => 0]);

        $this->assertSame([], $this->service()->anomalies($dossier));
        $this->assertNull(FormeSociete::GIE->capitalMinimum());
    }

    public function test_regle1_le_capital_minimum_est_parametrable(): void
    {
        // Ce montant est fixé par la loi et changera : il doit être modifiable sans
        // déploiement.
        \App\Models\Setting::set(FormeSociete::CLE_CAPITAL_MINIMUM_SA, 200_000_000);

        $this->assertSame(200_000_000, FormeSociete::SA->capitalMinimum());
    }

    // ── Règle 2 : sociétés à associé unique ───────────────────────────────────

    public function test_regle2_une_sarlu_a_deux_associes_est_bloquee(): void
    {
        $dossier = $this->dossier('SOC-SARLU');
        $this->associe($dossier);
        $this->associe($dossier);

        $anomalies = $this->service()->anomalies($dossier->fresh());

        $this->assertArrayHasKey('soc_associes', $anomalies);
        $this->assertStringContainsString("un seul associé", $anomalies['soc_associes'][0]);
    }

    public function test_regle2_une_sarl_a_un_seul_associe_est_bloquee(): void
    {
        // L'inverse : une forme pluripersonnelle exige au moins deux associés.
        $dossier = $this->dossier('SOC-SARL');
        $this->associe($dossier);

        $anomalies = $this->service()->anomalies($dossier->fresh());

        $this->assertArrayHasKey('soc_associes', $anomalies);
        $this->assertStringContainsString('SASU, SARLU et SAU', $anomalies['soc_associes'][0]);
    }

    public function test_regle2_les_trois_formes_unipersonnelles_sont_celles_du_document(): void
    {
        $unipersonnelles = array_values(array_filter(
            FormeSociete::cases(),
            fn (FormeSociete $f) => $f->admetAssocieUnique(),
        ));

        $this->assertEqualsCanonicalizing(
            [FormeSociete::SAU, FormeSociete::SARLU, FormeSociete::SASU],
            $unipersonnelles,
        );
    }

    // ── Règle 3 : classification capitaux / personnes ─────────────────────────

    public function test_regle3_la_classification_suit_le_document(): void
    {
        foreach ([FormeSociete::SA, FormeSociete::SAS, FormeSociete::SARL] as $forme) {
            $this->assertSame(NatureSociete::Capitaux, $forme->nature(), "{$forme->value} est une société de capitaux.");
            $this->assertStringContainsString('limitée aux apports', $forme->responsabilite());
        }

        foreach ([FormeSociete::SNC, FormeSociete::SCS] as $forme) {
            $this->assertSame(NatureSociete::Personnes, $forme->nature(), "{$forme->value} est une société de personnes.");
            $this->assertStringContainsString('illimitée, solidaire', $forme->responsabilite());
        }
    }

    // ── Règle 4 : dénomination sociale unique ─────────────────────────────────

    public function test_regle4_une_denomination_deja_utilisee_est_refusee(): void
    {
        $premier = $this->dossier('SOC-SARLU', ['soc.denomination' => "Bureau d'architecture"]);
        $this->associe($premier);

        // Casse et espaces différents : la comparaison doit rester normalisée.
        $second = $this->dossier('SOC-SARLU', ['soc.denomination' => "  BUREAU  D'ARCHITECTURE "]);
        $this->associe($second);

        $anomalies = $this->service()->anomalies($second->fresh());

        $this->assertArrayHasKey('soc_denomination', $anomalies);
        $this->assertStringContainsString($premier->reference, $anomalies['soc_denomination'][0]);
    }

    public function test_regle4_une_denomination_libre_passe(): void
    {
        $this->dossier('SOC-SARLU', ['soc.denomination' => 'Première société']);
        $second = $this->dossier('SOC-SARLU', ['soc.denomination' => 'Seconde société']);
        $this->associe($second);

        $this->assertArrayNotHasKey('soc_denomination', $this->service()->anomalies($second->fresh()));
    }

    // ── Règle 5 : commissaire aux comptes ─────────────────────────────────────

    public function test_regle5_une_sa_sans_commissaire_est_bloquee(): void
    {
        $dossier = $this->dossier('SOC-SA', ['soc.capital_chiffres' => 140_000_000]);

        $this->assertArrayHasKey('soc_commissaire', $this->service()->anomalies($dossier));
    }

    public function test_regle5_une_sarl_sans_commissaire_passe(): void
    {
        // « PAS obligatoire à la création » pour les autres formes.
        $dossier = $this->dossier('SOC-SARL');
        $this->associe($dossier);
        $this->associe($dossier);

        $this->assertArrayNotHasKey('soc_commissaire', $this->service()->anomalies($dossier->fresh()));
    }

    // ── Règle 6 : personne morale associée ────────────────────────────────────

    public function test_regle6_le_pv_dassemblee_est_une_piece_distincte_et_obligatoire(): void
    {
        // Avant, une seule entrée « Déclaration RCCM **ou** PV de délibération » rendait le
        // PV facultatif : fournir la déclaration suffisait à cocher la pièce.
        $requises = Partie::piecesRequisesPour('associe', 'morale');

        $this->assertArrayHasKey('declaration_rccm', $requises);
        $this->assertArrayHasKey('pv_ag', $requises);
        $this->assertStringNotContainsString(' ou ', $requises['declaration_rccm']);
    }

    // ── Règle 7 : mineurs et incapables ───────────────────────────────────────

    public function test_regle7_un_mineur_ne_peut_pas_etre_associe_dune_societe_de_personnes(): void
    {
        $dossier = $this->dossier('SOC-SNC');
        $this->associe($dossier, ['prenom_nom' => 'Mamadou Mineur', 'date_naissance' => now()->subYears(15)]);
        $this->associe($dossier, ['prenom_nom' => 'Adulte Deux', 'date_naissance' => now()->subYears(40)]);

        $anomalies = $this->service()->anomalies($dossier->fresh());

        $this->assertArrayHasKey('capacite_juridique', $anomalies);
        $this->assertStringContainsString('Mamadou Mineur', $anomalies['capacite_juridique'][0]);
    }

    public function test_regle7_un_mineur_associe_dune_societe_de_capitaux_exige_un_tuteur(): void
    {
        $dossier = $this->dossier('SOC-SARL');
        $this->associe($dossier, ['prenom_nom' => 'Mamadou Mineur', 'date_naissance' => now()->subYears(15)]);
        $this->associe($dossier, ['prenom_nom' => 'Adulte Deux', 'date_naissance' => now()->subYears(40)]);

        $anomalies = $this->service()->anomalies($dossier->fresh());

        // Pas d'interdiction — « pas d'âge minimum » en société de capitaux — mais la
        // représentation est exigée.
        $this->assertArrayNotHasKey('capacite_juridique', $anomalies);
        $this->assertArrayHasKey('representation', $anomalies);
    }

    public function test_regle7_un_mineur_avec_tuteur_passe_en_societe_de_capitaux(): void
    {
        $dossier = $this->dossier('SOC-SARL');
        $this->associe($dossier, [
            'prenom_nom'           => 'Mamadou Mineur',
            'date_naissance'       => now()->subYears(15),
            'representant_legal'   => 'Fatoumata Diallo',
            'representant_qualite' => 'Tuteur légal',
        ]);
        $this->associe($dossier, ['prenom_nom' => 'Adulte Deux', 'date_naissance' => now()->subYears(40)]);

        $this->assertSame([], $this->service()->anomalies($dossier->fresh()));
    }

    public function test_regle7_un_age_inconnu_ne_bloque_pas(): void
    {
        // La date de naissance est facultative au questionnaire : ne pas bloquer sur une
        // supposition.
        $dossier = $this->dossier('SOC-SARL');
        $this->associe($dossier, ['prenom_nom' => 'Sans date']);
        $this->associe($dossier, ['prenom_nom' => 'Sans date deux']);

        $this->assertSame([], $this->service()->anomalies($dossier->fresh()));
    }

    // ── Règles 8 et 9 : modifications statutaires ─────────────────────────────

    public function test_regle8_seul_le_gerant_non_statutaire_nimpacte_pas_les_statuts(): void
    {
        foreach (TypeModificationStatutaire::cases() as $type) {
            $this->assertTrue($type->impacteRccm(), "{$type->value} doit impacter le RCCM.");
        }

        $this->assertFalse(TypeModificationStatutaire::GerantNonStatutaire->impacteStatuts());
        foreach ([
            TypeModificationStatutaire::GerantStatutaire,
            TypeModificationStatutaire::SiegeSocial,
            TypeModificationStatutaire::CapitalAugmentation,
            TypeModificationStatutaire::CapitalDiminution,
            TypeModificationStatutaire::CapitalCession,
            TypeModificationStatutaire::ObjetSocial,
        ] as $type) {
            $this->assertTrue($type->impacteStatuts(), "{$type->value} doit impacter les statuts.");
        }
    }

    public function test_regle9_les_documents_requis_suivent_le_document(): void
    {
        // `declaration_rccm` s'ajoute depuis le 2026-08-11 : les notes de l'étude comptent le
        // RCCM parmi les actes **édités** d'une cession, pas seulement parmi les formalités.
        $this->assertSame(
            ['acte_cession', 'pv_modification', 'statuts_maj', 'declaration_rccm'],
            array_keys(TypeModificationStatutaire::CapitalCession->documentsRequis()),
        );
        $this->assertArrayHasKey('dnsv', TypeModificationStatutaire::CapitalAugmentation->documentsRequis());
        $this->assertSame(
            ['pv_modification', 'declaration_rccm'],
            array_keys(TypeModificationStatutaire::GerantNonStatutaire->documentsRequis()),
        );
    }

    public function test_regle9_une_modification_sans_type_precise_est_bloquee(): void
    {
        $dossier = $this->dossier('SOC-MOD');

        $this->assertArrayHasKey('modif_types', $this->service()->anomalies($dossier));
    }

    public function test_regle10_une_cession_exige_la_valeur_des_parts(): void
    {
        // Sans elle, l'assiette du droit de 2 % est inconnue et la facture serait fausse.
        $dossier = $this->dossier('SOC-MOD', ['modif.types' => ['Cession de parts sociales']]);

        $this->assertArrayHasKey('modif_valeur_parts', $this->service()->anomalies($dossier));

        $dossier->questionnaire->update(['donnees' => [
            'modif.types'                => ['Cession de parts sociales'],
            'modif.valeur_parts_cedees'  => 25_000_000,
            // Depuis le 2026-08-11, un dossier de modification doit aussi désigner sa société
            // et la date de l'assemblée qui décide — le PV n'est pas rédigeable sans elles.
            'soc.denomination'           => 'Société de test SARL',
            'ag.date'                    => '01/08/2026',
            'modif.cedants'              => [['nom' => 'Ibrahima DIALLO', 'parts_cedees' => 40]],
            'modif.cessionnaires'        => [['nom' => 'Mariama SOW', 'parts_acquises' => 40]],
        ]]);

        $this->assertSame([], $this->service()->anomalies($dossier->fresh()));
    }

    // ── Périmètre : ces règles ne concernent que les sociétés ─────────────────

    public function test_un_dossier_de_vente_nest_soumis_a_aucune_de_ces_regles(): void
    {
        $dossier = $this->dossier('VTE-TST', ['soc.capital_chiffres' => 1], 'vente');

        $this->assertSame([], $this->service()->anomalies($dossier));
    }

    public function test_une_dissolution_nest_pas_soumise_aux_regles_de_constitution(): void
    {
        // `SOC-DIS` est de catégorie société mais ne constitue aucune forme : bloquer sur
        // un capital minimum y serait absurde.
        $dossier = $this->dossier('SOC-DIS');

        $this->assertSame([], $this->service()->anomalies($dossier));
    }

    // ── Blocage effectif de l'avancement ─────────────────────────────────────

    public function test_les_anomalies_bloquent_la_sortie_de_linitialisation(): void
    {
        $notaire = User::factory()->create();
        $notaire->syncRoles([RoleUtilisateur::Administrateur]);

        $dossier = $this->dossier('SOC-SA', ['soc.capital_chiffres' => 50_000_000]);
        $dossier->update(['notaire_id' => $notaire->id, 'reviseur_id' => $notaire->id]);
        $dossier->documents()->create([
            'nom' => 'Accord client', 'categorie' => 'accord_client',
            'est_signe_cachete' => true, 'signe_cachete_at' => now(),
        ]);

        $this->actingAs($notaire)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasErrors(['soc_capital', 'soc_commissaire']);

        $this->assertSame(EtapeDossier::Initialisation, $dossier->fresh()->etape);
    }
}
