<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\NatureFacture;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFacture;
use App\Enums\StatutFormalite;
use App\Models\Bareme;
use App\Models\CompteBancaire;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\Formalite;
use App\Models\Paiement;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FacturationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La facture complémentaire des droits d'enregistrement (décision de l'étude, 2026-11-23).
 *
 * Les droits ne sont connus qu'au retour des Impôts ; la facture principale est émise avant, donc
 * figée. Le montant saisi au retour établit une complémentaire proforma, rattachée à la principale.
 */
class FactureComplementaireTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Dossier $dossier;
    private Formalite $impots;
    private Facture $principale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['actif' => true]);
        $this->admin->syncRoles([RoleUtilisateur::Administrateur]);

        $type = TypeActe::firstOrCreate(['code' => 'SOC-SARLU'], ['label' => 'SARLU', 'categorie' => 'societe']);
        $droits = Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Impots', 'libelle' => "Droits d'enregistrement des Statuts et de la DNSV",
            'montant_fixe' => 0, 'base_calcul' => 'montant_fixe', 'actif' => true, 'genere_formalite' => true,
            'donnees_au_retour' => ['quittance_numero', 'montant_droits'],
        ]);
        Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Notaire', 'libelle' => 'Honoraires Forfaitaires',
            'montant_fixe' => 4_500_000, 'base_calcul' => 'montant_fixe', 'actif' => true,
        ]);

        $this->dossier = Dossier::create([
            'reference' => 'SOC-2026-7001', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Formalites,
            'redacteur_id' => $this->admin->id, 'objet' => 'Constitution KOUMI',
        ]);
        Questionnaire::create(['dossier_id' => $this->dossier->id, 'donnees' => []]);

        // La principale, émise et réglée sur ORABANK, avant que les Impôts ne répondent.
        $this->principale = app(FacturationService::class)->genererFacture($this->dossier);
        $this->principale->choisirCompte(CompteBancaire::create(['banque' => 'ORABANK GUINEE SA', 'numero' => '011406700100']));
        $this->principale->save();
        $this->principale->emettre();

        $this->impots = Formalite::create([
            'dossier_id' => $this->dossier->id, 'bareme_id' => $droits->id, 'organisme' => 'impots',
            'libelle' => "Droits d'enregistrement des Statuts et de la DNSV", 'statut' => StatutFormalite::Depose,
            'donnees_au_retour' => ['quittance_numero', 'montant_droits'],
        ]);
    }

    private function retour(?string $montant)
    {
        return $this->actingAs($this->admin)->post("/formalites/{$this->impots->id}/retour", [
            'resultat' => 'recu', 'date_retour' => now()->toDateString(),
            'donnees_recues' => array_filter(['quittance_numero' => 'QT-2026-0001', 'montant_droits' => $montant]),
        ]);
    }

    private function complementaires()
    {
        return Facture::where('dossier_id', $this->dossier->id)->where('nature', NatureFacture::Complementaire)->get();
    }

    public function test_le_retour_des_impots_etablit_une_facture_complementaire(): void
    {
        $this->retour('1500000')->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertCount(1, $this->complementaires());
        $c = $this->complementaires()->first()->load('lignes');

        $this->assertSame(StatutFacture::Proforma, $c->statut);
        $this->assertSame($this->principale->id, $c->facture_origine_id);
        $this->assertSame($this->impots->id, $c->formalite_id);
        $this->assertEqualsWithDelta(1_500_000, (float) $c->total_chiffres, 0.01);
        $this->assertSame("Droits d'enregistrement des Statuts et de la DNSV", $c->lignes->sole()->designation);
        $this->assertFalse($c->lignes->sole()->remise_autorisee, 'Un droit reversé ne se remise pas.');
        // Le client règle au même endroit que la principale.
        $this->assertSame('011406700100 ORABANK GUINEE SA', $c->compteImprime());
        // La principale, émise, n'a pas bougé.
        $this->assertEqualsWithDelta(4_500_000, (float) $this->principale->fresh()->total_chiffres, 0.01);
    }

    public function test_sans_montant_saisi_aucune_complementaire(): void
    {
        $this->retour(null)->assertSessionHasNoErrors();

        $this->assertCount(0, $this->complementaires());
    }

    public function test_un_retour_reenregistre_met_a_jour_la_proforma_sans_la_doubler(): void
    {
        $this->retour('1500000');
        $this->retour('1500000');
        $this->assertCount(1, $this->complementaires());

        $this->retour('1800000');
        $this->assertCount(1, $this->complementaires());
        $this->assertEqualsWithDelta(1_800_000, (float) $this->complementaires()->first()->total_chiffres, 0.01);
    }

    public function test_une_complementaire_emise_ne_se_reecrit_pas(): void
    {
        $this->retour('1500000');
        $c = $this->complementaires()->first();
        $c->emettre();

        // Elle prend le numéro suivant du registre, après la principale.
        $this->assertSame(
            (int) explode('/', $this->principale->fresh()->note_numero)[0] + 1,
            (int) explode('/', $c->fresh()->note_numero)[0],
        );

        $this->retour('2000000')->assertSessionHas('success', fn ($m) => str_contains($m, 'déjà émise'));
        $this->assertEqualsWithDelta(1_500_000, (float) $c->fresh()->total_chiffres, 0.01);
    }

    public function test_regenerer_la_principale_proforma_garde_la_complementaire(): void
    {
        // Une principale encore proforma (cas d'un dossier où les Impôts répondent tôt).
        $this->principale->forceFill(['statut' => StatutFacture::Proforma, 'note_numero' => null])->save();
        $this->retour('1500000');

        app(FacturationService::class)->genererFacture($this->dossier->fresh());

        $this->assertCount(1, $this->complementaires());
        $this->assertSame(1, Facture::where('dossier_id', $this->dossier->id)->principales()->count());
    }

    public function test_un_paiement_regle_la_facture_designee_et_par_defaut_la_principale(): void
    {
        $this->retour('1500000');
        $c = $this->complementaires()->first();
        $c->emettre();

        $this->actingAs($this->admin)->post("/dossiers/{$this->dossier->reference}/paiements", [
            'facture_id' => $c->id, 'date_paiement' => now()->toDateString(), 'montant' => 1_500_000,
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/dossiers/{$this->dossier->reference}/paiements", [
            'date_paiement' => now()->toDateString(), 'montant' => 1_000_000,
        ])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(1_500_000, (float) Paiement::where('facture_id', $c->id)->sum('montant'), 0.01);
        $this->assertEqualsWithDelta(1_000_000, (float) Paiement::where('facture_id', $this->principale->id)->sum('montant'), 0.01);
        // Le solde du dossier compte les deux factures.
        $this->assertEqualsWithDelta(3_500_000, $this->dossier->fresh()->soldeRestant(), 0.01);
    }

    public function test_une_facture_d_un_autre_dossier_ne_peut_pas_etre_reglee_ici(): void
    {
        $autre = Dossier::create([
            'reference' => 'SOC-2026-7002', 'type_acte_id' => $this->dossier->type_acte_id, 'etape' => EtapeDossier::Formalites,
            'redacteur_id' => $this->admin->id, 'objet' => 'Autre',
        ]);
        $factureAutre = Facture::create(['dossier_id' => $autre->id, 'statut' => StatutFacture::Emise, 'note_numero' => 'X/1', 'total_chiffres' => 10]);

        $this->actingAs($this->admin)->post("/dossiers/{$this->dossier->reference}/paiements", [
            'facture_id' => $factureAutre->id, 'date_paiement' => now()->toDateString(), 'montant' => 5,
        ])->assertSessionHasErrors('facture_id');
    }
}
