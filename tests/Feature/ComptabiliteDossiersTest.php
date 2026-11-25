<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\NatureLigne;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFacture;
use App\Enums\StatutFormalite;
use App\Models\Bareme;
use App\Models\Decaissement;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\Formalite;
use App\Models\LigneFacture;
use App\Models\Paiement;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FacturationService;
use App\Services\SituationFinanciere;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La comptabilité des dossiers (2026-11-23) : honoraire ou débours, versements aux organismes,
 * situation financière, vue d'ensemble. Pas de comptabilité générale — l'étude ne la demande pas.
 */
class ComptabiliteDossiersTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;
    private TypeActe $type;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comptable = User::factory()->create(['actif' => true]);
        $this->comptable->syncRoles([RoleUtilisateur::Comptable]);
        $this->type = TypeActe::firstOrCreate(['code' => 'SOC-SARLU'], ['label' => 'SARLU', 'categorie' => 'societe']);
    }

    private function dossier(string $ref = 'SOC-2026-6001', EtapeDossier $etape = EtapeDossier::Formalites): Dossier
    {
        return Dossier::create([
            'reference' => $ref, 'type_acte_id' => $this->type->id, 'etape' => $etape,
            'redacteur_id' => $this->comptable->id, 'objet' => 'Constitution de test',
        ]);
    }

    /** Une facture émise : 4 500 000 d'honoraires, 640 000 de débours. */
    private function factureEmise(Dossier $dossier): Facture
    {
        $f = Facture::create(['dossier_id' => $dossier->id, 'statut' => StatutFacture::Emise, 'note_numero' => '9' . $dossier->id . '/MAB/26', 'note_date' => now(), 'total_chiffres' => 0]);
        LigneFacture::create(['facture_id' => $f->id, 'designation' => 'Honoraires Forfaitaires', 'quantite' => 1, 'montant' => 4_500_000, 'nature' => NatureLigne::Honoraire]);
        LigneFacture::create(['facture_id' => $f->id, 'designation' => 'Frais APIP et RCCM', 'quantite' => 1, 'montant' => 390_000, 'nature' => NatureLigne::Debours]);
        LigneFacture::create(['facture_id' => $f->id, 'designation' => "Insertion dans un Journal d'Annonces Légales", 'quantite' => 1, 'montant' => 250_000, 'nature' => NatureLigne::Debours]);

        return $f->recalculerTotal();
    }

    // ═══ Honoraire ou débours ════════════════════════════════════════════

    public function test_un_bareme_recoit_sa_nature_de_son_organisme_et_la_ligne_la_copie(): void
    {
        $honoraires = Bareme::create(['type_acte_id' => $this->type->id, 'organisme' => 'Notaire', 'libelle' => 'Honoraires', 'montant_fixe' => 100, 'base_calcul' => 'montant_fixe', 'actif' => true]);
        $greffe = Bareme::create(['type_acte_id' => $this->type->id, 'organisme' => 'Greffe', 'libelle' => 'Greffe', 'montant_fixe' => 50, 'base_calcul' => 'montant_fixe', 'actif' => true]);

        $this->assertSame(NatureLigne::Honoraire, $honoraires->nature);
        $this->assertSame(NatureLigne::Debours, $greffe->nature);

        $lignes = app(FacturationService::class)->genererFacture($this->dossier())->lignes->keyBy('designation');
        $this->assertSame(NatureLigne::Honoraire, $lignes['Honoraires']->nature);
        $this->assertSame(NatureLigne::Debours, $lignes['Greffe']->nature);
    }

    public function test_une_ligne_ajoutee_a_la_main_declare_sa_nature_qui_decide_de_la_remise(): void
    {
        $facture = Facture::create(['dossier_id' => $this->dossier()->id, 'statut' => StatutFacture::Proforma, 'total_chiffres' => 0]);
        $url = "/factures/{$facture->id}/lignes";

        $this->actingAs($this->comptable)->post($url, ['designation' => 'Sans nature', 'quantite' => 1, 'montant' => 10])
            ->assertSessionHasErrors('nature');

        $this->actingAs($this->comptable)->post($url, ['designation' => 'Insertion JAL', 'quantite' => 1, 'montant' => 250_000, 'nature' => 'debours'])->assertSessionHasNoErrors();
        $this->actingAs($this->comptable)->post($url, ['designation' => 'Conseil', 'quantite' => 1, 'montant' => 100_000, 'nature' => 'honoraire'])->assertSessionHasNoErrors();

        $lignes = $facture->lignes()->get()->keyBy('designation');
        $this->assertFalse($lignes['Insertion JAL']->remise_autorisee, 'Un débours ne se remise pas.');
        $this->assertTrue($lignes['Conseil']->remise_autorisee);
    }

    public function test_un_moyen_de_paiement_hors_liste_est_refuse(): void
    {
        $dossier = $this->dossier();
        $this->factureEmise($dossier);

        $this->actingAs($this->comptable)->post("/dossiers/{$dossier->reference}/paiements", [
            'date_paiement' => now()->toDateString(), 'montant' => 1000, 'moyen_paiement' => 'troc',
        ])->assertSessionHasErrors('moyen_paiement');
    }

    // ═══ Versements aux organismes ═══════════════════════════════════════

    public function test_le_montant_paye_au_depot_devient_un_versement_mis_a_jour_au_redepot(): void
    {
        $admin = User::factory()->create(['actif' => true]);
        $admin->syncRoles([RoleUtilisateur::Administrateur]);
        $dossier = $this->dossier();
        $formalite = Formalite::create(['dossier_id' => $dossier->id, 'organisme' => 'apip', 'libelle' => 'Frais APIP et RCCM', 'statut' => StatutFormalite::ADeposer]);

        $this->actingAs($admin)->post("/formalites/{$formalite->id}/deposer", [
            'date_depot' => '2026-11-20', 'montant_paye' => 390000, 'moyen' => 'especes', 'numero_recepisse' => 'APIP-001',
        ])->assertSessionHasNoErrors();

        $versement = Decaissement::where('formalite_id', $formalite->id)->sole();
        $this->assertTrue($versement->via_depot);
        $this->assertSame('APIP-001', $versement->reference);
        $this->assertSame('especes', $versement->moyen->value);
        $this->assertEqualsWithDelta(390_000, (float) $formalite->fresh()->montant_paye, 0.01);

        // Redépôt : le même versement est corrigé, pas doublé.
        $this->actingAs($admin)->post("/formalites/{$formalite->id}/deposer", ['date_depot' => '2026-11-22', 'montant_paye' => 400000]);
        $this->assertSame(1, Decaissement::where('formalite_id', $formalite->id)->count());
        $this->assertEqualsWithDelta(400_000, (float) $formalite->fresh()->montant_paye, 0.01);

        // Un versement de plus, saisi à part, s'ajoute à la somme de la formalité.
        Decaissement::create(['dossier_id' => $dossier->id, 'formalite_id' => $formalite->id, 'libelle' => 'Complément', 'date_decaissement' => '2026-11-23', 'montant' => 50_000]);
        $this->assertEqualsWithDelta(450_000, (float) $formalite->fresh()->montant_paye, 0.01);
    }

    public function test_les_versements_se_saisissent_se_corrigent_et_se_suppriment_avec_trace(): void
    {
        $dossier = $this->dossier();

        $this->actingAs($this->comptable)->post("/dossiers/{$dossier->reference}/decaissements", [
            'libelle' => 'Insertion JAL', 'organisme' => 'Le Lynx', 'date_decaissement' => '2026-11-21', 'montant' => 250000, 'moyen' => 'cheque',
        ])->assertSessionHasNoErrors();
        $v = Decaissement::sole();

        $this->actingAs($this->comptable)->patch("/decaissements/{$v->id}", [
            'libelle' => 'Insertion JAL', 'date_decaissement' => '2026-11-21', 'montant' => 260000,
        ])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(260_000, (float) $v->fresh()->montant, 0.01);

        $this->actingAs($this->comptable)->delete("/decaissements/{$v->id}")->assertSessionHasNoErrors();
        $this->assertSame(0, Decaissement::count());

        $this->assertDatabaseHas('journal_activites', ['dossier_id' => $dossier->id, 'type' => 'facturation']);
    }

    public function test_un_versement_ne_peut_viser_la_formalite_d_un_autre_dossier(): void
    {
        $autre = Formalite::create(['dossier_id' => $this->dossier('SOC-2026-6099')->id, 'organisme' => 'apip', 'libelle' => 'APIP', 'statut' => StatutFormalite::ADeposer]);
        $dossier = $this->dossier();

        $this->actingAs($this->comptable)->post("/dossiers/{$dossier->reference}/decaissements", [
            'libelle' => 'X', 'date_decaissement' => '2026-11-21', 'montant' => 10, 'formalite_id' => $autre->id,
        ])->assertSessionHasErrors('formalite_id');
    }

    public function test_un_dossier_cloture_ne_recoit_plus_de_versement(): void
    {
        $dossier = $this->dossier('SOC-2026-6002', EtapeDossier::Cloture);

        $this->actingAs($this->comptable)->post("/dossiers/{$dossier->reference}/decaissements", [
            'libelle' => 'X', 'date_decaissement' => '2026-11-21', 'montant' => 10,
        ])->assertForbidden();
    }

    // ═══ Situation financière ════════════════════════════════════════════

    public function test_la_situation_dit_ce_qui_est_du_encaisse_verse_et_detenu(): void
    {
        $dossier = $this->dossier();
        $facture = $this->factureEmise($dossier);
        Paiement::create(['facture_id' => $facture->id, 'date_paiement' => now(), 'montant' => 3_000_000]);
        Decaissement::create(['dossier_id' => $dossier->id, 'libelle' => 'APIP', 'date_decaissement' => now(), 'montant' => 390_000]);
        // Une proforma n'est pas due : elle est montrée à part.
        Facture::create(['dossier_id' => $dossier->id, 'statut' => StatutFacture::Proforma, 'total_chiffres' => 1_500_000]);

        $s = SituationFinanciere::pour($dossier->fresh());

        $this->assertEqualsWithDelta(4_500_000, $s['honorairesFactures'], 0.01);
        $this->assertEqualsWithDelta(640_000, $s['deboursFactures'], 0.01);
        $this->assertEqualsWithDelta(3_000_000, $s['encaisse'], 0.01);
        $this->assertEqualsWithDelta(2_140_000, $s['resteDu'], 0.01);
        $this->assertEqualsWithDelta(390_000, $s['deboursVerses'], 0.01);
        $this->assertEqualsWithDelta(250_000, $s['deboursAVerser'], 0.01);
        $this->assertEqualsWithDelta(2_610_000, $s['provision'], 0.01);
        $this->assertEqualsWithDelta(0, $s['avance'], 0.01);
        $this->assertEqualsWithDelta(1_500_000, $s['enProforma'], 0.01);
        $this->assertSame([], $s['alertes']);
    }

    public function test_une_avance_et_un_versement_non_refacture_sont_signales(): void
    {
        // Les 7 dossiers mesurés le 2026-11-20 où l'étude avait versé plus qu'encaissé.
        $dossier = $this->dossier();
        Decaissement::create(['dossier_id' => $dossier->id, 'libelle' => 'Droits', 'date_decaissement' => now(), 'montant' => 6_000_000]);

        $s = SituationFinanciere::pour($dossier->fresh());

        $this->assertEqualsWithDelta(6_000_000, $s['avance'], 0.01);
        $this->assertEqualsWithDelta(6_000_000, $s['versesNonFactures'], 0.01);
        $this->assertCount(2, $s['alertes']);
    }

    // ═══ Vue d'ensemble ══════════════════════════════════════════════════

    public function test_la_vue_d_ensemble_filtre_et_s_exporte_pour_excel(): void
    {
        $this->withoutVite(); // la page n'est pas dans le manifeste de build
        $avance = $this->dossier('SOC-2026-6010');
        Decaissement::create(['dossier_id' => $avance->id, 'libelle' => 'Droits', 'date_decaissement' => now(), 'montant' => 100_000]);
        $sain = $this->dossier('SOC-2026-6011');
        $facture = $this->factureEmise($sain);
        Paiement::create(['facture_id' => $facture->id, 'date_paiement' => now(), 'montant' => $facture->total_chiffres]);

        $this->actingAs($this->comptable)->get('/comptabilite')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Comptabilite/Index')->has('lignes', 2));

        $this->actingAs($this->comptable)->get('/comptabilite?filtre=avances')
            ->assertInertia(fn ($p) => $p->has('lignes', 1)->where('lignes.0.reference', 'SOC-2026-6010'));

        $csv = $this->actingAs($this->comptable)->get('/comptabilite/export.csv')->assertOk()->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('SOC-2026-6011;', $csv);
    }

    public function test_les_criteres_se_combinent_et_suivent_l_export(): void
    {
        $this->withoutVite();
        $ancien = $this->dossier('SOC-2026-6020', EtapeDossier::Formalites);
        $ancien->forceFill(['created_at' => '2026-01-15 10:00:00'])->save();
        $facture = $this->factureEmise($ancien);
        Paiement::create(['facture_id' => $facture->id, 'date_paiement' => now(), 'montant' => $facture->total_chiffres]);

        $recent = $this->dossier('SOC-2026-6021', EtapeDossier::Formalites);
        $recent->forceFill(['objet' => 'Cession de parts'])->save();
        Decaissement::create(['dossier_id' => $recent->id, 'libelle' => 'Droits', 'date_decaissement' => now(), 'montant' => 100_000]);

        $page = fn (string $qs) => $this->actingAs($this->comptable)->get('/comptabilite?' . $qs);

        $page('filtre=soldes')->assertInertia(fn ($p) => $p->has('lignes', 1)->where('lignes.0.reference', 'SOC-2026-6020'));
        $page('du=2026-06-01')->assertInertia(fn ($p) => $p->has('lignes', 1)->where('lignes.0.reference', 'SOC-2026-6021'));
        $page('au=2026-01-31')->assertInertia(fn ($p) => $p->has('lignes', 1)->where('lignes.0.reference', 'SOC-2026-6020'));
        $page('q=cession')->assertInertia(fn ($p) => $p->has('lignes', 1)->where('lignes.0.reference', 'SOC-2026-6021'));
        $page('type=' . $this->type->code . '&etape=' . EtapeDossier::Formalites->value)->assertInertia(fn ($p) => $p->has('lignes', 2)
            ->has('options.types', 1)->has('options.etapes', 1));
        // Combinés : soldé ET ouvert récemment → aucun.
        $page('filtre=soldes&du=2026-06-01')->assertInertia(fn ($p) => $p->has('lignes', 0));
        // Une valeur inconnue est ignorée, pas appliquée en silence.
        $page('etape=inexistante&filtre=nimporte')->assertInertia(fn ($p) => $p->has('lignes', 2)->where('criteres.etape', null)->where('filtre', null));

        $csv = $this->actingAs($this->comptable)->get('/comptabilite/export.csv?q=cession')->getContent();
        $this->assertStringContainsString('SOC-2026-6021;', $csv);
        $this->assertStringNotContainsString('SOC-2026-6020;', $csv);
    }

    public function test_la_comptabilite_est_reservee_a_ceux_qui_gerent_la_facturation(): void
    {
        $clerc = User::factory()->create(['actif' => true]);
        $clerc->syncRoles([RoleUtilisateur::Clerc]);

        $this->actingAs($clerc)->get('/comptabilite')->assertForbidden();
        $this->actingAs($clerc)->get('/comptabilite/export.csv')->assertForbidden();
    }
}
