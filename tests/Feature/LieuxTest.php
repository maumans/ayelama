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



    // ── Import du découpage administratif réel (2026-09-10) ──────────────────

    /** Écrit un fichier de données minimal, pour ne pas dépendre des 353 communes réelles. */
    private function fichierDonnees(array $villes): string
    {
        $chemin = sys_get_temp_dir() . '/lieux-test-' . uniqid() . '.json';

        file_put_contents($chemin, json_encode([
            'source'    => 'test',
            'genere_le' => '2026-09-10',
            'villes'    => $villes,
        ], JSON_UNESCAPED_UNICODE));

        return $chemin;
    }

    public function test_l_import_cree_les_villes_et_leurs_communes(): void
    {
        $fichier = $this->fichierDonnees([
            ['nom' => 'Boké', 'communes' => ['Boké', 'Kolaboui', 'Sangarédi']],
        ]);

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier])
            ->assertSuccessful();

        $ville = Lieu::where('niveau', Lieu::NIVEAU_VILLE)->where('nom', 'Boké')->first();

        $this->assertNotNull($ville);
        $this->assertSame(
            ['Boké', 'Kolaboui', 'Sangarédi'],
            Lieu::where('parent_id', $ville->id)->orderBy('nom')->pluck('nom')->all(),
        );
    }

    public function test_l_import_ne_fait_rien_sans_appliquer(): void
    {
        // Dry-run par défaut, comme `ayelema:brouillons-purger`. C'est ce mode qui a révélé que
        // trois préfectures allaient être créées en double (GeoNames les nomme « Préfecture de
        // Dubréka » au lieu de « Dubréka »).
        $fichier = $this->fichierDonnees([['nom' => 'Boké', 'communes' => ['Kolaboui']]]);

        $this->artisan('ayelema:lieux-importer', ['--fichier' => $fichier])->assertSuccessful();

        $this->assertSame(0, Lieu::count());
    }

    public function test_l_import_est_idempotent(): void
    {
        $fichier = $this->fichierDonnees([
            ['nom' => 'Boké', 'communes' => ['Boké', 'Kolaboui']],
        ]);

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);
        $apresPremier = Lieu::count();

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);

        $this->assertSame($apresPremier, Lieu::count(), 'Rejouer l\'import ne doit rien dupliquer.');
    }

    public function test_l_import_ne_cree_pas_de_doublon_sur_un_accent(): void
    {
        // Le dédoublonnage passe par `nom_normalise` : « Forecariah » et « Forécariah » désignent
        // le même endroit. Sans cela l'import aurait doublé une partie du référentiel amorcé.
        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Forecariah']);

        $fichier = $this->fichierDonnees([['nom' => 'Forécariah', 'communes' => []]]);

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);

        $this->assertSame(1, Lieu::where('niveau', Lieu::NIVEAU_VILLE)->count());
        $this->assertSame('Forecariah', Lieu::first()->nom, 'La graphie déjà en base prime.');
    }

    public function test_l_import_laisse_intact_un_lieu_saisi_par_l_etude(): void
    {
        // L'import **complète**, il ne réécrit jamais : c'est l'étude qui connaît le terrain.
        $admin = $this->utilisateur(RoleUtilisateur::Administrateur);

        $ville = Lieu::create([
            'niveau'        => Lieu::NIVEAU_VILLE,
            'nom'           => 'Boké',
            'a_verifier'    => true,
            'created_by_id' => $admin->id,
        ]);

        $fichier = $this->fichierDonnees([['nom' => 'Boké', 'communes' => []]]);

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);

        $frais = $ville->fresh();

        $this->assertTrue($frais->a_verifier, 'Un lieu de l\'étude garde son marqueur.');
        $this->assertSame($admin->id, $frais->created_by_id);
        $this->assertNull($frais->source, 'L\'import ne pose pas sa provenance sur un lieu existant.');
    }

    public function test_les_lieux_importes_arrivent_valides_et_traces(): void
    {
        // Décision de l'étude : une donnée venant d'une source documentée est utilisable
        // immédiatement. « À vérifier » retrouve son sens — ce que l'étude ajoute à la volée.
        $fichier = $this->fichierDonnees([['nom' => 'Boké', 'communes' => ['Kolaboui']]]);

        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);

        foreach (Lieu::all() as $lieu) {
            $this->assertFalse($lieu->a_verifier, "« {$lieu->nom} » doit arriver validé.");
            $this->assertSame('import:2026-09-10', $lieu->source);
            $this->assertNull($lieu->created_by_id, 'Un lieu importé n\'a pas d\'auteur humain.');
        }
    }

    public function test_l_import_perime_le_cache_du_referentiel(): void
    {
        Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        Lieu::referentielComplet();

        $fichier = $this->fichierDonnees([['nom' => 'Boké', 'communes' => []]]);
        $this->artisan('ayelema:lieux-importer', ['--appliquer' => true, '--fichier' => $fichier]);

        $this->assertContains(
            'Boké',
            collect(Lieu::referentielComplet()['ville'])->pluck('nom')->all(),
        );
    }

    public function test_l_import_refuse_un_fichier_introuvable(): void
    {
        $this->artisan('ayelema:lieux-importer', ['--fichier' => '/introuvable.json'])->assertFailed();
    }

    public function test_le_fichier_de_donnees_livre_est_coherent(): void
    {
        // Garde-fou sur la donnée elle-même : le fichier versionné doit rester lisible, porter sa
        // provenance, et refléter la réforme de 2024 sur Conakry (13 communes, dont Lambanyi —
        // celle de l'étude, qui manquait au référentiel amorcé).
        $donnees = json_decode(file_get_contents(database_path('data/lieux-guinee.json')), true);

        $this->assertIsArray($donnees, 'database/data/lieux-guinee.json doit être lisible.');
        $this->assertNotEmpty($donnees['source'] ?? null, 'La provenance doit être écrite dans le fichier.');
        $this->assertGreaterThanOrEqual(39, count($donnees['villes']));

        $conakry = collect($donnees['villes'])->firstWhere('nom', 'Conakry');

        $this->assertNotNull($conakry, 'Conakry doit figurer au fichier.');
        $this->assertCount(13, $conakry['communes'], 'Conakry compte 13 communes depuis la loi L2024/003.');
        $this->assertContains('Lambanyi', $conakry['communes']);

        // ── Les quartiers : Conakry seulement ────────────────────────────────
        //
        // Aucune source n'expose les 4 142 districts du pays ; le fichier n'en déclare donc que
        // pour Conakry, et le référentiel se peuple par l'usage ailleurs.
        foreach ($donnees['villes'] as $ville) {
            if ($ville['nom'] === 'Conakry') {
                $this->assertNotEmpty($ville['quartiers'] ?? [], 'Conakry doit déclarer ses quartiers.');
                continue;
            }

            $this->assertArrayNotHasKey(
                'quartiers',
                $ville,
                "« {$ville['nom']} » ne doit pas déclarer de quartiers : aucune source ne les couvre.",
            );
        }

        // ⚠️ **Le garde-fou qui aurait attrapé le défaut du jour** : « Lambanyi » et « Sonfonia »
        // figuraient à la fois comme communes de Conakry et comme quartiers de Ratoma. Un nom ne
        // peut pas désigner deux niveaux du même endroit.
        $communes = array_map(fn (string $c) => mb_strtolower($c), $conakry['communes']);

        foreach ($conakry['quartiers'] as $commune => $quartiers) {
            $this->assertContains(
                $commune,
                $conakry['communes'],
                "Les quartiers sont déclarés sous « {$commune} », qui n'est pas une commune de Conakry.",
            );

            foreach ($quartiers as $quartier) {
                $this->assertNotContains(
                    mb_strtolower($quartier),
                    $communes,
                    "« {$quartier} » est une commune de Conakry : il ne peut pas être aussi un quartier.",
                );
            }
        }
    }


    // ── Correction des quartiers restés sur l'ancien découpage (2026-09-10) ──

    /**
     * Un référentiel de Conakry reproduisant le désordre signalé : la réforme de 2024 a découpé
     * Ratoma en Ratoma + Lambanyi + Sonfonia, mais les quartiers pendaient toujours de Ratoma.
     */
    private function conakryApresReforme(): array
    {
        $ville   = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $ratoma  = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma']);
        Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Lambanyi']);

        return [$ville, $ratoma];
    }

    private function fichierConakry(array $quartiersDeRatoma): string
    {
        return $this->fichierDonnees([[
            'nom'       => 'Conakry',
            'communes'  => ['Ratoma', 'Lambanyi'],
            'quartiers' => ['Ratoma' => $quartiersDeRatoma],
        ]]);
    }

    public function test_un_quartier_qui_est_devenu_une_commune_est_retire(): void
    {
        // Le cœur du défaut : « Lambanyi » est une commune depuis 2024, il ne peut plus être un
        // quartier de Ratoma — un nom ne désigne pas deux niveaux du même endroit.
        [, $ratoma] = $this->conakryApresReforme();
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Lambanyi']);

        $this->artisan('ayelema:lieux-importer', [
            '--appliquer' => true,
            '--corriger'  => true,
            '--fichier'   => $this->fichierConakry(['Nongo']),
        ])->assertSuccessful();

        $this->assertDatabaseMissing('lieux', ['nom' => 'Lambanyi', 'niveau' => Lieu::NIVEAU_QUARTIER]);
        $this->assertDatabaseHas('lieux', ['nom' => 'Lambanyi', 'niveau' => Lieu::NIVEAU_COMMUNE]);
        $this->assertDatabaseHas('lieux', ['nom' => 'Nongo', 'actif' => true]);
    }

    public function test_un_quartier_employe_est_desactive_et_non_supprime(): void
    {
        // Son nom figure peut-être déjà dans un acte produit, et le référentiel ne porte aucune clé
        // étrangère vers eux : c'est la règle de `estSupprimable()`, on ne la contourne pas.
        [, $ratoma] = $this->conakryApresReforme();
        $intrus = Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Sonfonia']);

        Client::create([
            'type' => 'physique', 'nom_famille' => 'DIALLO',
            'demeurant_ville' => 'Conakry', 'commune' => 'Ratoma', 'quartier' => 'Sonfonia',
        ]);

        $this->artisan('ayelema:lieux-importer', [
            '--appliquer' => true,
            '--corriger'  => true,
            '--fichier'   => $this->fichierConakry(['Nongo']),
        ])->assertSuccessful();

        $frais = $intrus->fresh();

        $this->assertNotNull($frais, 'Un quartier employé ne doit pas être supprimé.');
        $this->assertFalse($frais->actif, 'Il doit sortir des listes proposées.');
    }

    public function test_la_correction_ne_touche_pas_un_quartier_saisi_par_l_etude(): void
    {
        // C'est l'étude qui connaît le terrain, pas un fichier de données. Le cas s'est présenté
        // réellement : « Dapompa » avait été saisi à la main, et la correction l'a préservé.
        $admin = $this->utilisateur(RoleUtilisateur::Administrateur);
        [, $ratoma] = $this->conakryApresReforme();

        $sien = Lieu::create([
            'parent_id'     => $ratoma->id,
            'niveau'        => Lieu::NIVEAU_QUARTIER,
            'nom'           => 'Quartier du clerc',
            'created_by_id' => $admin->id,
        ]);

        $this->artisan('ayelema:lieux-importer', [
            '--appliquer' => true,
            '--corriger'  => true,
            '--fichier'   => $this->fichierConakry(['Nongo']),
        ])->assertSuccessful();

        $this->assertNotNull($sien->fresh());
        $this->assertTrue($sien->fresh()->actif);
    }

    public function test_la_correction_ne_deplace_aucun_quartier(): void
    {
        // Répartir les quartiers entre les communes issues du découpage exige l'annexe de la loi
        // L2024/003 : rattacher au hasard mettrait une fausse adresse dans un acte authentique.
        [, $ratoma] = $this->conakryApresReforme();
        $nongo = Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);

        $this->artisan('ayelema:lieux-importer', [
            '--appliquer' => true,
            '--corriger'  => true,
            '--fichier'   => $this->fichierConakry(['Nongo']),
        ]);

        $this->assertSame($ratoma->id, $nongo->fresh()->parent_id);
    }

    public function test_la_correction_ne_fait_rien_sans_appliquer(): void
    {
        [, $ratoma] = $this->conakryApresReforme();
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Lambanyi']);

        $this->artisan('ayelema:lieux-importer', [
            '--corriger' => true,
            '--fichier'  => $this->fichierConakry(['Nongo']),
        ])->assertSuccessful();

        $this->assertDatabaseHas('lieux', ['nom' => 'Lambanyi', 'niveau' => Lieu::NIVEAU_QUARTIER]);
    }

    public function test_la_correction_est_idempotente(): void
    {
        [, $ratoma] = $this->conakryApresReforme();
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo']);
        Lieu::create(['parent_id' => $ratoma->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Lambanyi']);

        $fichier = $this->fichierConakry(['Nongo']);
        $options = ['--appliquer' => true, '--corriger' => true, '--fichier' => $fichier];

        $this->artisan('ayelema:lieux-importer', $options);
        $apres = Lieu::count();

        $this->artisan('ayelema:lieux-importer', $options);

        $this->assertSame($apres, Lieu::count());
    }

    public function test_l_import_cree_les_quartiers_declares(): void
    {
        $this->artisan('ayelema:lieux-importer', [
            '--appliquer' => true,
            '--fichier'   => $this->fichierConakry(['Nongo', 'Kipé', 'Taouyah']),
        ])->assertSuccessful();

        $ratoma = Lieu::where('nom', 'Ratoma')->where('niveau', Lieu::NIVEAU_COMMUNE)->first();

        $this->assertSame(
            ['Kipé', 'Nongo', 'Taouyah'],
            Lieu::where('parent_id', $ratoma->id)->orderBy('nom')->pluck('nom')->all(),
        );
    }

    // ── Correction des quartiers : fin ──────────────────────────────────────

    // ── Valider un lieu : un geste à part entière (2026-09-09) ───────────────



    public function test_un_lieu_se_valide_sans_etre_renomme(): void
    {
        // Le défaut signalé par l'étude : **seul un renommage** levait `a_verifier`. Devant
        // 53 quartiers marqués, il n'existait aucun moyen de dire « celui-ci est bon » — sinon
        // en le renommant à l'identique.
        $quartier = Lieu::create([
            'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo', 'a_verifier' => true,
        ]);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->patch("/parametres/lieux/{$quartier->id}", ['a_verifier' => false])
            ->assertRedirect();

        $frais = $quartier->fresh();

        $this->assertFalse($frais->a_verifier);
        $this->assertSame('Nongo', $frais->nom, 'Valider ne doit pas toucher au nom.');
    }

    public function test_un_renommage_ne_vaut_plus_validation(): void
    {
        // Les deux gestes sont distincts : corriger une orthographe n'est pas confirmer que le lieu
        // existe et qu'il est bien placé. Confondre les deux validait en masse sans relecture.
        $quartier = Lieu::create([
            'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo', 'a_verifier' => true,
        ]);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->patch("/parametres/lieux/{$quartier->id}", ['nom' => 'Nongo Centre'])
            ->assertRedirect();

        $frais = $quartier->fresh();

        $this->assertSame('Nongo Centre', $frais->nom);
        $this->assertTrue($frais->a_verifier, 'Renommer ne vaut pas valider.');
    }

    public function test_la_validation_en_lot_ne_touche_que_les_enfants_directs(): void
    {
        // Jamais récursive : valider une ville ne doit pas confirmer en silence des dizaines de
        // quartiers que personne n'a lus.
        $ville   = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry', 'a_verifier' => true]);
        $commune = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma', 'a_verifier' => true]);
        $quartier = Lieu::create(['parent_id' => $commune->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => 'Nongo', 'a_verifier' => true]);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->patch('/parametres/lieux/valider', ['parent_id' => $ville->id])
            ->assertRedirect();

        $this->assertFalse($commune->fresh()->a_verifier, 'La commune est un enfant direct.');
        $this->assertTrue($quartier->fresh()->a_verifier, 'Le quartier est deux niveaux plus bas.');
        $this->assertTrue($ville->fresh()->a_verifier, 'Le parent lui-même reste marqué.');
    }

    public function test_la_validation_en_lot_valide_les_quartiers_d_une_commune(): void
    {
        $ville   = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $commune = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Dixinn']);

        foreach (['Bellevue', 'Camayenne', 'Hafia'] as $nom) {
            Lieu::create(['parent_id' => $commune->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => $nom, 'a_verifier' => true]);
        }

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->patch('/parametres/lieux/valider', ['parent_id' => $commune->id])
            ->assertRedirect();

        $this->assertSame(0, Lieu::where('parent_id', $commune->id)->where('a_verifier', true)->count());
    }

    public function test_la_validation_en_lot_exige_l_autorisation(): void
    {
        $ville = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $commune = Lieu::create(['parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Ratoma', 'a_verifier' => true]);

        // Un clerc peut **ajouter** un lieu en pleine saisie, il ne peut pas le valider : son ajout
        // entre justement dans la liste de travail de l'administrateur.
        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->patch('/parametres/lieux/valider', ['parent_id' => $ville->id])
            ->assertForbidden();

        $this->assertTrue($commune->fresh()->a_verifier);
    }

    public function test_la_route_de_validation_en_lot_ne_capture_pas_un_identifiant(): void
    {
        // Piège d'ordre de déclaration : placée après `/lieux/{lieu}`, la route « valider » aurait
        // été lue comme un identifiant de lieu et aurait répondu 404.
        $this->assertSame(
            'parametres.lieux.valider',
            app('router')->getRoutes()->getByName('parametres.lieux.valider')?->getName(),
        );
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
    //
    // ⚠️ **Le filtrage par parent a changé de côté le 2026-09-10.** Le serveur servait un niveau à
    // la fois (`GET /lieux?niveau=commune&parent=Conakry`), ce qui coûtait jusqu'à 18 requêtes sur
    // un questionnaire de modification. Il sert désormais le référentiel **entier** une fois, et la
    // cascade se résout de mémoire (`resources/js/lib/referentielLieux.js`). Ces tests vérifient
    // donc que le contrat rend le filtrage **possible** — le parent porté par chaque lieu — et
    // `ReferentielLieuxTest` couvre le point d'entrée lui-même.

    public function test_chaque_lieu_porte_son_parent_pour_permettre_le_filtrage(): void
    {
        $this->conakry();
        $kindia = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Kindia']);
        Lieu::create(['parent_id' => $kindia->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Kindia']);

        $communes = collect(Lieu::referentielComplet()['commune']);

        // Deux communes homonymes de leur ville : c'est le parent qui les distingue, pas le nom.
        $this->assertSame(
            ['Ratoma'],
            $communes->where('parent', 'Conakry')->pluck('nom')->values()->all(),
        );
        $this->assertSame(
            ['Kindia'],
            $communes->where('parent', 'Kindia')->pluck('nom')->values()->all(),
        );
    }

    public function test_un_quartier_est_rattache_a_sa_commune_et_non_a_sa_ville(): void
    {
        // Proposer tous les quartiers du pays avant de connaître la commune recréerait exactement
        // l'incohérence que la cascade supprime. Le rattachement rend ce filtrage possible.
        $this->conakry();

        $quartiers = collect(Lieu::referentielComplet()['quartier']);

        $this->assertSame(['Nongo'], $quartiers->where('parent', 'Ratoma')->pluck('nom')->all());
        $this->assertCount(0, $quartiers->where('parent', 'Conakry'));
    }

    public function test_un_lieu_desactive_nest_plus_propose(): void
    {
        $conakry = $this->conakry();
        Lieu::parNom('Ratoma', Lieu::NIVEAU_COMMUNE, $conakry->id)->update(['actif' => false]);

        $this->assertCount(0, Lieu::referentielComplet()['commune']);
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
        $this->getJson('/lieux/referentiel')->assertUnauthorized();
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
