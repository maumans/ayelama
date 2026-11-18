<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Modifier » sur un dossier en Expédition laissait réécrire l'objet après la signature
 * (2026-11-18). L'objet et la valeur sont désormais figés après l'Édition ; le suivi et les
 * assignations restent modifiables jusqu'à l'archivage. Voir DossierPolicy::modifierObjet.
 */
class ObjetDossierFigeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => RoleUtilisateur::Administrateur->value]);
    }

    private function dossier(EtapeDossier $etape): Dossier
    {
        $type = TypeActe::firstOrCreate(['code' => 'SOC-SARLU'], ['label' => 'SARLU', 'categorie' => 'societe', 'prefixe_reference' => 'SOC']);

        return Dossier::create([
            'reference' => 'SOC-2026-' . random_int(9000, 9999), 'type_acte_id' => $type->id, 'etape' => $etape,
            'redacteur_id' => $this->admin->id, 'objet' => 'Constitution de la société Test', 'valeur' => 1000000,
        ]);
    }

    public function test_apres_ledition_lobjet_et_la_valeur_sont_refuses(): void
    {
        foreach ([EtapeDossier::Revision, EtapeDossier::Signature, EtapeDossier::Formalites, EtapeDossier::Expedition] as $etape) {
            $dossier = $this->dossier($etape);

            $this->actingAs($this->admin)
                ->patch("/dossiers/{$dossier->reference}", ['objet' => 'Objet réécrit après signature', 'valeur' => 5])
                ->assertSessionHasErrors(['objet', 'valeur']);

            $frais = $dossier->fresh();
            $this->assertSame('Constitution de la société Test', $frais->objet, $etape->value);
            $this->assertEquals(1000000, $frais->valeur, $etape->value);
        }
    }

    public function test_le_suivi_reste_modifiable_jusqua_larchivage(): void
    {
        $dossier = $this->dossier(EtapeDossier::Expedition);

        $this->actingAs($this->admin)
            ->patch("/dossiers/{$dossier->reference}", ['urgent' => true, 'notes' => 'Relancer le greffe'])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $dossier->fresh()->urgent);
        $this->assertSame('Relancer le greffe', $dossier->fresh()->notes);
    }

    public function test_en_edition_lobjet_reste_modifiable(): void
    {
        foreach ([EtapeDossier::Initialisation, EtapeDossier::Edition] as $etape) {
            $dossier = $this->dossier($etape);

            $this->actingAs($this->admin)
                ->patch("/dossiers/{$dossier->reference}", ['objet' => 'Constitution de la société Corrigée'])
                ->assertSessionHasNoErrors();

            $this->assertSame('Constitution de la société Corrigée', $dossier->fresh()->objet, $etape->value);
        }
    }

    /**
     * La modale s'initialise sur `dossier.notaire.id`. Servi sans `id`, la liste partait de
     * « Aucun » et Enregistrer désassignait le notaire (constaté le 2026-11-18).
     */
    public function test_la_fiche_sert_lidentifiant_de_chaque_assigne(): void
    {
        $dossier = $this->dossier(EtapeDossier::Expedition);
        $dossier->update(['notaire_id' => $this->admin->id, 'reviseur_id' => $this->admin->id, 'formaliste_id' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->get("/dossiers/{$dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->where('dossier.notaire.id', $this->admin->id)
                ->where('dossier.reviseur.id', $this->admin->id)
                ->where('dossier.formaliste.id', $this->admin->id)
                ->where('dossier.redacteur.id', $this->admin->id)
                ->where('can.modifierObjet', false)
                ->etc());
    }

    public function test_un_dossier_archive_ne_se_modifie_plus_du_tout(): void
    {
        $dossier = $this->dossier(EtapeDossier::Cloture);

        $this->actingAs($this->admin)
            ->patch("/dossiers/{$dossier->reference}", ['notes' => 'x'])
            ->assertForbidden();

        $this->assertFalse($this->admin->can('update', $dossier));
        $this->assertFalse($this->admin->can('modifierObjet', $dossier));
    }
}
