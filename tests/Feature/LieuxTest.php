<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Lieu;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Normalisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Référentiel des lieux — cascade ville → commune → quartier (2026-08-12).
 *
 * Le triplet était en saisie libre à dix endroits, et la base avait accumulé des incohérences :
 * « Forecariah » enregistré comme commune alors que c'est une préfecture, « Kountia » comme quartier
 * de Conakry alors qu'il est à Dubréka.
 */
class LieuxTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(?RoleUtilisateur $role = null): User
    {
        $user = User::factory()->create(['actif' => true]);

        if ($role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role->value]);
        }

        return $user->fresh();
    }

    private function conakry(): Lieu
    {
        $ville  = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $ratoma = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma']);
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);

        return $ville;
    }

    // ── Structure ────────────────────────────────────────────────────────────

    public function test_un_meme_nom_est_permis_sous_deux_parents_distincts(): void
    {
        // « Matam » est une commune de Conakry ET une préfecture : l'unicité ne peut donc pas être
        // globale, elle est relative au parent et au niveau.
        $conakry = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $kindia  = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Kindia']);

        Lieu::create(['parent_id' => $conakry->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Matam']);
        Lieu::create(['parent_id' => $kindia->id,  'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Matam']);

        $this->assertSame(2, Lieu::niveau(Lieu::NIVEAU_COMMUNE)->where('nom', 'Matam')->count());
    }

    public function test_le_meme_nom_sous_le_meme_parent_est_refuse(): void
    {
        $conakry = $this->conakry();

        $this->expectException(\Illuminate\Database\QueryException::class);
        Lieu::create(['parent_id' => $conakry->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma']);
    }

    public function test_les_variantes_accentuees_sont_le_meme_lieu(): void
    {
        // Sans quoi « Forécariah » et « Forecariah » coexisteraient au référentiel.
        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Forécariah']);

        $this->assertNotNull(Lieu::parNom('Forecariah', Lieu::NIVEAU_VILLE));
        $this->assertSame(
            Normalisation::comparable('Forécariah'),
            Normalisation::comparable('FORECARIAH'),
        );
    }

    public function test_un_niveau_nadmet_que_ses_propres_enfants(): void
    {
        $conakry = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);

        $this->assertTrue($conakry->accepteEnfant(Lieu::NIVEAU_COMMUNE));
        // Sans ce garde-fou, un quartier pourrait être rattaché directement à une ville, et la
        // cascade proposerait des quartiers là où l'on attend des communes.
        $this->assertFalse($conakry->accepteEnfant(Lieu::NIVEAU_QUARTIER));
    }

    // ── La cascade ───────────────────────────────────────────────────────────

    public function test_la_cascade_ne_rend_que_les_enfants_du_parent_demande(): void
    {
        $this->conakry();
        $kindia = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Kindia']);
        Lieu::create(['parent_id' => $kindia->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Kindia']);

        $reponse = $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->getJson('/lieux?niveau=commune&parent=Conakry')
            ->assertOk();

        $this->assertSame(['Ratoma'], array_column($reponse->json('lieux'), 'nom'));
    }

    public function test_sans_parent_un_niveau_qui_en_attend_un_ne_rend_rien(): void
    {
        // Proposer tous les quartiers du pays avant de connaître la commune recréerait exactement
        // l'incohérence que la cascade supprime.
        $this->conakry();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->getJson('/lieux?niveau=quartier')
            ->assertOk()
            ->assertJsonCount(0, 'lieux');
    }

    public function test_un_lieu_desactive_nest_plus_propose(): void
    {
        $conakry = $this->conakry();
        Lieu::parNom('Ratoma', Lieu::NIVEAU_COMMUNE, $conakry->id)->update(['actif' => false]);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->getJson('/lieux?niveau=commune&parent=Conakry')
            ->assertOk()
            ->assertJsonCount(0, 'lieux');
    }

    // ── Ajout et autorisations ───────────────────────────────────────────────

    public function test_un_clerc_ajoute_un_quartier_manquant_marque_a_verifier(): void
    {
        $this->conakry();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])
            ->assertCreated()
            ->assertJson(['nom' => 'Kipé', 'a_verifier' => true]);
    }

    public function test_un_quartier_ne_peut_pas_etre_rattache_a_une_ville(): void
    {
        $this->conakry();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Conakry'])
            ->assertStatus(422);
    }

    public function test_un_role_sans_habilitation_ne_peut_pas_ajouter(): void
    {
        $this->conakry();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Formaliste))
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])
            ->assertForbidden();
    }

    public function test_le_referentiel_nest_jamais_atteignable_sans_authentification(): void
    {
        // Le formulaire public d'intake reçoit le référentiel dans ses props : aucune écriture ne
        // doit être exposée à un tiers.
        $this->getJson('/lieux?niveau=ville')->assertUnauthorized();
        $this->postJson('/lieux', ['niveau' => 'ville', 'nom' => 'Intrus'])->assertUnauthorized();

        $this->assertDatabaseMissing('lieux', ['nom' => 'Intrus']);
    }

    // ── Rapprochement des données existantes ────────────────────────────────

    public function test_le_rapprochement_signale_sans_rien_ecrire(): void
    {
        $this->conakry();
        // « Nongo » est un quartier : l'enregistrer en commune est l'écart type.
        $client = Client::create(['type' => 'physique', 'nom_famille' => 'TEST', 'commune' => 'Nongo']);

        $this->artisan('ayelema:lieux-rapprocher')->assertSuccessful();

        $this->assertSame('Nongo', $client->fresh()->commune, 'Le dry-run ne doit rien réécrire.');
    }

    public function test_appliquer_vide_le_champ_mal_place(): void
    {
        // On vide plutôt que de deviner où reposer la valeur : c'est au clerc de la ressaisir depuis
        // la cascade, en la voyant manquer.
        $this->conakry();
        $client = Client::create(['type' => 'physique', 'nom_famille' => 'TEST', 'commune' => 'Nongo']);

        $this->artisan('ayelema:lieux-rapprocher --appliquer')->assertSuccessful();

        $this->assertNull($client->fresh()->commune);
    }

    public function test_une_valeur_conforme_nest_pas_touchee(): void
    {
        $this->conakry();
        $client = Client::create([
            'type' => 'physique', 'nom_famille' => 'TEST',
            'demeurant_ville' => 'Conakry', 'commune' => 'Ratoma', 'quartier' => 'Nongo',
        ]);

        $this->artisan('ayelema:lieux-rapprocher --appliquer')->assertSuccessful();

        $frais = $client->fresh();
        $this->assertSame('Conakry', $frais->demeurant_ville);
        $this->assertSame('Ratoma', $frais->commune);
        $this->assertSame('Nongo', $frais->quartier);
    }
}
