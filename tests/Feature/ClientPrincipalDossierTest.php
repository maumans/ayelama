<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * « Qui commande ce dossier » est une donnée du dossier (2026-11-26, décision #135).
 *
 * **Le trou.** Aucune notion de client principal n'existait en base :
 * `Dossier::partiePrincipale()` la devinait en parcourant les rôles par priorité. Pour un
 * dossier commandé par quelqu'un qui n'y tient aucun rôle, le nom affiché était celui d'une
 * autre personne — sur le reçu de paiement, la liste des certifications, les balises des
 * factures et des formalités, et le signataire proposé de l'accord client, qui passent tous par
 * `clientPrincipalLabel()`.
 *
 * Deux contrats sont vérifiés ici, et le second est le plus fragile : un champ absent des règles
 * de validation est **écarté en silence** par `validated()`, sur lequel travaille
 * `creerDossier()`. C'est exactement ainsi que `donnees_prefixe` est resté NULL sur 36 parties
 * (voir LocalisationPartiesTest) et `type_personne` sur 39 (NaturePersonnePartieTest). Le test
 * poste donc par la route réelle plutôt que d'appeler `Dossier::create()`.
 */
class ClientPrincipalDossierTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

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
            ['label' => 'Constitution SARLU', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );
    }

    private function client(array $attributs): Client
    {
        return Client::create(array_merge(['type' => 'physique'], $attributs));
    }

    /** @param array<string, mixed> $extra */
    private function creerDossier(array $extra = []): Dossier
    {
        $this->actingAs($this->clerc())->post('/dossiers', array_merge([
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU commandée par un tiers au dossier',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Client Principal SARLU'],
            'parties'      => [[
                'nom'             => 'Ibrahima DIALLO',
                'role'            => 'associe_unique',
                'type_personne'   => 'physique',
                'donnees_prefixe' => 'pp',
            ]],
        ], $extra))->assertRedirect()->assertSessionHasNoErrors();

        return Dossier::latest('id')->firstOrFail();
    }

    /**
     * Le cœur de la demande : le donneur d'ordre ne tient aucun rôle, et c'est pourtant son nom
     * que le dossier doit porter.
     */
    public function test_le_client_designe_prime_sur_les_roles(): void
    {
        $commanditaire = $this->client(['nom_famille' => 'BAH', 'prenoms' => 'Mariama']);

        $dossier = $this->creerDossier(['client_principal_id' => $commanditaire->id]);

        $this->assertSame($commanditaire->id, $dossier->client_principal_id);
        $this->assertSame($commanditaire->nomComplet(), $dossier->clientPrincipalLabel());
        // La partie, elle, n'a pas bougé : le client du dossier n'est pas une partie à l'acte.
        $this->assertSame('associe_unique', $dossier->parties()->sole()->role);
    }

    /**
     * Sans désignation, le comportement reste **exactement** celui d'avant : aucun des dossiers
     * déjà en base ne change de nom, et c'est pourquoi la colonne n'a pas eu de reprise.
     */
    public function test_sans_client_designe_l_heuristique_des_roles_subsiste(): void
    {
        $dossier = $this->creerDossier();

        $this->assertNull($dossier->client_principal_id);
        $this->assertSame('Ibrahima DIALLO', $dossier->clientPrincipalLabel());
    }

    /**
     * Le défaut silencieux que ce dépôt a déjà payé deux fois : un champ non validé est retiré
     * par `validated()` avant même d'atteindre `Dossier::create()`. Rien n'échoue, rien ne
     * s'affiche — la colonne reste NULL.
     */
    public function test_le_champ_traverse_la_validation_de_la_requete(): void
    {
        $commanditaire = $this->client(['type' => 'morale', 'denomination' => 'SOCIÉTÉ COMMANDITAIRE']);

        $dossier = $this->creerDossier(['client_principal_id' => $commanditaire->id]);

        $this->assertDatabaseHas('dossiers', [
            'id'                  => $dossier->id,
            'client_principal_id' => $commanditaire->id,
        ]);
    }

    public function test_une_fiche_inconnue_est_refusee(): void
    {
        $this->actingAs($this->clerc())->post('/dossiers', [
            'type_acte_id'        => $this->typeActe()->id,
            'objet'               => 'Constitution SARLU avec une fiche client inexistante',
            'notaire_id'          => $this->notaire()->id,
            'client_principal_id' => 999999,
            'donnees'             => ['soc.denomination' => 'Fiche Inconnue SARLU'],
        ])->assertSessionHasErrors('client_principal_id');
    }

    /** Il se corrige après coup, et se retire — l'heuristique reprend alors la main. */
    public function test_il_se_modifie_et_se_retire_depuis_la_fiche_dossier(): void
    {
        $dossier = $this->creerDossier();
        $commanditaire = $this->client(['nom_famille' => 'CAMARA', 'prenoms' => 'Alpha']);

        $this->actingAs($this->clerc())
            ->patch("/dossiers/{$dossier->reference}", ['client_principal_id' => $commanditaire->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($commanditaire->nomComplet(), $dossier->fresh()->clientPrincipalLabel());

        $this->actingAs($this->clerc())
            ->patch("/dossiers/{$dossier->reference}", ['client_principal_id' => null])
            ->assertSessionHasNoErrors();

        $dossier = $dossier->fresh();
        $this->assertNull($dossier->client_principal_id);
        $this->assertSame('Ibrahima DIALLO', $dossier->clientPrincipalLabel());
    }

    /**
     * Supprimer une fiche du répertoire ne doit pas emporter les dossiers qui la citent —
     * `nullOnDelete`, comme `societe_id`. Le dossier reprend alors son nom d'avant.
     */
    public function test_supprimer_la_fiche_ne_supprime_pas_le_dossier(): void
    {
        $commanditaire = $this->client(['nom_famille' => 'SYLLA', 'prenoms' => 'Fatoumata']);
        $dossier = $this->creerDossier(['client_principal_id' => $commanditaire->id]);

        $commanditaire->delete();

        $dossier = $dossier->fresh();
        $this->assertNotNull($dossier);
        $this->assertNull($dossier->client_principal_id);
        $this->assertSame('Ibrahima DIALLO', $dossier->clientPrincipalLabel());
    }
}
