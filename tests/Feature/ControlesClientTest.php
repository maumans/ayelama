<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Contrôles de cohérence de la fiche client (2026-08-12).
 *
 * `ClientController::regles()` ne vérifiait que des **types** : une pièce pouvait donc expirer avant
 * d'avoir été délivrée, une naissance être postérieure à la pièce d'identité, et un régime
 * matrimonial être renseigné pour un célibataire — trois états impossibles que les actes auraient
 * repris tels quels.
 *
 * Un test par règle, nommé d'après elle.
 */
class ControlesClientTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $champs */
    private function creer(array $champs)
    {
        return $this->actingAs($this->clerc())->postJson('/clients', array_merge([
            'type'        => 'physique',
            'nom_famille' => 'DIALLO',
            'prenoms'     => 'Thierno',
        ], $champs));
    }

    // ── Cohérence des dates ──────────────────────────────────────────────────

    public function test_une_expiration_anterieure_a_la_delivrance_est_refusee(): void
    {
        $this->creer([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN001',
            'piece_delivree_le' => '2020-01-01',
            'piece_expire_le'   => '2019-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('piece_expire_le');
    }

    public function test_une_delivrance_anterieure_a_la_naissance_est_refusee(): void
    {
        $this->creer([
            'date_naissance'    => '2000-01-01',
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN001',
            'piece_delivree_le' => '1995-01-01',
        ])->assertStatus(422)->assertJsonValidationErrors('piece_delivree_le');
    }

    public function test_une_naissance_dans_le_futur_est_refusee(): void
    {
        $this->creer(['date_naissance' => now()->addDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('date_naissance');
    }

    public function test_une_piece_delivree_dans_le_futur_est_refusee(): void
    {
        $this->creer([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN001',
            'piece_delivree_le' => now()->addDay()->toDateString(),
        ])->assertStatus(422)->assertJsonValidationErrors('piece_delivree_le');
    }

    public function test_une_naissance_avant_1900_est_refusee(): void
    {
        // Presque toujours une faute de frappe sur l'année, jamais un client réel.
        $this->creer(['date_naissance' => '1850-01-01'])
            ->assertStatus(422)->assertJsonValidationErrors('date_naissance');
    }

    public function test_une_piece_expiree_est_acceptee_avec_avertissement(): void
    {
        // Décision retenue : **avertissement seul**. L'étude doit pouvoir consigner la situation
        // réelle du client avant de lui demander un renouvellement — la cohérence des dates entre
        // elles, en revanche, décrit une saisie impossible et reste bloquante.
        $this->creer([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN001',
            'piece_delivree_le' => '2015-01-01',
            'piece_expire_le'   => '2020-01-01',
        ])->assertCreated();

        $client = Client::where('piece_numero', 'GN001')->first();

        $this->assertTrue($client->pieceExpiree());
        $this->assertStringContainsString('expirée', $client->avertissements()[0]);
    }

    public function test_une_piece_valide_ne_declenche_aucun_avertissement(): void
    {
        $this->creer([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN002',
            'piece_delivree_le' => '2020-01-01',
            'piece_expire_le'   => now()->addYears(5)->toDateString(),
        ])->assertCreated();

        $this->assertSame([], Client::where('piece_numero', 'GN002')->first()->avertissements());
    }

    // ── Paires indissociables ────────────────────────────────────────────────

    public function test_un_type_de_piece_sans_numero_est_refuse(): void
    {
        // Un type sans numéro ne prouve rien.
        $this->creer(['piece_type' => 'CNI'])
            ->assertStatus(422)->assertJsonValidationErrors('piece_numero');
    }

    public function test_un_numero_de_piece_sans_type_est_refuse(): void
    {
        // Et un numéro sans type ne se vérifie pas.
        $this->creer(['piece_numero' => 'GN001'])
            ->assertStatus(422)->assertJsonValidationErrors('piece_type');
    }

    // ── Cohérence conditionnelle ─────────────────────────────────────────────

    public function test_un_regime_matrimonial_sans_mariage_est_refuse(): void
    {
        $this->creer([
            'situation_matrimoniale' => 'Célibataire',
            'regime_matrimonial'     => 'Communauté de biens',
        ])->assertStatus(422)->assertJsonValidationErrors('regime_matrimonial');
    }

    public function test_un_regime_matrimonial_avec_mariage_est_accepte(): void
    {
        $this->creer([
            'situation_matrimoniale' => 'Marié(e)',
            'regime_matrimonial'     => 'Communauté de biens',
        ])->assertCreated();
    }

    public function test_un_regime_hors_liste_est_refuse(): void
    {
        // Le cœur du changement : « Communaté de bien » partait telle quelle dans les actes.
        $this->creer([
            'situation_matrimoniale' => 'Marié(e)',
            'regime_matrimonial'     => 'Communaté de bien',
        ])->assertStatus(422)->assertJsonValidationErrors('regime_matrimonial');
    }

    public function test_une_personne_mariee_sans_regime_est_refusee(): void
    {
        // L'acte nomme le régime : le laisser vide reporte le problème à la rédaction.
        $this->creer(['situation_matrimoniale' => 'Marié(e)'])
            ->assertStatus(422)->assertJsonValidationErrors('regime_matrimonial');
    }

    public function test_les_quatre_regimes_de_la_liste_sont_acceptes(): void
    {
        foreach (\App\Models\Client::REGIMES_MATRIMONIAUX as $regime) {
            $this->creer([
                'situation_matrimoniale' => 'Marié(e)',
                'regime_matrimonial'     => $regime,
            ])->assertCreated("« {$regime} » doit être accepté.");
        }
    }

    public function test_une_situation_matrimoniale_hors_liste_est_refusee(): void
    {
        $this->creer(['situation_matrimoniale' => 'Concubinage'])
            ->assertStatus(422)->assertJsonValidationErrors('situation_matrimoniale');
    }

    // ── Personne morale ──────────────────────────────────────────────────────

    public function test_une_personne_morale_sans_representant_legal_est_refusee(): void
    {
        // On ne fait pas signer une personne morale sans représentant, et l'acte le nomme.
        $this->actingAs($this->clerc())
            ->postJson('/clients', ['type' => 'morale', 'denomination' => 'FAYA SARL'])
            ->assertStatus(422)->assertJsonValidationErrors('representant_legal');
    }

    // ── Téléphone ────────────────────────────────────────────────────────────

    public function test_les_formes_courantes_du_numero_guineen_sont_acceptees(): void
    {
        foreach (['622783732', '622 78 37 32', '+224 622 78 37 32', '00224622783732', '622-78-37-32'] as $i => $numero) {
            $this->creer(['telephone' => $numero, 'piece_numero' => null])
                ->assertCreated("« {$numero} » doit être accepté.");
        }
    }

    public function test_un_numero_non_guineen_est_refuse(): void
    {
        foreach (['12345', '722783732', '622783'] as $numero) {
            $this->creer(['telephone' => $numero])
                ->assertStatus(422)
                ->assertJsonValidationErrors('telephone');
        }
    }

    // ── Non-régression ───────────────────────────────────────────────────────

    public function test_une_fiche_minimale_reste_enregistrable(): void
    {
        // Les contrôles ajoutés ne doivent pas exiger plus qu'avant sur une saisie sommaire : un
        // client se crée souvent avec son seul nom, complété ensuite.
        $this->creer([])->assertCreated();
    }

    public function test_une_fiche_complete_et_coherente_est_acceptee(): void
    {
        $this->creer([
            'date_naissance'         => '1985-03-15',
            'ne_a'                   => 'Conakry',
            'piece_type'             => 'CNI CEDEAO',
            'piece_numero'           => 'GN00123456',
            'piece_delivree_le'      => '2020-01-01',
            'piece_delivree_a'       => 'Conakry',
            'piece_expire_le'        => now()->addYears(3)->toDateString(),
            'situation_matrimoniale' => 'Marié(e)',
            'regime_matrimonial'     => 'Communauté de biens',
            'telephone'              => '622 78 37 32',
            'email'                  => 'thierno@exemple.com',
        ])->assertCreated();
    }
}
