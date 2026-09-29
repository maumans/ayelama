<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'emplacement d'une partie dans le questionnaire doit survivre à la validation (2026-09-24).
 *
 * `donnees_prefixe`, `donnees_bloc` et `donnees_index` disent où une `Partie` se trouve dans le
 * questionnaire. Le frontend les émet depuis l'origine (partiesPayload.js) et le modèle les
 * accepte (`$fillable`) — mais ils ne figuraient pas dans `StoreDossierRequest::partiesRules()`,
 * et `store()` comme `updateQuestionnaire()` travaillent sur `validated()`. Laravel les écartait
 * donc en silence, à la création **comme** à la mise à jour.
 *
 * Conséquence mesurée sur la base de développement : les trois colonnes étaient `NULL` sur les
 * 36 parties existantes, `estProjetable()` toujours faux, et tout `ClientProjectionService`
 * dormant depuis le 2026-08-03 — c'est-à-dire la décision #33 (« la fiche client est la source
 * de vérité de l'identité, corriger une fiche répercute sur les dossiers non clôturés ») sans
 * aucun effet. Rien ne le signalait : `ClientProjectionTest` construit ses `Partie` directement
 * en base et ne passait donc jamais par la validation.
 *
 * Ces tests ferment ce trou : ils passent par HTTP, seul chemin où le défaut se voyait.
 */
class LocalisationPartiesTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    private function notaire(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Notaire->value]);

        return $user->fresh();
    }

    private function typeActe(): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => 'SOC-SARLU'],
            [
                'label'       => 'Constitution SARLU',
                'categorie'   => CategorieActe::Societe,
                'delai_jours' => 30,
                'actif'       => true,
            ],
        );
    }

    private function client(): Client
    {
        return Client::create([
            'type'       => 'physique',
            'statut'     => 'prospect',
            'prenom_nom' => 'Mamadou BAH',
        ]);
    }

    public function test_la_localisation_dune_section_scalaire_est_persistee_a_la_creation(): void
    {
        $client = $this->client();

        $this->actingAs($this->clerc())->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU pour vérifier la localisation des parties',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Localisation SARLU'],
            'parties'      => [[
                'nom'             => 'Mamadou BAH',
                'role'            => 'associe_unique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $partie = Partie::where('role', 'associe_unique')->sole();

        $this->assertSame(
            'pp',
            $partie->donnees_prefixe,
            "Le préfixe envoyé par le frontend doit être persisté, sans quoi la partie n'est pas "
            . 'projetable et la fiche client ne peuple jamais le questionnaire.',
        );
        $this->assertTrue($partie->estProjetable());
    }

    public function test_la_localisation_dun_item_de_bloc_repetable_est_persistee(): void
    {
        $client = $this->client();

        $this->actingAs($this->clerc())->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution avec un bloc répétable pour la localisation',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Bloc SARL'],
            'parties'      => [[
                'nom'           => 'Mamadou BAH',
                'role'          => 'associe',
                'client_id'     => $client->id,
                'donnees_bloc'  => 'associes',
                'donnees_index' => 2,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $partie = Partie::where('role', 'associe')->sole();

        $this->assertSame('associes', $partie->donnees_bloc);
        $this->assertSame(2, $partie->donnees_index, "L'index doit rester entier, pas être perdu ni converti.");
        $this->assertTrue($partie->estProjetable());
    }

    public function test_la_localisation_survit_a_une_mise_a_jour_du_questionnaire(): void
    {
        // Le cas le plus traître : la création pouvait bien se passer, la première
        // resynchronisation remettait la colonne à NULL — et la projection s'éteignait.
        $clerc  = $this->clerc();
        $client = $this->client();

        $this->actingAs($clerc)->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU dont le questionnaire sera modifié ensuite',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Mise à jour SARLU'],
            'parties'      => [[
                'nom'             => 'Mamadou BAH',
                'role'            => 'associe_unique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier = Dossier::sole();
        $partie  = $dossier->parties()->sole();

        $this->actingAs($clerc)->patch("/dossiers/{$dossier->reference}/questionnaire", [
            'donnees'      => ['soc.denomination' => 'Mise à jour SARLU', 'soc.sigle' => 'MAJ'],
            'managedRoles' => ['associe_unique'],
            'parties'      => [[
                'partie_id'       => $partie->id,
                'nom'             => 'Mamadou BAH',
                'role'            => 'associe_unique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('pp', $partie->fresh()->donnees_prefixe);
    }

    public function test_une_localisation_absente_reste_acceptee(): void
    {
        // Les trois colonnes sont nullable : une personne ajoutée hors questionnaire
        // (accompagnateur, témoin) n'a pas d'emplacement, et ne doit pas être refusée.
        $this->actingAs($this->clerc())->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU sans localisation de partie',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Sans localisation'],
            'parties'      => [['nom' => 'Témoin sans emplacement', 'role' => 'associe_unique']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $partie = Partie::sole();
        $this->assertNull($partie->donnees_prefixe);
        $this->assertFalse($partie->estProjetable());
    }

    // ── La simulation ────────────────────────────────────────────────────────

    public function test_la_simulation_de_projection_necrit_rien(): void
    {
        // Le pire résultat possible pour cette commande serait d'écrire : elle sert à décider
        // si l'on accepte un écrasement irréversible. Elle rejoue donc la vraie projection
        // dans une transaction annulée, et ce test le vérifie sur un cas qui changerait
        // effectivement quelque chose.
        $clerc  = $this->clerc();
        $client = $this->client();

        $this->actingAs($clerc)->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU pour vérifier la simulation de projection',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => [
                'soc.denomination' => 'Simulation SARLU',
                // Identité saisie à la main, volontairement différente de la fiche : c'est
                // exactement ce que la projection remplacerait.
                'pp.prenom_nom'    => 'Saisi à la main',
            ],
            'parties'      => [[
                'nom'             => 'Mamadou BAH',
                'role'            => 'associe_unique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier = Dossier::sole();
        $avant   = $dossier->questionnaire->donnees;

        // La projection a déjà tourné à la création : c'est la fiche qui fait foi désormais.
        $this->assertSame('Mamadou BAH', $avant['pp.prenom_nom']);

        // On repose une valeur divergente pour que la simulation ait quelque chose à signaler.
        $dossier->questionnaire->update(['donnees' => [...$avant, 'pp.prenom_nom' => 'Divergent']]);

        $this->artisan('ayelema:projection')->assertSuccessful();

        $this->assertSame(
            'Divergent',
            $dossier->questionnaire->fresh()->donnees['pp.prenom_nom'],
            'La simulation a écrit en base — elle doit annuler sa transaction.',
        );
    }
}
