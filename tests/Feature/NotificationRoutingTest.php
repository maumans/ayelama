<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use App\Notifications\CertificationValideeNotification;
use App\Notifications\DossierRenvoyeNotification;
use App\Notifications\FormalitesAFaireNotification;
use App\Notifications\RevisionEnAttenteNotification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Couvre le routage des notifications : qui reçoit quoi, et surtout qui ne
 * reçoit pas. Avant NotificationService, tout partait aux 4 assignés du dossier
 * et rien ne partait quand le poste concerné n'était pas assigné.
 */
class NotificationRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(RoleUtilisateur $role, array $attributs = []): User
    {
        $user = User::factory()->create($attributs);
        $user->syncRoles([$role]);

        return $user;
    }

    private function dossier(array $attributs = []): Dossier
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        return Dossier::create(array_merge([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => $this->utilisateur(RoleUtilisateur::Clerc)->id,
            'objet'        => 'Dossier de test pour le routage des notifications',
        ], $attributs));
    }

    public function test_le_certificateur_assigne_est_le_seul_notifie_de_la_certification(): void
    {
        Notification::fake();

        $certificateur = $this->utilisateur(RoleUtilisateur::Reviseur);
        $formaliste    = $this->utilisateur(RoleUtilisateur::Formaliste);
        $dossier       = $this->dossier([
            'reviseur_id'   => $certificateur->id,
            'formaliste_id' => $formaliste->id,
        ]);

        app(NotificationService::class)->notifierRoles(
            $dossier,
            RoleUtilisateur::Reviseur,
            new RevisionEnAttenteNotification($dossier),
        );

        Notification::assertSentTo($certificateur, RevisionEnAttenteNotification::class);
        // Le point du correctif : le formaliste n'a aucune action à mener sur une
        // certification, il ne doit plus être notifié.
        Notification::assertNotSentTo($formaliste, RevisionEnAttenteNotification::class);
    }

    public function test_le_pool_du_role_prend_le_relais_quand_le_poste_nest_pas_assigne(): void
    {
        Notification::fake();

        $formalisteA = $this->utilisateur(RoleUtilisateur::Formaliste);
        $formalisteB = $this->utilisateur(RoleUtilisateur::Formaliste);
        $notaire     = $this->utilisateur(RoleUtilisateur::Notaire);

        // formaliste_id volontairement nul : avant le repli, ce passage aux
        // formalités ne notifiait strictement personne.
        $dossier = $this->dossier(['notaire_id' => $notaire->id, 'formaliste_id' => null]);

        app(NotificationService::class)->notifierRoles(
            $dossier,
            RoleUtilisateur::Formaliste,
            new FormalitesAFaireNotification($dossier),
        );

        Notification::assertSentTo($formalisteA, FormalitesAFaireNotification::class);
        Notification::assertSentTo($formalisteB, FormalitesAFaireNotification::class);
        Notification::assertSentTo($notaire, FormalitesAFaireNotification::class);
    }

    public function test_un_compte_desactive_nest_jamais_notifie(): void
    {
        Notification::fake();

        $certificateur = $this->utilisateur(RoleUtilisateur::Reviseur, ['actif' => false]);
        $dossier       = $this->dossier(['reviseur_id' => $certificateur->id]);

        app(NotificationService::class)->notifierRoles(
            $dossier,
            RoleUtilisateur::Reviseur,
            new RevisionEnAttenteNotification($dossier),
            repli: false,
        );

        Notification::assertNothingSent();
    }

    public function test_lacteur_declencheur_nest_pas_notifie_de_sa_propre_action(): void
    {
        Notification::fake();

        // Cas réel : un notaire qui porte aussi le rôle de rédacteur renvoie son
        // propre dossier en correction.
        $redacteur = $this->utilisateur(RoleUtilisateur::Clerc);
        $dossier   = $this->dossier(['redacteur_id' => $redacteur->id]);

        app(NotificationService::class)->notifierRoles(
            $dossier,
            RoleUtilisateur::Clerc,
            new DossierRenvoyeNotification($dossier, 'Certification des actes', 'Édition actes', 'Point 3 à corriger'),
            repli: false,
            sauf: $redacteur,
        );

        Notification::assertNothingSent();
    }

    public function test_le_canal_mail_est_retire_sans_email_ou_si_loption_est_coupee(): void
    {
        $dossier = $this->dossier();

        $complet = $this->utilisateur(RoleUtilisateur::Reviseur);
        $coupe   = $this->utilisateur(RoleUtilisateur::Reviseur, ['notifications_email' => false]);
        $sansMail = $this->utilisateur(RoleUtilisateur::Reviseur, ['email' => '']);

        $notification = new RevisionEnAttenteNotification($dossier);

        $this->assertSame(['database', 'broadcast', 'mail'], $notification->via($complet));

        // Historique et temps réel restent actifs : seul l'email est retiré.
        $this->assertSame(['database', 'broadcast'], $notification->via($coupe));
        $this->assertSame(['database', 'broadcast'], $notification->via($sansMail));
    }

    public function test_le_broadcast_part_en_synchrone_pour_ne_pas_dependre_dun_worker(): void
    {
        $dossier = $this->dossier();
        $user    = $this->utilisateur(RoleUtilisateur::Reviseur);

        $message = (new RevisionEnAttenteNotification($dossier))->toBroadcast($user);

        // C'est cette connexion qui fait exécuter le BroadcastEvent immédiatement
        // au lieu de l'empiler sur la queue `database` (sans worker : jamais émis).
        $this->assertSame('sync', $message->connection);
        $this->assertSame('revision', $message->data['type']);
    }

    public function test_le_passage_en_signature_notifie_notaire_et_redacteur(): void
    {
        Notification::fake();

        $redacteur = $this->utilisateur(RoleUtilisateur::Clerc);
        $notaire   = $this->utilisateur(RoleUtilisateur::Notaire);
        $dossier   = $this->dossier(['redacteur_id' => $redacteur->id, 'notaire_id' => $notaire->id]);

        app(NotificationService::class)->notifierRoles(
            $dossier,
            [RoleUtilisateur::Notaire, RoleUtilisateur::Clerc],
            new CertificationValideeNotification($dossier),
        );

        Notification::assertSentTo($notaire, CertificationValideeNotification::class);
        Notification::assertSentTo($redacteur, CertificationValideeNotification::class);
    }

    public function test_ayants_droit_exclut_les_comptes_desactives(): void
    {
        $actif   = $this->utilisateur(RoleUtilisateur::Reviseur);
        $inactif = $this->utilisateur(RoleUtilisateur::Formaliste, ['actif' => false]);
        $dossier = $this->dossier(['reviseur_id' => $actif->id, 'formaliste_id' => $inactif->id]);

        $ayantsDroit = $dossier->ayantsDroit()->pluck('id');

        $this->assertTrue($ayantsDroit->contains($actif->id));
        $this->assertFalse($ayantsDroit->contains($inactif->id));
    }
}
