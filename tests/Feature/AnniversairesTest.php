<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\SouhaitAnniversaire;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * L'écran « Anniversaires » — et la trace des souhaits déjà adressés (2026-10-05).
 *
 * ⚠️ **Ce fichier n'aurait pas pu exister avant cette passe.** Le filtrage employait
 * `DATE_FORMAT(date_naissance, '%m-%d')`, propre à MySQL, alors que les tests tournent sous
 * SQLite : toute visite de la page y levait une erreur SQL. C'était le seul endroit de
 * l'application à employer cette fonction, et l'écran était le seul sans aucun test — les deux
 * faits n'en font qu'un.
 *
 * Le temps est **figé** dans chaque test : un écran qui calcule « dans combien de jours »
 * donne un résultat différent demain, et un test qui passe aujourd'hui seulement ne prouve rien.
 */
class AnniversairesTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

        return $user->fresh();
    }

    private function client(string $naissance, string $nom = 'Thierno DIALLO', string $type = 'physique'): Client
    {
        return Client::create([
            'type'           => $type,
            'statut'         => 'prospect',
            'prenom_nom'     => $type === 'physique' ? $nom : null,
            'denomination'   => $type === 'morale' ? $nom : null,
            'date_naissance' => $naissance,
            'email'          => 'thierno@example.gn',
            'telephone'      => '621 78 37 32',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function lignes($reponse): array
    {
        return $reponse->viewData('page')['props']['clients'] ?? [];
    }

    // ═══ La liste ════════════════════════════════════════════════════════

    public function test_un_anniversaire_dans_la_fenetre_est_liste(): void
    {
        Carbon::setTestNow('2026-10-05');
        $this->client('1970-10-20');

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/anniversaires')->assertOk());

        $this->assertCount(1, $lignes);
        $this->assertSame(15, $lignes[0]['jours_restants']);
        $this->assertSame('2026-10-20', $lignes[0]['prochain_anniversaire']);
        $this->assertSame(56, $lignes[0]['age_a_venir']);
    }

    public function test_un_anniversaire_hors_fenetre_est_ecarte(): void
    {
        Carbon::setTestNow('2026-10-05');
        $this->client('1970-12-20');

        $this->assertCount(0, $this->lignes($this->actingAs($this->clerc())->get('/anniversaires')));
        // La fenêtre de 90 jours, elle, l'atteint.
        $this->assertCount(1, $this->lignes($this->actingAs($this->clerc())->get('/anniversaires?fenetre=90')));
    }

    /** Une société n'a pas d'anniversaire : la liste ne doit jamais en proposer un. */
    public function test_une_personne_morale_nest_jamais_listee(): void
    {
        Carbon::setTestNow('2026-10-05');
        $this->client('1970-10-20', 'Addvalis SARL', 'morale');

        $this->assertCount(0, $this->lignes($this->actingAs($this->clerc())->get('/anniversaires')));
    }

    /**
     * Le mois civil inclut ce qui est **déjà passé** ce mois-ci — c'est sa raison d'être :
     * préparer ou rattraper une série en début de mois.
     */
    public function test_le_mois_civil_inclut_un_anniversaire_deja_passe(): void
    {
        Carbon::setTestNow('2026-10-25');
        $this->client('1970-10-20');

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/anniversaires?mois=10'));

        $this->assertCount(1, $lignes);
        $this->assertSame(-5, $lignes[0]['jours_restants'], 'Un anniversaire passé se compte en négatif.');
        $this->assertSame(2026, $lignes[0]['annee_occurrence'], "L'occurrence du mois est celle de cette année.");
    }

    /**
     * Un 29 février ne doit pas glisser au 1er mars en silence.
     *
     * `CarbonImmutable::createFromDate(2026, 2, 29)` reporte au 1er mars sans rien dire : l'étude
     * souhaiterait alors un jour trop tard, et rien ne l'indiquerait.
     */
    public function test_un_29_fevrier_est_souhaite_le_28_en_annee_non_bissextile(): void
    {
        Carbon::setTestNow('2027-02-01');
        $this->client('1996-02-29');

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/anniversaires'));

        $this->assertCount(1, $lignes);
        $this->assertSame('2027-02-28', $lignes[0]['prochain_anniversaire']);
    }

    // ═══ La marque ═══════════════════════════════════════════════════════

    public function test_marquer_puis_demarquer_un_souhait(): void
    {
        Carbon::setTestNow('2026-10-05');
        $client = $this->client('1970-10-20');
        $clerc  = $this->clerc();

        $this->actingAs($clerc)
            ->post("/anniversaires/{$client->id}/souhait", ['annee' => 2026, 'canal' => 'whatsapp'])
            ->assertRedirect();

        $souhait = SouhaitAnniversaire::sole();
        $this->assertSame(2026, $souhait->annee);
        $this->assertSame('whatsapp', $souhait->canal);
        $this->assertSame($clerc->id, $souhait->user_id);

        // La même route rebascule : le bouton a deux états, pas l'écran deux appels.
        $this->actingAs($clerc)->post("/anniversaires/{$client->id}/souhait", ['annee' => 2026]);
        $this->assertSame(0, SouhaitAnniversaire::count());
    }

    public function test_la_marque_apparait_sur_la_ligne(): void
    {
        Carbon::setTestNow('2026-10-05');
        $client = $this->client('1970-10-20');
        $clerc  = $this->clerc();

        $this->actingAs($clerc)->post("/anniversaires/{$client->id}/souhait", ['annee' => 2026, 'canal' => 'email']);

        $ligne = $this->lignes($this->actingAs($clerc)->get('/anniversaires'))[0];

        $this->assertNotNull($ligne['souhait']);
        $this->assertSame('par email', $ligne['souhait']['canal']);
        $this->assertSame($clerc->name, $ligne['souhait']['auteur']);
    }

    /**
     * **L'année est celle de l'anniversaire fêté, pas celle du geste.**
     *
     * Souhaiter le 31 décembre pour un anniversaire du 2 janvier pose la marque sur l'année
     * suivante. Confondre les deux ferait réapparaître le client deux jours plus tard, déjà
     * souhaité — le défaut exact que cette trace existe pour éviter.
     */
    public function test_la_marque_porte_sur_lannee_de_loccurrence_et_non_du_geste(): void
    {
        Carbon::setTestNow('2026-12-31');
        $client = $this->client('1990-01-02');
        $clerc  = $this->clerc();

        $ligne = $this->lignes($this->actingAs($clerc)->get('/anniversaires'))[0];
        $this->assertSame(2027, $ligne['annee_occurrence'], "L'occurrence à souhaiter est celle de 2027.");

        $this->actingAs($clerc)->post("/anniversaires/{$client->id}/souhait", ['annee' => $ligne['annee_occurrence']]);

        $this->assertSame(2027, SouhaitAnniversaire::sole()->annee);
        $this->assertNotNull($this->lignes($this->actingAs($clerc)->get('/anniversaires'))[0]['souhait']);
    }

    // ═══ Les filtres ═════════════════════════════════════════════════════

    public function test_le_filtre_restants_masque_les_souhaites(): void
    {
        Carbon::setTestNow('2026-10-05');
        $souhaite = $this->client('1970-10-20', 'Déjà souhaité');
        $this->client('1985-10-22', 'Reste à faire');
        $clerc = $this->clerc();

        $this->actingAs($clerc)->post("/anniversaires/{$souhaite->id}/souhait", ['annee' => 2026]);

        $restants = $this->lignes($this->actingAs($clerc)->get('/anniversaires?statut=restants'));
        $this->assertCount(1, $restants);
        $this->assertSame('Reste à faire', $restants[0]['nom']);

        $faits = $this->lignes($this->actingAs($clerc)->get('/anniversaires?statut=souhaites'));
        $this->assertCount(1, $faits);
        $this->assertSame('Déjà souhaité', $faits[0]['nom']);
    }

    public function test_la_recherche_filtre_par_nom(): void
    {
        Carbon::setTestNow('2026-10-05');
        $this->client('1970-10-20', 'Thierno DIALLO');
        $this->client('1985-10-22', 'Michel Oliano');

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/anniversaires?q=oliano'));

        $this->assertCount(1, $lignes, 'La recherche doit ignorer la casse.');
        $this->assertSame('Michel Oliano', $lignes[0]['nom']);
    }

    /** Une fenêtre hors catalogue retombe sur le défaut, elle n'ouvre pas une liste arbitraire. */
    public function test_une_fenetre_inconnue_retombe_sur_le_defaut(): void
    {
        Carbon::setTestNow('2026-10-05');

        $reponse = $this->actingAs($this->clerc())->get('/anniversaires?fenetre=9999')->assertOk();

        $this->assertSame(30, $reponse->viewData('page')['props']['filtres']['fenetre']);
    }
}
