<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FacturationService;
use Database\Seeders\ReglesGestionHonorairesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Décisions de l'étude du 2026-11-23 sur ses notes de frais (`docs/ANALYSE-NOTES-DE-FRAIS-2026-11.md`) :
 * débours par tranche de capital sur toutes les constitutions, libellés des notes, retrait du
 * greffe en modification. Joue la vraie chaîne des seeders, dans l'ordre de `DatabaseSeeder`.
 */
class DeboursEtLibellesBaremesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\TypeActeSeeder::class);
        $this->seed(\Database\Seeders\BaremeSeeder::class);
        $this->seed(\Database\Seeders\ReglesGestionBaremeSeeder::class);
        $this->seed(ReglesGestionHonorairesSeeder::class);
    }

    private function bareme(string $code, string $libelle): ?Bareme
    {
        return Bareme::where('type_acte_id', TypeActe::where('code', $code)->value('id'))
            ->where('libelle', $libelle)->first();
    }

    public function test_le_debours_suit_la_tranche_de_capital(): void
    {
        $debours = $this->bareme('SOC-SARL', 'Débours');

        // Les deux points que donnent les notes réelles, et les deux côtés de la coupure.
        $this->assertSame(500_000.0, $debours->calculerMontant(10_000_000));    // KOUMI
        $this->assertSame(500_000.0, $debours->calculerMontant(99_999_999));
        $this->assertSame(1_000_000.0, $debours->calculerMontant(100_000_000)); // NEXIUM
        $this->assertSame(1_000_000.0, $debours->calculerMontant(1_000_000_000));
        // Capital inconnu : la première tranche, jamais un débours nul.
        $this->assertSame(500_000.0, $debours->calculerMontant(0));
    }

    public function test_toutes_les_constitutions_portent_le_debours_a_confirmer_et_non_remisable(): void
    {
        foreach (ReglesGestionHonorairesSeeder::TYPES_CONSTITUTION as $code) {
            $debours = $this->bareme($code, 'Débours');
            $this->assertNotNull($debours, "{$code} n'a pas de débours");
            $this->assertTrue($debours->actif);
            // La grille n'est connue qu'en deux points : elle doit le dire.
            $this->assertTrue($debours->a_confirmer, "{$code} : grille de débours non confirmée, à signaler");
            $this->assertFalse($debours->remise_autorisee, 'Un débours ne se remise pas : il est reversé.');
            $this->assertFalse($debours->genere_formalite);
        }

        // Ni une modification ni une dissolution ne constituent de société.
        $this->assertNull($this->bareme('SOC-MOD', 'Débours'));
        $this->assertNull($this->bareme('SOC-DIS', 'Débours'));
    }

    public function test_une_facture_de_constitution_porte_le_debours_de_sa_tranche(): void
    {
        $dossier = Dossier::create([
            'reference' => 'SOC-2026-8001', 'type_acte_id' => TypeActe::where('code', 'SOC-SARL')->value('id'),
            'etape' => EtapeDossier::Initialisation, 'redacteur_id' => User::factory()->create()->id, 'objet' => 'Constitution NEXIUM',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => ['soc.capital_chiffres' => '100000000']]);

        $facture = app(FacturationService::class)->genererFacture($dossier->fresh('questionnaire'))->load('lignes');
        $lignes = $facture->lignes->pluck('montant', 'designation')->map(fn ($m) => (float) $m);

        $this->assertSame(1_000_000.0, $lignes['Débours']);
        $this->assertSame(4_500_000.0, $lignes['Honoraires Forfaitaires']);
        $this->assertSame(250_000.0, $lignes["Insertion dans un Journal d'Annonces Légales"]);
        // Les tarifs retirés le 2026-11-20 n'y figurent plus.
        $this->assertArrayNotHasKey('Enregistrement fiscal acte', $lignes);
        $this->assertArrayNotHasKey('Enregistrement au Tribunal de Commerce (RCCM)', $lignes);
    }

    public function test_les_libelles_sont_ceux_des_notes_de_l_etude(): void
    {
        foreach (['Honoraires Forfaitaires', "Insertion dans un Journal d'Annonces Légales", 'Timbres fiscaux', 'Rôles'] as $libelle) {
            $this->assertNotNull($this->bareme('SOC-SARLU', $libelle), "Libellé attendu : {$libelle}");
        }

        foreach (['Honoraires forfaitaires', 'Insertion JAL (Journal Annonces Légales)', 'Timbres fiscaux (à la page)', 'Rôles (à la page)'] as $ancien) {
            $this->assertSame(0, Bareme::where('libelle', $ancien)->count(), "Ancien libellé resté en base : {$ancien}");
        }

        // Les deux lignes à la page restent à la page.
        $this->assertTrue($this->bareme('SOC-SARLU', 'Timbres fiscaux')->par_page);
        $this->assertTrue($this->bareme('SOC-SARLU', 'Rôles')->par_page);
    }

    public function test_le_greffe_est_retire_des_modifications_aussi(): void
    {
        $greffe = $this->bareme('SOC-MOD', 'Enregistrement au Tribunal de Commerce (RCCM)');

        $this->assertNotNull($greffe, 'Désactivé, jamais supprimé : des factures de modification le citent.');
        $this->assertFalse($greffe->actif);
    }
}
