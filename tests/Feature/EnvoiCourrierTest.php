<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Transmission d'un courrier (2026-11-17) : un clic suffisait à le marquer envoyé, daté du clic
 * et sans personne nommée. L'étude exige la date de transmission et le transmetteur.
 */
class EnvoiCourrierTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Courrier $courrier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        $type = TypeActe::firstOrCreate(['code' => 'SOC-SARLU'], ['label' => 'SARLU', 'categorie' => 'societe', 'prefixe_reference' => 'SOC']);
        $dossier = Dossier::create([
            'reference' => 'SOC-2026-9001', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Expedition,
            'redacteur_id' => $this->admin->id, 'objet' => 'Test',
        ]);

        $this->courrier = Courrier::create([
            'reference' => 'COU-2026-9001', 'dossier_id' => $dossier->id, 'redacteur_id' => $this->admin->id,
            'destinataire' => 'Client', 'objet' => 'Transmission', 'type' => 'transmission', 'statut' => 'brouillon',
        ]);
        $this->courrier->forceFill(['created_at' => now()->subDays(10)])->save();
    }

    public function test_un_clic_ne_suffit_plus_a_marquer_envoye(): void
    {
        $this->actingAs($this->admin)
            ->patch("/courriers/{$this->courrier->id}", ['statut' => 'envoye'])
            ->assertSessionHasErrors('statut');

        $this->assertSame('brouillon', $this->courrier->fresh()->statut);
    }

    public function test_date_et_transmetteur_sont_exiges(): void
    {
        $this->actingAs($this->admin)
            ->post("/courriers/{$this->courrier->id}/envoyer", [])
            ->assertSessionHasErrors(['envoye_le', 'transmis_par']);

        $this->assertSame('brouillon', $this->courrier->fresh()->statut);
    }

    public function test_la_date_nest_ni_future_ni_anterieure_a_la_creation(): void
    {
        foreach ([now()->addDay(), now()->subDays(11)] as $date) {
            $this->actingAs($this->admin)
                ->post("/courriers/{$this->courrier->id}/envoyer", ['envoye_le' => $date->toDateString(), 'transmis_par' => 'Coursier'])
                ->assertSessionHasErrors('envoye_le');
        }

        $this->assertSame('brouillon', $this->courrier->fresh()->statut);
    }

    public function test_la_transmission_est_enregistree_et_journalisee(): void
    {
        $date = now()->subDays(2)->toDateString();

        $this->actingAs($this->admin)
            ->post("/courriers/{$this->courrier->id}/envoyer", ['envoye_le' => $date, 'transmis_par' => '  Mamadou Coursier '])
            ->assertSessionHasNoErrors();

        $c = $this->courrier->fresh();
        $this->assertSame('envoye', $c->statut);
        $this->assertSame($date, $c->envoye_at->toDateString());
        $this->assertSame('Mamadou Coursier', $c->transmis_par);
        $this->assertTrue($c->dossier->journal()->where('action', 'like', '%transmis le%par Mamadou Coursier%')->exists());
    }

    public function test_le_retour_en_brouillon_efface_la_transmission(): void
    {
        $this->courrier->update(['statut' => 'envoye', 'envoye_at' => now(), 'transmis_par' => 'X']);

        $this->actingAs($this->admin)->patch("/courriers/{$this->courrier->id}", ['statut' => 'brouillon']);

        $c = $this->courrier->fresh();
        $this->assertNull($c->envoye_at);
        $this->assertNull($c->transmis_par);
    }

    public function test_deposer_la_version_signee_ne_vaut_plus_envoi(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post("/courriers/{$this->courrier->id}/televerser-signe", ['fichier' => UploadedFile::fake()->create('signe.pdf', 10, 'application/pdf')]);

        $c = $this->courrier->fresh();
        $this->assertTrue($c->est_signe_cachete);
        $this->assertSame('brouillon', $c->statut);
        $this->assertNull($c->envoye_at);
    }

    public function test_un_courrier_signe_peut_etre_transmis(): void
    {
        $this->courrier->update(['est_signe_cachete' => true]);

        $this->actingAs($this->admin)
            ->post("/courriers/{$this->courrier->id}/envoyer", ['envoye_le' => now()->toDateString(), 'transmis_par' => 'Clerc'])
            ->assertSessionHasNoErrors();

        $this->assertSame('envoye', $this->courrier->fresh()->statut);
    }
}
