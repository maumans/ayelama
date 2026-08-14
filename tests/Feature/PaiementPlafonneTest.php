<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\Paiement;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invariant de facturation : la somme des paiements d'une facture ne dépasse
 * jamais son total. Avant ce contrôle, la surfacturation était explicitement
 * permise (simple avertissement front au-delà du double du total).
 */
class PaiementPlafonneTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comptable = User::factory()->create();
        $this->comptable->syncRoles([RoleUtilisateur::Comptable]);
    }

    private function factureDe(float $total): Facture
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Formalites,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour le plafonnement des paiements',
        ]);

        $facture = Facture::create([
            'dossier_id'        => $dossier->id,
            'note_numero'       => Facture::genererNumero(),
            'note_date'         => now(),
            'objet'             => 'Note de frais de test',
            'assiette_chiffres' => $total,
            'total_chiffres'    => $total,
        ]);

        if ($total > 0) {
            LigneFacture::create([
                'facture_id'  => $facture->id,
                'designation' => 'Honoraires',
                'quantite'    => 1,
                'montant'     => $total,
            ]);
        }

        return $facture;
    }

    private function payer(Facture $facture, float $montant)
    {
        return $this->actingAs($this->comptable)->post(
            "/dossiers/{$facture->dossier->reference}/paiements",
            ['date_paiement' => now()->toDateString(), 'montant' => $montant],
        );
    }

    public function test_un_paiement_superieur_au_total_est_refuse(): void
    {
        $facture = $this->factureDe(1_000_000);

        $this->payer($facture, 1_500_000)->assertSessionHasErrors('montant');

        $this->assertSame(0, $facture->paiements()->count());
    }

    public function test_un_paiement_exactement_egal_au_solde_est_accepte(): void
    {
        $facture = $this->factureDe(1_000_000);

        // Cas limite : un test strict sur des flottants rejetterait à tort le
        // paiement qui solde exactement la facture.
        $this->payer($facture, 1_000_000)->assertSessionHasNoErrors();

        $this->assertSame(1_000_000.0, $facture->fresh()->totalPaye());
        $this->assertSame(0.0, $facture->fresh()->soldeRestant());
    }

    public function test_le_cumul_des_paiements_ne_peut_pas_depasser_le_total(): void
    {
        $facture = $this->factureDe(1_000_000);

        $this->payer($facture, 600_000)->assertSessionHasNoErrors();
        // 600 000 + 500 000 = 1 100 000 > 1 000 000 : c'est le cumul qui doit bloquer,
        // pas le montant pris isolément (500 000 seul serait valide).
        $this->payer($facture, 500_000)->assertSessionHasErrors('montant');
        $this->payer($facture, 400_000)->assertSessionHasNoErrors();

        $this->assertSame(1_000_000.0, $facture->fresh()->totalPaye());
        $this->assertSame(2, $facture->paiements()->count());
    }

    public function test_aucun_paiement_sur_une_facture_soldee(): void
    {
        $facture = $this->factureDe(500_000);
        $this->payer($facture, 500_000)->assertSessionHasNoErrors();

        $this->payer($facture, 1)->assertSessionHasErrors('montant');

        $this->assertSame(1, $facture->paiements()->count());
    }

    public function test_aucun_paiement_sur_une_facture_a_zero(): void
    {
        $facture = $this->factureDe(0);

        $this->payer($facture, 50_000)->assertSessionHasErrors('montant');

        $this->assertFalse($facture->peutRecevoirPaiement());
    }

    public function test_modifier_un_paiement_a_la_hausse_au_dela_du_total_est_refuse(): void
    {
        $facture = $this->factureDe(1_000_000);
        $this->payer($facture, 1_000_000);
        $paiement = $facture->paiements()->firstOrFail();

        $this->actingAs($this->comptable)
            ->patch("/paiements/{$paiement->id}", [
                'date_paiement' => now()->toDateString(),
                'montant'       => 1_200_000,
            ])
            ->assertSessionHasErrors('montant');

        $this->assertSame(1_000_000.0, (float) $paiement->fresh()->montant);
    }

    public function test_modifier_un_paiement_a_la_baisse_est_accepte(): void
    {
        $facture = $this->factureDe(1_000_000);
        $this->payer($facture, 1_000_000);
        $paiement = $facture->paiements()->firstOrFail();

        // Le paiement édité libère son propre montant : le ramener à 800 000 sur
        // une facture soldée doit passer, alors que le solde brut est à 0.
        $this->actingAs($this->comptable)
            ->patch("/paiements/{$paiement->id}", [
                'date_paiement' => now()->toDateString(),
                'montant'       => 800_000,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(800_000.0, (float) $paiement->fresh()->montant);
        $this->assertSame(200_000.0, $facture->fresh()->soldeRestant());
    }

    public function test_le_solde_disponible_ignore_le_paiement_en_cours_dedition(): void
    {
        $facture = $this->factureDe(1_000_000);
        $this->payer($facture, 300_000);
        $this->payer($facture, 200_000);
        $premier = $facture->paiements()->orderBy('id')->firstOrFail();

        $this->assertSame(500_000.0, $facture->soldeDisponible());
        $this->assertSame(800_000.0, $facture->soldeDisponible($premier->id));
    }

    public function test_supprimer_un_paiement_libere_du_solde(): void
    {
        $facture = $this->factureDe(1_000_000);
        $this->payer($facture, 1_000_000);
        $paiement = $facture->paiements()->firstOrFail();

        $this->assertFalse($facture->fresh()->peutRecevoirPaiement());

        $this->actingAs($this->comptable)->delete("/paiements/{$paiement->id}")->assertSessionHasNoErrors();

        $this->assertTrue($facture->fresh()->peutRecevoirPaiement());
        $this->payer($facture, 1_000_000)->assertSessionHasNoErrors();
    }

    public function test_une_facture_deja_en_trop_percu_est_signalee_et_bloquee(): void
    {
        // Simule une donnée antérieure à la règle (insertion directe, sans passer
        // par le contrôleur qui l'interdirait désormais).
        $facture = $this->factureDe(500_000);
        Paiement::create([
            'facture_id'    => $facture->id,
            'date_paiement' => now(),
            'montant'       => 700_000,
        ]);

        $facture->refresh();
        $this->assertTrue($facture->estTropPercue());
        $this->assertFalse($facture->peutRecevoirPaiement());

        $this->payer($facture, 1)->assertSessionHasErrors('montant');
    }

    public function test_les_lignes_restent_non_modifiables_des_quun_paiement_existe(): void
    {
        // L'autre moitié de l'invariant : si le total pouvait baisser après
        // encaissement, les paiements le dépasseraient à nouveau.
        $facture = $this->factureDe(1_000_000);
        $this->payer($facture, 1_000_000);
        $ligne = $facture->lignes()->firstOrFail();

        $this->actingAs($this->comptable)
            ->patch("/lignes/{$ligne->id}", [
                'designation' => 'Honoraires réduits',
                'quantite'    => 1,
                'montant'     => 100_000,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(1_000_000.0, (float) $facture->fresh()->total_chiffres);
    }
}
