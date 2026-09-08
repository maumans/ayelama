<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_client_physique_returns_fields_needed_for_display(): void
    {
        // Rôle explicite : gérer le répertoire clients est réservé aux rôles qui
        // ouvrent des dossiers (ClientPolicy) — un compte sans rôle reçoit un 403.
        $user = User::factory()->create();
        $user->syncRoles([RoleUtilisateur::Clerc]);

        $response = $this->actingAs($user)->postJson('/clients', [
            'type'        => 'physique',
            'civilite'    => 'M.',
            'prenom_nom'  => 'Ibrahima DIALLO',
            'nationalite' => 'Guinéenne',
        ]);

        $response->assertStatus(201)->assertJson([
            'type'       => 'physique',
            'civilite'   => 'M.',
            'prenom_nom' => 'Ibrahima DIALLO',
        ]);
    }

    public function test_creating_a_client_morale_returns_fields_needed_for_display(): void
    {
        // Rôle explicite : gérer le répertoire clients est réservé aux rôles qui
        // ouvrent des dossiers (ClientPolicy) — un compte sans rôle reçoit un 403.
        $user = User::factory()->create();
        $user->syncRoles([RoleUtilisateur::Clerc]);

        // `representant_legal` est requis depuis le 2026-08-12 : on ne fait pas signer une personne
        // morale sans représentant, et l'acte le nomme.
        $response = $this->actingAs($user)->postJson('/clients', [
            'type'               => 'morale',
            'denomination'       => 'Société XYZ SARL',
            'representant_legal' => 'Ibrahima DIALLO',
        ]);

        $response->assertStatus(201)->assertJson([
            'type'         => 'morale',
            'denomination' => 'Société XYZ SARL',
        ]);
    }

    public function test_autocomplete_returns_type_field_needed_by_the_frontend_display_helper(): void
    {
        // Rôle explicite : gérer le répertoire clients est réservé aux rôles qui
        // ouvrent des dossiers (ClientPolicy) — un compte sans rôle reçoit un 403.
        $user = User::factory()->create();
        $user->syncRoles([RoleUtilisateur::Clerc]);

        Client::create([
            'type'       => 'physique',
            'civilite'   => 'Mme',
            'prenom_nom' => 'Aicha Balde',
        ]);

        $response = $this->actingAs($user)->getJson('/clients/autocomplete?q=Aicha');

        $response->assertOk();
        $this->assertNotEmpty($response->json());
        $this->assertSame('physique', $response->json()[0]['type']);
    }
}
