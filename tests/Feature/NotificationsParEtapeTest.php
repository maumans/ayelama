<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFacture;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\TypeActe;
use App\Models\User;
use App\Notifications\ActesAProduireNotification;
use App\Notifications\ExpeditionAFaireNotification;
use App\Notifications\FactureAEncaisserNotification;
use App\Services\DossierStepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * « Notification par mail pour chaque tâche à traiter » — retour R22, 2026-11-16.
 *
 * Avant : l'entrée en Édition et en Expédition ne notifiait personne. La règle vit désormais dans
 * un `match` exhaustif (DossierStepService::tachesALEntree) ; ce test parcourt toutes les étapes.
 */
class NotificationsParEtapeTest extends TestCase
{
    use RefreshDatabase;

    /** Étapes sans tâche à l'entrée, et pourquoi — la liste ne peut que rétrécir. */
    private const SANS_TACHE = [
        'initialisation' => 'un dossier y naît : notifierAssignations() prévient chaque assigné',
        'cloture'        => 'étape terminale, dossier figé',
    ];

    private function utilisateur(RoleUtilisateur $role): User
    {
        $user = User::factory()->create(['notifications_email' => true]);
        $user->syncRoles([$role]);

        return $user;
    }

    private function dossier(EtapeDossier $etape, array $attributs = []): Dossier
    {
        $type = TypeActe::firstOrCreate(['code' => 'TST-NOT'], ['label' => 'Type de test', 'categorie' => 'societe']);

        return Dossier::create([
            'reference' => 'TST-2026-' . fake()->unique()->numerify('####'), 'type_acte_id' => $type->id,
            'etape' => $etape, 'objet' => 'Dossier de test des notifications', ...$attributs,
        ]);
    }

    public function test_chaque_etape_met_quelquun_au_travail_ou_dit_pourquoi_non(): void
    {
        $service = app(DossierStepService::class);
        $dossier = $this->dossier(EtapeDossier::Edition);

        foreach (EtapeDossier::cases() as $etape) {
            $taches = $service->tachesALEntree($dossier, $etape);

            if (array_key_exists($etape->value, self::SANS_TACHE)) {
                $this->assertSame([], $taches, "{$etape->value} est déclarée sans tâche : " . self::SANS_TACHE[$etape->value]);
                continue;
            }

            $this->assertNotEmpty($taches, "L'entrée en « {$etape->label()} » ne notifie personne. Ajoutez sa tâche dans tachesALEntree().");
        }
    }

    public function test_lentree_en_edition_previent_le_redacteur(): void
    {
        $redacteur = $this->utilisateur(RoleUtilisateur::Clerc);
        $taches = app(DossierStepService::class)->tachesALEntree($this->dossier(EtapeDossier::Edition, ['redacteur_id' => $redacteur->id]), EtapeDossier::Edition);

        $this->assertSame(RoleUtilisateur::Clerc, $taches[0]['roles']);
        $this->assertInstanceOf(ActesAProduireNotification::class, $taches[0]['notification']);
    }

    public function test_lentree_en_expedition_previent_formaliste_notaire_et_comptable_par_mail(): void
    {
        Notification::fake();

        $formaliste = $this->utilisateur(RoleUtilisateur::Formaliste);
        $notaire    = $this->utilisateur(RoleUtilisateur::Notaire);
        $comptable  = $this->utilisateur(RoleUtilisateur::Comptable);
        $redacteur  = $this->utilisateur(RoleUtilisateur::Clerc);

        $dossier = $this->dossier(EtapeDossier::Formalites, [
            'formaliste_id' => $formaliste->id, 'notaire_id' => $notaire->id, 'redacteur_id' => $redacteur->id,
        ]);
        Facture::create(['dossier_id' => $dossier->id, 'statut' => StatutFacture::Proforma, 'note_date' => now(), 'objet' => 'Test', 'total_chiffres' => 500000]);

        app(DossierStepService::class)->avancer($dossier, $redacteur);

        $this->assertSame(EtapeDossier::Expedition, $dossier->fresh()->etape);
        foreach ([$formaliste, $notaire] as $u) {
            Notification::assertSentTo($u, ExpeditionAFaireNotification::class, fn ($n, array $canaux) => in_array('mail', $canaux, true));
        }
        Notification::assertSentTo($comptable, FactureAEncaisserNotification::class, fn ($n, array $canaux) => in_array('mail', $canaux, true));
        // Le comptable seul : le repli habituel aurait ajouté le notaire, déjà prévenu.
        Notification::assertNotSentTo($notaire, FactureAEncaisserNotification::class);
        Notification::assertNotSentTo($redacteur, ExpeditionAFaireNotification::class);
    }

    public function test_une_facture_soldee_ne_derange_pas_le_comptable(): void
    {
        Notification::fake();

        $comptable = $this->utilisateur(RoleUtilisateur::Comptable);
        $redacteur = $this->utilisateur(RoleUtilisateur::Clerc);
        $dossier   = $this->dossier(EtapeDossier::Formalites, ['redacteur_id' => $redacteur->id]);

        app(DossierStepService::class)->avancer($dossier, $redacteur); // aucune facture : rien à encaisser

        Notification::assertNotSentTo($comptable, FactureAEncaisserNotification::class);
    }
}
