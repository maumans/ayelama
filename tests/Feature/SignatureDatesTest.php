<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Dates de signature — retour R09, arbitré le 2026-11-16 : ni dans le futur, ni avant la création
 * du dossier, et rien de plus (voir Dossier::bornesDateSignature()).
 */
class SignatureDatesTest extends TestCase
{
    use RefreshDatabase;

    private User $notaire;
    private Dossier $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-16 10:00:00');

        $this->notaire = User::factory()->create();
        $this->notaire->syncRoles([RoleUtilisateur::Notaire]);
        $type = TypeActe::create(['code' => 'TST-SIG', 'label' => 'Type de test', 'categorie' => 'societe']);

        $this->dossier = Dossier::create([
            'reference' => 'TST-SIG-1', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Signature,
            'redacteur_id' => $this->notaire->id, 'notaire_id' => $this->notaire->id, 'objet' => 'Dossier de test signature',
        ]);
        $this->dossier->forceFill(['created_at' => '2026-11-01 09:00:00'])->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function signer(array $dates)
    {
        return $this->actingAs($this->notaire)->patch("/dossiers/{$this->dossier->reference}/signatures", $dates);
    }

    public function test_une_signature_future_est_refusee(): void
    {
        $this->signer(['date_signature_client' => '2026-11-17'])
            ->assertSessionHasErrors(['date_signature_client' => 'Une date de signature ne peut pas être dans le futur.']);
        $this->assertNull($this->dossier->fresh()->date_signature_client);
    }

    public function test_une_signature_anterieure_a_la_creation_est_refusee(): void
    {
        $this->signer(['date_signature_notaire' => '2026-10-31'])
            ->assertSessionHasErrors('date_signature_notaire');
        $this->assertNull($this->dossier->fresh()->date_signature_notaire);
    }

    public function test_les_bornes_sont_incluses(): void
    {
        // Signer le jour de la création, ou aujourd'hui, est permis.
        $this->signer(['date_signature_client' => '2026-11-01', 'date_signature_notaire' => '2026-11-16'])
            ->assertSessionHasNoErrors();
    }

    public function test_le_notaire_peut_signer_avant_le_client(): void
    {
        // Mesuré : SOC-2026-0013 et 0014. L'étude n'en fait pas une incohérence.
        $this->signer(['date_signature_client' => '2026-11-10', 'date_signature_notaire' => '2026-11-09'])
            ->assertSessionHasNoErrors();
    }

    public function test_lecran_recoit_les_memes_bornes(): void
    {
        $this->assertSame(['min' => '2026-11-01', 'max' => '2026-11-16'], $this->dossier->bornesDateSignature());

        $this->actingAs($this->notaire)->get("/dossiers/{$this->dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->where('dossier.bornesDateSignature.min', '2026-11-01')
                ->where('dossier.bornesDateSignature.max', '2026-11-16'));
    }
}
