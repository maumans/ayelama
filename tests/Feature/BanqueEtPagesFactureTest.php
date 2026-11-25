<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFacture;
use App\Models\Bareme;
use App\Models\CompteBancaire;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\Paiement;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FacturationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La banque de règlement et le nombre de pages d'une facture (2026-11-20).
 *
 *  - l'étude choisit la banque facture par facture ; le numéro suit, et la facture en garde une
 *    **copie** — corriger un numéro ne réécrit pas une facture remise ;
 *  - timbres fiscaux et rôles = pages des statuts et de la DNSV (sans pages de garde) × 2 000 et
 *    × 5 000 GNF : une seule donnée, portée par la facture, qui survit à la régénération.
 */
class BanqueEtPagesFactureTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;
    private Dossier $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comptable = User::factory()->create(['name' => 'Aïssata Camara']);
        $this->comptable->syncRoles([RoleUtilisateur::Comptable]);

        $type = TypeActe::firstOrCreate(['code' => 'SOC-SARLU'], ['label' => 'SARLU', 'categorie' => 'societe']);
        foreach ([
            ['Honoraires forfaitaires', 4_500_000, false],
            ['Timbres fiscaux (à la page)', 2_000, true],
            ['Rôles (à la page)', 5_000, true],
        ] as $i => [$libelle, $montant, $parPage]) {
            Bareme::create([
                'type_acte_id' => $type->id, 'organisme' => $parPage ? 'Impots' : 'Notaire', 'libelle' => $libelle,
                'montant_fixe' => $montant, 'base_calcul' => 'montant_fixe', 'quantite_defaut' => $parPage ? 0 : 1,
                'par_page' => $parPage, 'actif' => true, 'ordre' => $i,
            ]);
        }

        $this->dossier = Dossier::create([
            'reference' => 'SOC-2026-9001', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Formalites,
            'redacteur_id' => $this->comptable->id, 'objet' => 'Constitution de test',
        ]);
    }

    private function facture(): Facture
    {
        return app(FacturationService::class)->genererFacture($this->dossier)->fresh();
    }

    private function entete(Facture $facture, array $champs)
    {
        return $this->actingAs($this->comptable)->patch("/factures/{$facture->id}/entete", [
            'note_date' => now()->toDateString(), ...$champs,
        ]);
    }

    public function test_la_banque_choisie_imprime_son_numero_et_la_facture_en_garde_copie(): void
    {
        $orabank = CompteBancaire::create(['banque' => 'ORABANK GUINEE SA', 'numero' => '011406700100', 'actif' => true]);
        $facture = $this->facture();

        $this->entete($facture, ['compte_bancaire_id' => $orabank->id])->assertSessionHasNoErrors();
        $facture->refresh();

        $this->assertSame('011406700100 ORABANK GUINEE SA', $facture->compteImprime());
        $g = app(\App\Services\FactureGeneratorService::class);
        $facture->load('lignes', 'dossier');
        $html = view('factures.pdf', [
            'facture' => $facture, 'detail' => $g->detailPrestations($facture->lignes),
            'totalEnLettres' => $g->totalEnLettres((float) $facture->total_chiffres),
        ])->render();
        $this->assertStringContainsString('011406700100 ORABANK GUINEE SA', $html);

        // Corriger le numéro plus tard ne réécrit pas la facture déjà établie.
        $orabank->update(['numero' => '999']);
        $this->assertSame('011406700100', $facture->fresh()->compte_numero);
    }

    public function test_un_compte_desactive_n_est_plus_proposable_sauf_s_il_est_deja_retenu(): void
    {
        $ferme = CompteBancaire::create(['banque' => 'BANQUE FERMÉE', 'numero' => '1', 'actif' => false]);
        $facture = $this->facture();

        $this->entete($facture, ['compte_bancaire_id' => $ferme->id])->assertSessionHasErrors('compte_bancaire_id');

        // Déjà retenu avant sa désactivation : il reste valide pour cette facture.
        $facture->forceFill(['compte_bancaire_id' => $ferme->id])->save();
        $this->entete($facture, ['compte_bancaire_id' => $ferme->id])->assertSessionHasNoErrors();
    }

    public function test_les_pages_alimentent_timbres_et_roles_et_bloquent_l_emission_tant_qu_absentes(): void
    {
        $facture = $this->facture();
        $this->assertTrue($facture->comptePages());

        // Sans pages : l'émission est refusée, et dit pourquoi.
        $this->actingAs($this->comptable)->post("/factures/{$facture->id}/emettre")->assertSessionHasErrors('facture');
        $this->assertSame(StatutFacture::Proforma, $facture->fresh()->statut);

        // 11 pages (KOUMI : statuts 8 + DNSV 3) → 22 000 + 55 000.
        $this->entete($facture, ['nb_pages' => 11])->assertSessionHasNoErrors();
        $facture->refresh()->load('lignes');
        $quantites = $facture->lignes->pluck('quantite', 'designation');
        $this->assertSame(11, $quantites['Timbres fiscaux (à la page)']);
        $this->assertSame(11, $quantites['Rôles (à la page)']);
        $this->assertSame(1, $quantites['Honoraires forfaitaires']);
        $this->assertEqualsWithDelta(4_500_000 + 22_000 + 55_000, (float) $facture->total_chiffres, 0.01);

        $this->actingAs($this->comptable)->post("/factures/{$facture->id}/emettre")->assertSessionHasNoErrors();
    }

    public function test_la_regeneration_garde_banque_pages_et_expediteur(): void
    {
        $compte = CompteBancaire::create(['banque' => 'ECOBANK', 'numero' => '7344002411', 'actif' => true]);
        $facture = $this->facture();
        $this->entete($facture, ['compte_bancaire_id' => $compte->id, 'nb_pages' => 12, 'expediteur' => 'Mariama Sow']);

        $nouvelle = $this->facture()->load('lignes');

        $this->assertSame([$compte->id, 'ECOBANK', '7344002411', 12, 'Mariama Sow'],
            [$nouvelle->compte_bancaire_id, $nouvelle->banque, $nouvelle->compte_numero, $nouvelle->nb_pages, $nouvelle->expediteur]);
        $this->assertSame(12, $nouvelle->lignes->firstWhere('designation', 'Rôles (à la page)')->quantite);
    }

    public function test_les_pages_ne_changent_plus_apres_un_paiement(): void
    {
        $facture = $this->facture();
        $this->entete($facture, ['nb_pages' => 11]);
        $facture->refresh()->emettre();
        Paiement::create(['facture_id' => $facture->id, 'date_paiement' => now(), 'montant' => 100_000, 'enregistre_par_id' => $this->comptable->id]);

        $this->entete($facture->fresh(), ['nb_pages' => 20])->assertSessionHasErrors();
        $this->assertSame(11, $facture->fresh()->nb_pages);
    }

    public function test_seul_l_administrateur_gere_les_comptes_et_le_numero_reste_numerique(): void
    {
        $admin = User::factory()->create();
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        $this->actingAs($this->comptable)->post('/parametres/comptes-bancaires', ['banque' => 'X', 'numero' => '1'])->assertForbidden();

        $this->actingAs($admin)->post('/parametres/comptes-bancaires', ['banque' => 'BSIC', 'numero' => '32 1656 0O15'])
            ->assertSessionHasErrors('numero'); // une lettre O glissée à la place d'un zéro

        $this->actingAs($admin)->post('/parametres/comptes-bancaires', ['banque' => 'BSIC', 'numero' => '32 1656 0015', 'a_verifier' => true])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('comptes_bancaires', ['banque' => 'BSIC', 'numero' => '3216560015', 'a_verifier' => true]);
    }
}
