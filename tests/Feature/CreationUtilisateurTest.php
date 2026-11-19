<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Je n'arrive pas à ajouter un utilisateur » (2026-11-19) : un mot de passe de 8 caractères,
 * refusé par `Password::defaults()`, et un refus que la fenêtre n'affichait pas. La fenêtre
 * vérifie désormais les exigences avant l'envoi avec `motDePasseConforme()`
 * (`PasswordRequirements.jsx`) : ce test garde ce miroir d'accord avec la règle du serveur.
 */
class CreationUtilisateurTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $this->admin->id, 'role' => RoleUtilisateur::Administrateur->value]);
    }

    private function creer(string $motDePasse)
    {
        return $this->actingAs($this->admin)->post('/parametres/utilisateurs', [
            'name' => 'Mamadou Test', 'email' => 'mamadou.test@example.com', 'password' => $motDePasse,
            'roles' => [RoleUtilisateur::Clerc->value], 'initiales' => 'MT', 'telephone' => '621 86 92 45',
        ]);
    }

    public function test_un_mot_de_passe_conforme_cree_le_compte(): void
    {
        $this->creer('Ayelema-2026!x')->assertSessionHasNoErrors();

        $user = User::where('email', 'mamadou.test@example.com')->sole();
        $this->assertTrue($user->hasRole(RoleUtilisateur::Clerc));
    }

    public function test_le_mot_de_passe_de_la_capture_est_refuse_avec_un_message(): void
    {
        $this->creer('azerty12')->assertSessionHasErrors('password');
        $this->assertFalse(User::where('email', 'mamadou.test@example.com')->exists());
    }

    /**
     * Les cinq règles affichées par `PasswordRequirements.jsx`, une par une : chaque mot de
     * passe n'en manque qu'une. Si le serveur durcit ou assouplit sa politique sans le miroir,
     * l'un de ces cas change de verdict.
     */
    public function test_le_miroir_js_suit_la_politique_du_serveur(): void
    {
        $js = file_get_contents(resource_path('js/Components/PasswordRequirements.jsx'));
        $this->assertStringContainsString('v.length >= 12', $js);

        foreach (['Ayelema-26!' => '11 caractères', 'ayelema-2026!' => 'sans majuscule', 'AYELEMA-2026!' => 'sans minuscule',
                  'Ayelema-Notaire!' => 'sans chiffre', 'Ayelema20261x' => 'sans symbole'] as $mdp => $manque) {
            $this->creer($mdp)->assertSessionHasErrors('password', "« {$mdp} » ({$manque}) devrait être refusé");
        }
    }
}
