<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'interface est en français, messages de validation compris.
 *
 * L'application tournait en locale `en` sans aucun dossier `lang/` : l'écran de connexion
 * affichait « The email field is required. » Ces tests verrouillent la traduction, et
 * surtout le tableau `attributes` de lang/fr/validation.php — sans lui, les messages
 * citent le nom technique de la colonne (« Le champ prenom_nom est obligatoire »).
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_locale_de_lapplication_est_le_francais(): void
    {
        $this->assertSame('fr', app()->getLocale());
        // Fallback en français aussi : sinon une clé absente de lang/fr repartirait en
        // anglais, ce qui donnerait une interface mi-française mi-anglaise.
        $this->assertSame('fr', config('app.fallback_locale'));
    }

    public function test_les_erreurs_de_lecran_de_connexion_sont_en_francais(): void
    {
        $this->post('/login', [])->assertSessionHasErrors([
            'email'    => 'Le champ adresse e-mail est obligatoire.',
            'password' => 'Le champ mot de passe est obligatoire.',
        ]);

        $this->post('/login', ['email' => 'pas-un-email', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Le champ adresse e-mail doit être une adresse e-mail valide.']);
    }

    public function test_lechec_dauthentification_ne_revele_pas_si_le_compte_existe(): void
    {
        User::factory()->create(['email' => 'existe@ayelama.gn']);

        // Même message dans les deux cas : distinguer « e-mail inconnu » de « mot de passe
        // incorrect » permettrait de savoir quelles adresses ont un compte dans l'office.
        $attendu = 'Ces identifiants ne correspondent à aucun compte.';

        $this->post('/login', ['email' => 'existe@ayelama.gn', 'password' => 'MauvaisMdp!123'])
            ->assertSessionHasErrors(['email' => $attendu]);

        $this->post('/login', ['email' => 'inconnu@ayelama.gn', 'password' => 'MauvaisMdp!123'])
            ->assertSessionHasErrors(['email' => $attendu]);
    }

    public function test_les_champs_metier_portent_leur_libelle_et_non_le_nom_de_colonne(): void
    {
        $clerc = User::factory()->create();
        $clerc->syncRoles([RoleUtilisateur::Clerc]);

        // `prenom_nom` doit s'afficher en clair, pas sous son nom de colonne — et le
        // message conditionnel (`required_if:type,physique`) est reformulé pour se lire
        // naturellement plutôt que « obligatoire quand type vaut physique ».
        $this->actingAs($clerc)
            ->post('/clients', ['type' => 'physique'])
            ->assertSessionHasErrors([
                // Depuis la règle 4 (nom de famille en capitales dans les actes), c'est
                // `nom_famille` qui porte l'obligation, plus `prenom_nom`.
                'nom_famille' => 'Le nom de famille est obligatoire pour une personne physique.',
            ]);

        $this->actingAs($clerc)
            ->post('/clients', ['type' => 'morale'])
            ->assertSessionHasErrors([
                'denomination' => 'La dénomination est obligatoire pour une personne morale.',
            ]);
    }

    public function test_les_regles_de_mot_de_passe_sont_traduites(): void
    {
        $messages = validator(
            ['password' => 'faible'],
            ['password' => \Illuminate\Validation\Rules\Password::defaults()],
        )->errors()->get('password');

        $this->assertNotEmpty($messages);
        foreach ($messages as $message) {
            $this->assertStringStartsWith('Le champ mot de passe', $message);
        }
    }

    public function test_les_libelles_du_paquet_de_langue_sont_charges(): void
    {
        // Un fichier manquant se traduirait par le renvoi de la clé brute.
        $this->assertSame('Bonjour,', trans('Hello!'));
        $this->assertSame('Suivant &raquo;', trans('pagination.next'));
        $this->assertSame('Ce lien de réinitialisation est invalide ou a expiré.', trans('passwords.token'));
    }
}
