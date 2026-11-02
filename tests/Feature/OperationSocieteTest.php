<?php

namespace Tests\Feature;

use App\Contracts\OperationDeSociete;
use App\Enums\TypeAssemblee;
use App\Enums\TypeModificationStatutaire;
use App\Enums\VarianteDissolution;
use App\Enums\RoleUtilisateur;
use App\Models\OperationSociete;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\ReglesGestionOperationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catalogue des types d'opération sur les sociétés — addendum v1.2 § 5 (Tableau 9 & 10).
 */
class OperationSocieteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReglesGestionOperationsSeeder::class);
    }

    public function test_le_catalogue_contient_les_14_operations_dont_12_actives(): void
    {
        // 12 actives et non 11 : la diminution de capital est absente de la spécification mais
        // pratiquée par l'étude — cochable au questionnaire, dotée d'un bloc et d'un barème.
        // La laisser inactive aurait fait dire au catalogue l'inverse de ce que le formulaire
        // propose. Les deux restantes (prorogation, annulation) ne sont nulle part dans le dépôt.
        $this->assertSame(14, OperationSociete::count());
        $this->assertSame(12, OperationSociete::actives()->count());
        $this->assertSame(2, OperationSociete::where('est_actif', false)->count());
    }

    public function test_les_operations_sont_reparties_par_type_dacte(): void
    {
        // 9 actives + 3 inactives = 12 pour SOC-MOD
        $this->assertSame(12, OperationSociete::pourTypeActe('SOC-MOD')->count());
        $this->assertSame(10, OperationSociete::pourTypeActe('SOC-MOD')->actives()->count());

        // 2 actives pour SOC-DIS (DISSOLUTION, CLOTURE_LIQUIDATION)
        $this->assertSame(2, OperationSociete::pourTypeActe('SOC-DIS')->count());
        $this->assertSame(2, OperationSociete::pourTypeActe('SOC-DIS')->actives()->count());
    }

    public function test_les_operations_actives_correspondent_toutes_a_un_cas_denum(): void
    {
        $actives = OperationSociete::actives()->get();

        foreach ($actives as $operation) {
            $ref = $operation->referenceEnum();
            $this->assertNotNull($ref, "L'opération active {$operation->code} doit avoir un enum de référence");
            $this->assertInstanceOf(OperationDeSociete::class, $ref);
            $this->assertSame($operation->code, $ref->codeOperation());
        }
    }

    public function test_aucune_operation_seedee_ne_diverge_de_la_reference(): void
    {
        $actives = OperationSociete::actives()->get();

        foreach ($actives as $operation) {
            $this->assertFalse(
                $operation->divergeDeLaReference(),
                "L'opération {$operation->code} ne doit pas diverger de la référence par défaut"
            );
            $this->assertSame([], $operation->differencesAvecLaReference());
        }
    }

    public function test_une_modification_de_regle_en_base_est_detectee_comme_une_divergence(): void
    {
        // Prenons DECISION_GESTION (règle 9 du tableau 9)
        $op = OperationSociete::where('code', 'DECISION_GESTION')->firstOrFail();
        $this->assertFalse($op->divergeDeLaReference());

        // Si l'étude décide que les décisions de gestion doivent passer au RCCM (question Q2 à Maître Bah)
        $op->update(['impacte_rccm' => true]);

        $this->assertTrue($op->divergeDeLaReference());
        $differences = $op->differencesAvecLaReference();

        $this->assertArrayHasKey('impacte_rccm', $differences);
        $this->assertSame(true, $differences['impacte_rccm']['actuel']);
        $this->assertSame(false, $differences['impacte_rccm']['reference']);
    }

    public function test_le_changement_dassemblee_est_detecte_dans_les_differences(): void
    {
        $op = OperationSociete::where('code', 'MOD_DENOMINATION')->firstOrFail();
        $this->assertSame(TypeAssemblee::Age, $op->assemblee);

        $op->update(['assemblee' => TypeAssemblee::Ago->value]);

        $this->assertTrue($op->divergeDeLaReference());
        $differences = $op->differencesAvecLaReference();

        $this->assertArrayHasKey('assemblee', $differences);
        $this->assertSame('AGO', $differences['assemblee']['actuel']);
        $this->assertSame('AGE', $differences['assemblee']['reference']);
    }

    public function test_le_seeder_est_idempotent(): void
    {
        $this->seed(ReglesGestionOperationsSeeder::class);
        $this->assertSame(14, OperationSociete::count());
    }

    public function test_les_operations_de_dissolution_sont_exclusives(): void
    {
        $dissolution = OperationSociete::where('code', 'DISSOLUTION')->firstOrFail();
        $cloture = OperationSociete::where('code', 'CLOTURE_LIQUIDATION')->firstOrFail();

        $this->assertTrue($dissolution->exclusive);
        $this->assertTrue($cloture->exclusive);
    }

    /**
     * Parité **bidirectionnelle** enum ⇄ catalogue, sur toutes les lignes.
     *
     * ⚠️ Le test précédent ne regardait que les opérations **actives**, et seulement dans un
     * sens. Deux désalignements y ont vécu sans bruit : la ligne `RED_CAPITAL` portait un code
     * que `TypeModificationStatutaire::CapitalDiminution->codeOperation()` n'a jamais rendu
     * (`DIM_CAPITAL`), et la dissolution portait la variante `dissolution_anticipee` quand
     * l'enum vaut `dissolution`. Dans les deux cas `referenceEnum()` rendait `null`, donc
     * `divergeDeLaReference()` rendait `false` : **une divergence qui se cachait derrière le
     * détecteur de divergences**. Le sens manquant — chaque cas d'enum a sa ligne — est ce qui
     * l'aurait vue.
     */
    public function test_chaque_cas_denum_a_sa_ligne_au_catalogue(): void
    {
        $cas = [...TypeModificationStatutaire::cases(), ...VarianteDissolution::cases()];

        foreach ($cas as $case) {
            $ligne = OperationSociete::where('code', $case->codeOperation())->first();

            $this->assertNotNull(
                $ligne,
                "Le cas {$case->value} rend le code {$case->codeOperation()}, qu'aucune ligne du "
                . "catalogue ne porte. L'écran d'administration ne pourra donc jamais régler "
                . 'cette opération, et aucune divergence ne sera détectée.',
            );

            $this->assertSame(
                $case->valeur(),
                $ligne->variante,
                "La ligne {$ligne->code} désigne la variante « {$ligne->variante} », l'enum la "
                . "variante « {$case->valeur()} ». C'est par la variante que l'assistant "
                . 'rapprochera une case cochée de sa règle.',
            );

            $this->assertSame([], $ligne->differencesAvecLaReference());
        }
    }

    public function test_une_ligne_sans_reference_est_inactive(): void
    {
        // L'inverse du test précédent : une ligne que le dépôt ne sait pas exécuter ne doit pas
        // être proposée. Prorogation et annulation de décision sont au Tableau 10 de la spec
        // sans exister côté code — les activer offrirait une case qui ne produit rien.
        foreach (OperationSociete::actives()->get() as $ligne) {
            $this->assertNotNull(
                $ligne->referenceEnum(),
                "L'opération active {$ligne->code} n'a aucun cas d'enum : cochée, elle "
                . "n'ouvrirait aucun bloc de questionnaire et ne produirait aucun acte.",
            );
        }
    }

    public function test_reinitialiser_rend_une_ligne_modifiee_a_la_reference(): void
    {
        $op = OperationSociete::where('code', 'MOD_SIEGE')->first();
        $op->update(['impacte_rccm' => false, 'assemblee' => TypeAssemblee::Ago]);

        $this->assertTrue($op->fresh()->divergeDeLaReference());

        $this->assertTrue(ReglesGestionOperationsSeeder::reinitialiser('MOD_SIEGE'));
        $this->assertFalse($op->fresh()->divergeDeLaReference());
    }

    public function test_reinitialiser_un_code_inconnu_le_dit(): void
    {
        // Un `false` silencieux ferait croire l'écran à une remise à zéro qui n'a pas eu lieu.
        $this->assertFalse(ReglesGestionOperationsSeeder::reinitialiser('RED_CAPITAL'));
    }

    // ── L'écran d'administration (E6, test T13) ──────────────────────────────

    private function administrateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Administrateur->value]);

        return $user->fresh();
    }

    public function test_lecran_expose_le_catalogue_et_son_ecart_a_la_reference(): void
    {
        OperationSociete::where('code', 'MOD_SIEGE')->update(['impacte_rccm' => false]);

        $this->actingAs($this->administrateur())
            ->get('/parametres/types-actes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('operations', 14)
                ->has('assemblees', 3)
                // L'écart doit remonter jusqu'à l'écran : une règle réglée sans que l'écart se
                // voie, c'est le réglage qu'on ne sait plus annuler.
                ->where('operations.1.code', 'MOD_SIEGE')
                ->where('operations.1.diverge', true)
                ->where('operations.1.aUneReference', true));
    }

    public function test_une_regle_se_change_sans_redeploiement(): void
    {
        $op = OperationSociete::where('code', 'DECISION_GESTION')->first();

        $this->actingAs($this->administrateur())
            ->patch("/parametres/operations-societe/{$op->id}", [
                'assemblee'       => 'AGO',
                'impacte_statuts' => false,
                'impacte_rccm'    => true,
                'publication_jal' => false,
                'exclusive'       => false,
                'regle_validee'   => true,
                'est_actif'       => true,
            ])
            ->assertRedirect();

        $op->refresh();
        $this->assertTrue($op->impacte_rccm);
        $this->assertTrue($op->regle_validee);
        $this->assertTrue($op->divergeDeLaReference());
    }

    public function test_une_assemblee_inconnue_est_refusee(): void
    {
        $op = OperationSociete::where('code', 'MOD_SIEGE')->first();

        $this->actingAs($this->administrateur())
            ->patch("/parametres/operations-societe/{$op->id}", [
                'assemblee'       => 'AG_INVENTEE',
                'impacte_statuts' => true,
                'impacte_rccm'    => true,
                'publication_jal' => true,
                'exclusive'       => false,
                'regle_validee'   => true,
                'est_actif'       => true,
            ])
            ->assertSessionHasErrors('assemblee');
    }

    public function test_le_bouton_restaurer_rejoue_la_reference(): void
    {
        $op = OperationSociete::where('code', 'MOD_OBJET')->first();
        $op->update(['publication_jal' => false, 'assemblee' => TypeAssemblee::Ago]);

        $this->actingAs($this->administrateur())
            ->post("/parametres/operations-societe/{$op->id}/reinitialiser")
            ->assertRedirect();

        $this->assertFalse($op->fresh()->divergeDeLaReference());
    }

    public function test_lecran_est_reserve_a_ladministrateur(): void
    {
        // Les règles qui décident des actes produits et des formalités engagées ne se règlent
        // pas depuis un compte de clerc.
        $clerc = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $clerc->id, 'role' => RoleUtilisateur::Clerc->value]);

        $op = OperationSociete::where('code', 'MOD_SIEGE')->first();

        $this->actingAs($clerc->fresh())
            ->patch("/parametres/operations-societe/{$op->id}", [
                'assemblee'       => 'AGO',
                'impacte_statuts' => false,
                'impacte_rccm'    => false,
                'publication_jal' => false,
                'exclusive'       => false,
                'regle_validee'   => false,
                'est_actif'       => false,
            ])
            ->assertForbidden();

        $this->assertFalse($op->fresh()->divergeDeLaReference());
    }
}
