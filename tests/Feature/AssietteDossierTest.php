<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\FormaliteGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Valeur (GNF) » ne se saisit plus (2026-11-18) : l'assiette d'un dossier se déduit de son
 * questionnaire (`FacturationService::assiette`), pour la facture **et** pour les formalités.
 * Ces dernières lisaient encore `dossier.valeur`, que la création ne renseigne plus : une
 * formalité au pourcentage partait sans montant.
 */
class AssietteDossierTest extends TestCase
{
    use RefreshDatabase;

    private function dossierDeVente(array $donnees): Dossier
    {
        $type = TypeActe::firstOrCreate(['code' => 'VTE-TST'], ['label' => 'Vente de test', 'categorie' => 'vente', 'prefixe_reference' => 'VTE']);

        $dossier = Dossier::create([
            'reference' => 'VTE-2026-' . fake()->unique()->numerify('####'), 'type_acte_id' => $type->id,
            'etape' => EtapeDossier::Initialisation, 'redacteur_id' => User::factory()->create()->id,
            'objet' => 'Vente de test pour l’assiette',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier;
    }

    public function test_une_formalite_au_pourcentage_est_calculee_sur_lassiette_du_questionnaire(): void
    {
        $dossier = $this->dossierDeVente(['bien.prix_vente_chiffres' => '200000000']);
        Bareme::create([
            'type_acte_id' => $dossier->type_acte_id, 'organisme' => 'Impots', 'libelle' => "Droits d'enregistrement",
            'taux' => 7.5, 'base_calcul' => 'valeur_acte', 'genere_formalite' => true, 'actif' => true,
        ]);

        $this->assertNull($dossier->valeur, 'précondition : un dossier récent ne porte plus de valeur');

        app(FormaliteGenerationService::class)->genererFormalites($dossier);

        $formalite = Formalite::where('dossier_id', $dossier->id)->sole();
        $this->assertEquals(200000000, $formalite->montant_base);
        $this->assertEquals(15000000, $formalite->montant_calcule);
    }

    public function test_la_valeur_nest_plus_acceptee_ni_a_la_modification_ni_a_la_creation(): void
    {
        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);
        $dossier = $this->dossierDeVente([]);

        $this->actingAs($admin)
            ->patch("/dossiers/{$dossier->reference}", ['valeur' => 5000000])
            ->assertSessionHasErrors('valeur');

        $this->assertNull($dossier->fresh()->valeur);
        $this->assertSame(['prohibited'], (new \App\Http\Requests\StoreDossierRequest())->rules()['valeur']);
    }

    public function test_la_fiche_sert_lassiette_et_non_la_valeur(): void
    {
        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);
        $dossier = $this->dossierDeVente(['bien.prix_vente_chiffres' => '75000000']);

        $this->actingAs($admin)
            ->get("/dossiers/{$dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->where('dossier.assiette', fn ($v) => (float) $v === 75000000.0)
                ->missing('dossier.valeur')
                ->etc());
    }
}
