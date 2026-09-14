<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Lieu;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Le référentiel des lieux, servi en une seule réponse (2026-09-10).
 *
 * ⚠️ **Le défaut corrigé n'était pas un volume, mais une multiplication.** `LieuSelect` interrogeait
 * un point d'entrée **par niveau et par champ** : sur le questionnaire de modification, qui porte
 * **18 champs géographiques**, cela faisait jusqu'à 18 requêtes à l'ouverture, puis une de plus à
 * chaque choix de ville ou de commune. L'étude le ressentait comme une lenteur de l'application.
 *
 * Le référentiel entier pèse **16,5 Ko** (3,1 Ko compressé) pour 447 lieux : le transférer une fois
 * coûte moins que dix-huit allers-retours — et le gain est **plus** net en production, où c'est la
 * latence par requête qui domine.
 */
class ReferentielLieuxTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(?RoleUtilisateur $role = RoleUtilisateur::Clerc): User
    {
        $user = User::factory()->create(['actif' => true]);

        if ($role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role->value]);
        }

        return $user->fresh();
    }

    private function conakry(): Lieu
    {
        $ville   = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $commune = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma']);
        Lieu::create(['parent_id' => $commune->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);

        return $ville;
    }

    // ── Le contenu ──────────────────────────────────────────────────────────────────────

    public function test_le_referentiel_renvoie_les_trois_niveaux_en_une_reponse(): void
    {
        $this->conakry();

        $reponse = $this->actingAs($this->utilisateur())->getJson('/lieux/referentiel')->assertOk();

        $reponse->assertJsonStructure(['ville', 'commune', 'quartier']);
        $this->assertSame('Conakry', $reponse->json('ville.0.nom'));
        $this->assertSame('Ratoma', $reponse->json('commune.0.nom'));
        $this->assertSame('Nongo', $reponse->json('quartier.0.nom'));
    }

    public function test_le_parent_est_designe_par_son_nom_et_non_par_un_identifiant(): void
    {
        // C'est ce que les fiches et les questionnaires détiennent (`soc.siege_ville = "Conakry"`),
        // et ce que les actes reprennent. Un identifiant y serait inutilisable.
        $this->conakry();

        $reponse = $this->actingAs($this->utilisateur())->getJson('/lieux/referentiel')->assertOk();

        $this->assertNull($reponse->json('ville.0.parent'));
        $this->assertSame('Conakry', $reponse->json('commune.0.parent'));
        $this->assertSame('Ratoma', $reponse->json('quartier.0.parent'));
    }

    public function test_un_lieu_desactive_est_exclu(): void
    {
        $ville = $this->conakry();
        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Ancienne', 'actif' => false]);

        $noms = collect($this->actingAs($this->utilisateur())->getJson('/lieux/referentiel')->json('ville'))
            ->pluck('nom');

        $this->assertTrue($noms->contains($ville->nom));
        $this->assertFalse($noms->contains('Ancienne'), 'Désactiver retire des listes proposées.');
    }

    public function test_le_referentiel_exige_une_authentification(): void
    {
        $this->getJson('/lieux/referentiel')->assertUnauthorized();
    }

    // ── Le cache ────────────────────────────────────────────────────────────────────────

    public function test_un_lieu_ajoute_perime_le_cache(): void
    {
        // Sans cette invalidation, un lieu créé en pleine saisie restait invisible jusqu'au
        // prochain vidage du cache — donc, en pratique, indisponible.
        $this->conakry();
        Lieu::referentielComplet();
        $this->assertTrue(Cache::has(Lieu::CLE_CACHE_REFERENTIEL));

        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Boké']);

        $this->assertFalse(Cache::has(Lieu::CLE_CACHE_REFERENTIEL));
        $this->assertContains('Boké', collect(Lieu::referentielComplet()['ville'])->pluck('nom')->all());
    }

    public function test_un_lieu_supprime_perime_le_cache(): void
    {
        // `saved` ne couvre pas la suppression : il faut les deux hooks.
        $ville = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Éphémère']);
        Lieu::referentielComplet();

        $ville->delete();

        $this->assertFalse(Cache::has(Lieu::CLE_CACHE_REFERENTIEL));
        $this->assertNotContains('Éphémère', collect(Lieu::referentielComplet()['ville'])->pluck('nom')->all());
    }

    public function test_une_desactivation_perime_aussi_le_cache(): void
    {
        // La désactivation passe par une mise à jour, pas par une suppression — et elle change
        // bien le contenu servi.
        $ville = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Fria']);
        Lieu::referentielComplet();

        $ville->update(['actif' => false]);

        $this->assertNotContains('Fria', collect(Lieu::referentielComplet()['ville'])->pluck('nom')->all());
    }

    // ── L'ETag ──────────────────────────────────────────────────────────────────────────

    public function test_un_navigateur_deja_a_jour_recoit_un_304(): void
    {
        $this->conakry();
        $utilisateur = $this->utilisateur();

        $premiere = $this->actingAs($utilisateur)->getJson('/lieux/referentiel')->assertOk();
        $etag = $premiere->headers->get('ETag');

        $this->assertNotNull($etag, 'La réponse doit porter un ETag.');

        $this->actingAs($utilisateur)
            ->getJson('/lieux/referentiel', ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_l_etag_change_quand_le_referentiel_change(): void
    {
        $this->conakry();
        $utilisateur = $this->utilisateur();

        $avant = $this->actingAs($utilisateur)->getJson('/lieux/referentiel')->headers->get('ETag');

        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Kindia']);

        $apres = $this->actingAs($utilisateur)->getJson('/lieux/referentiel')->headers->get('ETag');

        $this->assertNotSame($avant, $apres, 'Un référentiel modifié doit invalider le cache navigateur.');
    }

    // ── Garde-fou de structure ──────────────────────────────────────────────────────────

    public function test_la_cascade_ninterroge_plus_le_serveur_par_niveau(): void
    {
        // Le retour à un appel par niveau redonnerait 18 requêtes par formulaire sans que rien ne
        // le signale : le défaut était invisible, donc il faut le rendre bruyant (voir la règle 2
        // de CLAUDE.md).
        $source = file_get_contents(resource_path('js/Components/ui/lieu-select.jsx'));

        $this->assertStringContainsString(
            'referentielLieux',
            $source,
            'LieuSelect doit lire le référentiel partagé.',
        );

        $this->assertSame(
            0,
            preg_match("#axios\s*\n?\s*\.?get\(#", $source),
            'LieuSelect ne doit plus faire de requête par niveau — le référentiel est chargé une fois.',
        );
    }

    public function test_le_formulaire_public_recoit_le_referentiel_dans_ses_props(): void
    {
        // L'intake n'est pas authentifié : aucun point d'entrée ne lui est exposé, il reçoit le
        // référentiel en props. Les deux appelants passent désormais par le **même** constructeur.
        $source = file_get_contents(app_path('Http/Controllers/IntakeController.php'));

        $this->assertStringContainsString('Lieu::referentielComplet()', $source);
    }
}
