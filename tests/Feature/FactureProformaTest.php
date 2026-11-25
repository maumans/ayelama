<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFacture;
use App\Enums\TypeRemise;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FactureGeneratorService;
use App\Services\FacturePdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proforma, émission, en-tête et facture sur une page — retours R20, R11, R21 (2026-11-16).
 */
class FactureProformaTest extends TestCase
{
    use RefreshDatabase;

    private User $comptable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comptable = User::factory()->create(['name' => 'Aïssata Camara']);
        $this->comptable->syncRoles([RoleUtilisateur::Comptable]);
    }

    private function proforma(float $total = 500000, int $lignes = 1): Facture
    {
        $type = TypeActe::firstOrCreate(['code' => 'TST-FAC'], ['label' => 'Type de test', 'categorie' => 'societe']);
        $dossier = Dossier::create([
            'reference' => 'TST-2026-' . fake()->unique()->numerify('####'), 'type_acte_id' => $type->id,
            'etape' => EtapeDossier::Formalites, 'redacteur_id' => $this->comptable->id, 'objet' => 'Dossier de test facture',
        ]);
        $facture = Facture::create([
            'dossier_id' => $dossier->id, 'statut' => StatutFacture::Proforma, 'note_numero' => null,
            'note_date' => now()->subMonth(), 'objet' => 'Constitution SARLU', 'total_chiffres' => $total,
        ]);
        for ($i = 0; $i < $lignes; $i++) {
            LigneFacture::create(['facture_id' => $facture->id, 'designation' => "Prestation {$i} (précision de la ligne)", 'quantite' => 1, 'montant' => $total / max(1, $lignes)]);
        }

        return $facture->fresh();
    }

    // ── R20 : proforma ───────────────────────────────────────────────────────

    public function test_une_proforma_nest_pas_numerotee_et_ne_recoit_aucun_paiement(): void
    {
        $facture = $this->proforma();

        $this->assertNull($facture->note_numero);
        $this->assertFalse($facture->peutRecevoirPaiement());

        $this->actingAs($this->comptable)
            ->post("/dossiers/{$facture->dossier->reference}/paiements", ['date_paiement' => now()->toDateString(), 'montant' => 1000])
            ->assertSessionHasErrors('montant');
    }

    public function test_emettre_numerote_date_du_jour_et_nomme_lexpediteur(): void
    {
        $facture = $this->proforma();

        $this->actingAs($this->comptable)->post("/factures/{$facture->id}/emettre")->assertSessionHasNoErrors();

        $facture->refresh();
        $this->assertSame(StatutFacture::Emise, $facture->statut);
        // Premier numéro de l'année — 326 en 2026, reprise du registre papier (Facture::REPRISE_REGISTRE).
        $premier = \App\Models\Facture::REPRISE_REGISTRE[date('y')] ?? 1;
        $this->assertSame(sprintf('%03d/MAB/%s', $premier, date('y')), $facture->note_numero);
        $this->assertTrue($facture->note_date->isToday());
        $this->assertSame('Aïssata Camara', $facture->expediteur);
        $this->assertTrue($facture->peutRecevoirPaiement());
        $this->assertDatabaseHas('journal_activites', ['dossier_id' => $facture->dossier_id, 'type' => 'facturation']);
    }

    public function test_le_numero_suit_la_plus_haute_sequence_et_non_le_dernier_id(): void
    {
        // Émises dans un autre ordre que leur création : la plus récente (id le plus haut)
        // est émise d'abord. L'ancienne lecture « dernière ligne par id » redonnait un numéro.
        $a = $this->proforma();
        $b = $this->proforma();
        $c = $this->proforma();

        $b->emettre(); // n
        $a->emettre(); // n + 1 — id plus petit que b
        $c->emettre(); // n + 2, et non n + 1 à nouveau

        $n = Facture::REPRISE_REGISTRE[date('y')] ?? 1; // 326 en 2026
        $attendus = array_map(fn ($i) => sprintf('%03d', $n + $i), [0, 1, 2]);
        $this->assertSame($attendus, [substr($b->note_numero, 0, 3), substr($a->fresh()->note_numero, 0, 3), substr($c->fresh()->note_numero, 0, 3)]);
    }

    public function test_une_facture_ne_semet_quune_fois_et_jamais_vide(): void
    {
        $facture = $this->proforma();
        $facture->emettre();

        $this->actingAs($this->comptable)->post("/factures/{$facture->id}/emettre")->assertSessionHasErrors('facture');

        $vide = $this->proforma(total: 0, lignes: 0);
        $this->actingAs($this->comptable)->post("/factures/{$vide->id}/emettre")->assertSessionHasErrors('facture');
        $this->assertNull($vide->fresh()->note_numero);
    }

    public function test_seul_un_role_de_facturation_emet(): void
    {
        $clerc = User::factory()->create();
        $clerc->syncRoles([RoleUtilisateur::Clerc]);

        $this->actingAs($clerc)->post("/factures/{$this->proforma()->id}/emettre")->assertForbidden();
    }

    // ── R11 : date et expéditeur ─────────────────────────────────────────────

    public function test_len_tete_se_modifie_et_simprime(): void
    {
        $facture = $this->proforma();
        $facture->emettre('Aïssata Camara');

        $this->actingAs($this->comptable)
            ->patch("/factures/{$facture->id}/entete", ['note_date' => '2026-11-10', 'expediteur' => 'Mariame Sylla'])
            ->assertSessionHasNoErrors();

        $facture->refresh();
        $this->assertSame('2026-11-10', $facture->note_date->toDateString());
        $this->assertSame('Mariame Sylla', $facture->expediteur);
        $this->assertStringContainsString('Expédiée par Mariame Sylla', $this->html($facture));
    }

    // ── R21 : une page ───────────────────────────────────────────────────────

    public function test_dix_lignes_dont_trois_remisees_tiennent_sur_une_page(): void
    {
        // 10 lignes : le maximum mesuré sur les factures réelles. Trois remises : chacune ajoute
        // deux lignes de texte dans sa cellule. Plus l'expéditeur.
        $facture = $this->proforma(total: 7_500_000, lignes: 10);
        $facture->lignes->take(3)->each(fn (LigneFacture $l) => $l->update([
            'remise_autorisee' => true, 'remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10,
        ]));
        $facture->emettre('Aïssata Camara');

        $this->assertSame(1, $this->pages($facture->fresh()));
    }

    public function test_une_proforma_le_dit_et_se_nomme_dapres_son_dossier(): void
    {
        $facture = $this->proforma();

        $this->assertStringContainsString('FACTURE PROFORMA', $this->html($facture));
        $this->assertStringContainsString("Numéro attribué à l'émission", $this->html($facture));
        $this->assertSame('proforma-' . $facture->dossier->reference . '.pdf', app(FacturePdfService::class)->nomFichier($facture));
    }

    private function vue(Facture $facture): array
    {
        $g = app(FactureGeneratorService::class);
        $facture->load('lignes', 'dossier');

        return ['facture' => $facture, 'detail' => $g->detailPrestations($facture->lignes), 'totalEnLettres' => $g->totalEnLettres((float) $facture->total_chiffres)];
    }

    private function html(Facture $facture): string
    {
        return view('factures.pdf', $this->vue($facture))->render();
    }

    public function test_la_reprise_du_registre_et_les_numeros_d_essai(): void
    {
        $annee = date('y');
        $premier = Facture::REPRISE_REGISTRE[$annee] ?? 1;

        // Les essais renumérotés « TEST-… » ne comptent pas dans la séquence.
        $this->proforma()->update(['statut' => StatutFacture::Emise, 'note_numero' => "TEST-025/MAB/{$annee}"]);
        $this->assertSame(sprintf('%03d/MAB/%s', $premier, $annee), Facture::genererNumero());

        // Au-delà du point de reprise, la séquence suit son cours normal.
        $this->proforma()->update(['statut' => StatutFacture::Emise, 'note_numero' => sprintf('%03d/MAB/%s', $premier + 4, $annee)]);
        $this->assertSame(sprintf('%03d/MAB/%s', $premier + 5, $annee), Facture::genererNumero());
    }

    private function pages(Facture $facture): int
    {
        $pdf = Pdf::loadView('factures.pdf', $this->vue($facture));
        $pdf->render();

        return $pdf->getDomPDF()->getCanvas()->get_page_count();
    }
}
