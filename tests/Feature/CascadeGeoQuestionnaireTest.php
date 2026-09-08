<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Lieu;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La cascade géographique appliquée **partout** où le triplet apparaît (2026-08-12).
 *
 * Le triplet ville/commune/quartier est déclaré comme trois champs distincts dans **16 blocs** de
 * questionnaire, plus la fiche client et la fiche société. Les identifiants ne sont pas réguliers
 * (`soc.siege_ville` ici, `pp.demeurant_ville` là, `demeurant_ville` sans préfixe dans les blocs
 * répétables), d'où la table `TRIPLETS_GEO` côté frontend.
 *
 * Ces tests portent sur ce que le **serveur** garantit à cette cascade : le référentiel, ses
 * niveaux, et le fait que le formulaire public n'y accède qu'en lecture.
 */
class CascadeGeoQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    private function referentiel(): void
    {
        $conakry = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $ratoma  = Lieu::create(['parent_id' => $conakry->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma']);
        $kaloum  = Lieu::create(['parent_id' => $conakry->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Kaloum']);
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);
        Lieu::create(['parent_id' => $kaloum->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Almamya']);
    }

    public function test_les_quartiers_dune_commune_ne_debordent_pas_sur_lautre(): void
    {
        // C'est la garantie de fond : « Almamya » est à Kaloum, il ne doit pas être proposé sous
        // Ratoma. En saisie libre, la base avait accumulé l'inverse.
        $this->referentiel();
        $clerc = $this->clerc();

        $ratoma = $this->actingAs($clerc)->getJson('/lieux?niveau=quartier&parent=Ratoma')->assertOk();
        $kaloum = $this->actingAs($clerc)->getJson('/lieux?niveau=quartier&parent=Kaloum')->assertOk();

        $this->assertSame(['Nongo'],   array_column($ratoma->json('lieux'), 'nom'));
        $this->assertSame(['Almamya'], array_column($kaloum->json('lieux'), 'nom'));
    }

    public function test_un_lieu_ajoute_sert_immediatement_aux_dossiers_suivants(): void
    {
        // L'ajout en un clic n'a d'intérêt que si le lieu entre vraiment au référentiel, au lieu de
        // rester une chaîne isolée dans un seul dossier.
        $this->referentiel();
        $clerc = $this->clerc();

        $this->actingAs($clerc)
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])
            ->assertCreated();

        $this->assertContains(
            'Kipé',
            array_column(
                $this->actingAs($clerc)->getJson('/lieux?niveau=quartier&parent=Ratoma')->json('lieux'),
                'nom',
            ),
        );
    }

    public function test_le_formulaire_public_recoit_le_referentiel_sans_pouvoir_lecrire(): void
    {
        // Le référentiel voyage dans les props de l'intake : aucun point d'entrée ne lui est exposé,
        // et un tiers ne doit pas pouvoir peupler le référentiel de l'étude.
        $this->referentiel();

        $this->getJson('/lieux?niveau=ville')->assertUnauthorized();
        $this->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Intrus', 'parent' => 'Ratoma'])
            ->assertUnauthorized();

        $this->assertDatabaseMissing('lieux', ['nom' => 'Intrus']);
    }

    public function test_un_niveau_inconnu_est_refuse(): void
    {
        // La cascade n'a que trois étages : accepter un niveau arbitraire laisserait créer des
        // lieux que personne ne proposerait jamais.
        $this->actingAs($this->clerc())
            ->getJson('/lieux?niveau=departement')
            ->assertStatus(422);
    }

    public function test_le_seeder_amorce_conakry_et_les_prefectures(): void
    {
        $this->seed(\Database\Seeders\LieuSeeder::class);

        $conakry = Lieu::parNom('Conakry', Lieu::NIVEAU_VILLE);

        $this->assertNotNull($conakry);
        $this->assertEqualsCanonicalizing(
            ['Kaloum', 'Dixinn', 'Matam', 'Ratoma', 'Matoto'],
            $conakry->enfants->pluck('nom')->all(),
            'Les 5 communes de Conakry sont la partie du référentiel qui est affirmée.',
        );

        // Une préfecture est d'abord une **ville** — c'est ce qui manquait à la base, où
        // « Forecariah » n'existait que comme commune, donc sous aucune ville.
        $prefecture = Lieu::parNom('Forécariah', Lieu::NIVEAU_VILLE);
        $this->assertNotNull($prefecture);
        $this->assertNull($prefecture->parent_id, 'Une ville est au sommet de la hiérarchie.');

        // Elle porte en outre sa commune urbaine homonyme : c'est le découpage réel (une préfecture
        // compte une commune urbaine, son chef-lieu, et des communes rurales).
        $communeUrbaine = Lieu::parNom('Forécariah', Lieu::NIVEAU_COMMUNE);
        $this->assertNotNull($communeUrbaine);
        $this->assertSame($prefecture->id, $communeUrbaine->parent_id);
    }

    public function test_tous_les_quartiers_amorces_sont_marques_a_verifier(): void
    {
        // Leur liste n'est ni garantie exhaustive ni garantie à jour : les présenter comme sûrs
        // serait pire que du texte libre.
        $this->seed(\Database\Seeders\LieuSeeder::class);

        $this->assertSame(
            0,
            Lieu::niveau(Lieu::NIVEAU_QUARTIER)->where('a_verifier', false)->count(),
        );
        $this->assertSame(
            0,
            Lieu::where('a_verifier', true)->where('niveau', '!=', Lieu::NIVEAU_QUARTIER)->count(),
            'Seuls les quartiers portent le drapeau : villes et communes sont affirmées.',
        );
    }

    public function test_un_administrateur_valide_un_lieu_et_le_retire_du_filtre(): void
    {
        $this->seed(\Database\Seeders\LieuSeeder::class);

        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        $quartier = Lieu::niveau(Lieu::NIVEAU_QUARTIER)->where('a_verifier', true)->first();

        $this->actingAs($admin->fresh())
            ->patch("/parametres/lieux/{$quartier->id}", ['nom' => $quartier->nom, 'a_verifier' => false])
            ->assertRedirect();

        $this->assertFalse($quartier->fresh()->a_verifier);
    }

    public function test_un_clerc_ne_peut_pas_corriger_le_referentiel(): void
    {
        // Asymétrie voulue : ajouter un lieu manquant est un geste de saisie, renommer « Ratoma »
        // changerait la liste proposée à toute l'étude.
        $this->referentiel();
        $ratoma = Lieu::parNom('Ratoma', Lieu::NIVEAU_COMMUNE);

        $this->actingAs($this->clerc())
            ->patch("/parametres/lieux/{$ratoma->id}", ['nom' => 'Ratoma modifié'])
            ->assertForbidden();

        $this->assertSame('Ratoma', $ratoma->fresh()->nom);
    }

    public function test_un_lieu_desactive_sort_des_listes_sans_disparaitre(): void
    {
        // Pas de suppression : le nom peut figurer dans des actes déjà produits.
        $this->referentiel();
        $ratoma = Lieu::parNom('Ratoma', Lieu::NIVEAU_COMMUNE);
        $ratoma->update(['actif' => false]);

        $this->actingAs($this->clerc())
            ->getJson('/lieux?niveau=commune&parent=Conakry')
            ->assertJsonCount(1, 'lieux');

        $this->assertDatabaseHas('lieux', ['id' => $ratoma->id]);
    }

    // ── Ajout depuis l'écran du référentiel ─────────────────────────────────

    public function test_un_ajout_par_ladministrateur_nest_pas_marque_a_verifier(): void
    {
        // « À vérifier » est la liste de travail de l'administrateur : marquer son propre ajout le
        // lui renverrait à lui-même. Ajouter depuis l'écran du référentiel **est** la vérification.
        $this->referentiel();

        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        $this->actingAs($admin->fresh())
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])
            ->assertCreated()
            ->assertJson(['a_verifier' => false]);
    }

    public function test_un_ajout_par_un_clerc_reste_a_verifier(): void
    {
        // Le pendant : un lieu créé en pleine saisie de dossier sert immédiatement, mais entre dans
        // la liste de travail — personne ne l'a validé.
        $this->referentiel();

        $this->actingAs($this->clerc())
            ->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])
            ->assertCreated()
            ->assertJson(['a_verifier' => true]);
    }

    public function test_une_ville_sajoute_sans_parent(): void
    {
        // Niveau racine : aucun parent à fournir, et le lieu doit bien se placer au sommet.
        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        $this->actingAs($admin->fresh())
            ->postJson('/lieux', ['niveau' => 'ville', 'nom' => 'Dubréka'])
            ->assertCreated();

        $ville = Lieu::parNom('Dubréka', Lieu::NIVEAU_VILLE);
        $this->assertNotNull($ville);
        $this->assertNull($ville->parent_id);
    }

    public function test_ajouter_deux_fois_le_meme_lieu_ne_cree_pas_de_doublon(): void
    {
        // `firstOrCreate` : deux clercs saisissant le même quartier au même moment ne doivent pas
        // le dédoubler au référentiel.
        $this->referentiel();
        $clerc = $this->clerc();

        $this->actingAs($clerc)->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'Kipé', 'parent' => 'Ratoma'])->assertCreated();
        $this->actingAs($clerc)->postJson('/lieux', ['niveau' => 'quartier', 'nom' => 'KIPE', 'parent' => 'Ratoma'])->assertCreated();

        $this->assertSame(1, Lieu::niveau(Lieu::NIVEAU_QUARTIER)->where('nom_normalise', 'kipe')->count());
    }

    // ── Suppression ─────────────────────────────────────────────────────────

    private function admin(): User
    {
        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        return $admin->fresh();
    }

    public function test_un_lieu_non_reference_est_supprimable(): void
    {
        // Le cas qui manquait : un lieu ajouté par erreur, mal orthographié ou placé au mauvais
        // niveau doit pouvoir partir. La désactivation seule laissait le référentiel se remplir de
        // scories sans recours.
        $this->referentiel();
        $orphelin = Lieu::parNom('Almamya', Lieu::NIVEAU_QUARTIER);

        $this->assertTrue($orphelin->estSupprimable());

        $this->actingAs($this->admin())
            ->delete("/parametres/lieux/{$orphelin->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('lieux', ['id' => $orphelin->id]);
    }

    public function test_un_lieu_employe_dans_une_fiche_nest_pas_supprimable(): void
    {
        // Son nom figure dans une fiche, et donc possiblement dans un acte produit : c'est là que
        // la désactivation est la bonne réponse.
        $this->referentiel();
        Client::create(['type' => 'physique', 'nom_famille' => 'TEST', 'quartier' => 'Nongo']);

        $nongo = Lieu::parNom('Nongo', Lieu::NIVEAU_QUARTIER);
        $this->assertFalse($nongo->estSupprimable());

        $this->actingAs($this->admin())->delete("/parametres/lieux/{$nongo->id}");

        $this->assertDatabaseHas('lieux', ['id' => $nongo->id]);
    }

    public function test_un_lieu_avec_enfants_nest_pas_supprimable(): void
    {
        // Supprimer une commune emporterait ses quartiers en cascade, sans que rien ne l'annonce.
        $this->referentiel();
        $ratoma = Lieu::parNom('Ratoma', Lieu::NIVEAU_COMMUNE);

        $this->assertFalse($ratoma->estSupprimable());

        $this->actingAs($this->admin())->delete("/parametres/lieux/{$ratoma->id}");

        $this->assertDatabaseHas('lieux', ['id' => $ratoma->id]);
        $this->assertDatabaseHas('lieux', ['nom' => 'Nongo']);
    }

    public function test_un_lieu_employe_dans_un_questionnaire_nest_pas_supprimable(): void
    {
        // Les questionnaires portent les lieux sous des clés préfixées variables — le balayage doit
        // les voir, blocs répétables compris.
        $this->referentiel();

        $type = \App\Models\TypeActe::firstOrCreate(
            ['code' => 'SOC-SARLU'],
            ['label' => 'SARLU', 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
        $dossier = \App\Models\Dossier::create([
            'reference' => 'SOC-2026-9999', 'type_acte_id' => $type->id,
            'redacteur_id' => User::factory()->create()->id, 'objet' => 'Test du balayage',
        ]);
        \App\Models\Questionnaire::create([
            'dossier_id' => $dossier->id,
            'donnees'    => ['associes' => [['quartier' => 'Almamya']]],
        ]);

        $this->assertFalse(Lieu::parNom('Almamya', Lieu::NIVEAU_QUARTIER)->estSupprimable());
    }

    public function test_un_clerc_ne_peut_pas_supprimer(): void
    {
        $this->referentiel();
        $almamya = Lieu::parNom('Almamya', Lieu::NIVEAU_QUARTIER);

        $this->actingAs($this->clerc())
            ->delete("/parametres/lieux/{$almamya->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('lieux', ['id' => $almamya->id]);
    }
}
