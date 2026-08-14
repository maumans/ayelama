<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registre des sociétés — activé le 2026-08-11.
 *
 * La table `societes` existait depuis le 2026-06-29 sans jamais être alimentée : ni
 * controller, ni route, ni seeder (devbook §826). Ouvrir un dossier de modification obligeait
 * donc à retaper la dénomination, la forme, le capital et le siège d'une société que l'étude
 * avait elle-même constituée.
 */
class RegistreSocietesTest extends TestCase
{
    use RefreshDatabase;

    private function utilisateur(RoleUtilisateur ...$roles): User
    {
        $user = User::factory()->create(['actif' => true]);
        foreach ($roles as $role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role->value]);
        }

        return $user->fresh();
    }

    private function typeActe(string $code, string $categorie = 'societe'): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => $categorie, 'prefixe_reference' => 'SOC'],
        );
    }

    private function dossier(string $code, array $donnees): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test du registre des sociétés',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    // ── Backfill ─────────────────────────────────────────────────────────────

    public function test_le_backfill_est_un_dry_run_par_defaut(): void
    {
        $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya Distribution SARLU']);

        $this->artisan('ayelema:societes-backfill')->assertSuccessful();

        // Une commande lancée pour voir ne doit rien écrire : c'est le parti pris de
        // `ayelema:brouillons-purger`, et il vaut à plus forte raison pour des fiches de
        // référence et des dossiers modifiés.
        $this->assertSame(0, Societe::count());
    }

    public function test_le_backfill_cree_une_fiche_et_rattache_le_dossier(): void
    {
        $dossier = $this->dossier('SOC-SARLU', [
            'soc.denomination'     => 'Faya Distribution SARLU',
            'soc.capital_chiffres' => 50_000_000,
            'soc.siege_quartier'   => 'Almamya',
            'soc.rccm'             => 'GN-CON-2020-B-0001',
        ]);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $societe = Societe::sole();
        $this->assertSame('Faya Distribution SARLU', $societe->denomination);
        // La forme vient du **code du type d'acte**, jamais du questionnaire — les dossiers
        // réels en sont dépourvus (constat du 2026-08-05).
        $this->assertSame('SARLU', $societe->forme);
        $this->assertSame('GN-CON-2020-B-0001', $societe->rccm_numero);
        $this->assertSame($dossier->id, $societe->dossier_id, 'Le dossier de constitution est son origine.');
        $this->assertSame($societe->id, $dossier->fresh()->societe_id);
    }

    public function test_le_backfill_est_idempotent(): void
    {
        $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya Distribution SARLU']);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();
        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $this->assertSame(1, Societe::count());
    }

    public function test_le_backfill_rapproche_les_denominations_a_la_casse_et_aux_espaces_pres(): void
    {
        // Même normalisation que la règle 4 (`Societe::normaliserDenomination`) : deux
        // normalisations divergentes feraient qu'une dénomination jugée unique par la règle
        // créerait malgré tout un doublon au registre.
        $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya Distribution SARLU']);
        $this->dossier('SOC-MOD', ['soc.denomination' => '  faya   distribution sarlu ']);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $this->assertSame(1, Societe::count());
    }

    public function test_un_dossier_de_modification_se_rattache_a_la_fiche_existante_sans_la_dupliquer(): void
    {
        $constitution = $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya Distribution SARLU']);
        $modification = $this->dossier('SOC-MOD', ['soc.denomination' => 'Faya Distribution SARLU']);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $societe = Societe::sole();
        $this->assertSame($societe->id, $constitution->fresh()->societe_id);
        $this->assertSame($societe->id, $modification->fresh()->societe_id);
        // Une modification n'est pas l'origine de la société.
        $this->assertSame($constitution->id, $societe->dossier_id);
    }

    public function test_un_dossier_sans_denomination_est_ignore(): void
    {
        $this->dossier('SOC-MOD', ['objet_modification' => 'Changement à préciser']);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $this->assertSame(0, Societe::count());
    }

    public function test_les_dossiers_hors_categorie_societe_sont_ignores(): void
    {
        $this->typeActe('VTE-IMM', 'vente');
        $this->dossier('VTE-IMM', ['soc.denomination' => 'Ne doit pas être fichée']);

        $this->artisan('ayelema:societes-backfill --appliquer')->assertSuccessful();

        $this->assertSame(0, Societe::count());
    }

    // ── Alimentation à la création d'un dossier ──────────────────────────────

    public function test_creer_un_dossier_de_constitution_cree_la_fiche_du_registre(): void
    {
        $user = $this->utilisateur(RoleUtilisateur::Clerc);
        $notaire = $this->utilisateur(RoleUtilisateur::Notaire);
        $this->typeActe('SOC-SARLU');

        $this->actingAs($user)->post('/dossiers', [
            'type_acte_id' => $this->typeActe('SOC-SARLU')->id,
            'objet'        => 'Constitution de la SARLU de test pour le registre',
            'notaire_id'   => $notaire->id,
            'donnees'      => [
                'soc.denomination'     => 'Nouvelle Société SARLU',
                'soc.capital_chiffres' => 10_000_000,
            ],
        ])->assertRedirect();

        $societe = Societe::sole();
        $this->assertSame('Nouvelle Société SARLU', $societe->denomination);
        $this->assertSame('SARLU', $societe->forme);
        $this->assertNotNull($societe->dossier_id);
    }

    public function test_creer_un_dossier_de_modification_ne_cree_aucune_fiche(): void
    {
        // Une modification porte sur une société **préexistante** : sa fiche est choisie dans
        // l'assistant, pas créée par le dossier.
        $user = $this->utilisateur(RoleUtilisateur::Clerc);
        $notaire = $this->utilisateur(RoleUtilisateur::Notaire);
        $societe = Societe::create(['denomination' => 'Faya Distribution SARLU', 'forme' => 'SARLU']);

        $this->actingAs($user)->post('/dossiers', [
            'type_acte_id' => $this->typeActe('SOC-MOD')->id,
            'societe_id'   => $societe->id,
            'objet'        => 'Transfert du siège social de Faya Distribution',
            'notaire_id'   => $notaire->id,
            'donnees'      => ['modif.types' => ['Transfert du siège social']],
        ])->assertRedirect();

        $this->assertSame(1, Societe::count());
        $this->assertSame($societe->id, Dossier::latest('id')->first()->societe_id);
    }

    // ── Autocomplétion ───────────────────────────────────────────────────────

    private function fiche(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination' => 'Faya Distribution SARLU',
            'sigle'        => 'FD',
            'forme'        => 'SARLU',
            'rccm_numero'  => 'GN-CON-2020-B-0001',
            'nif'          => '000123456',
        ], $attributs));
    }

    public function test_lautocompletion_cherche_sur_denomination_sigle_rccm_et_nif(): void
    {
        $this->fiche();
        $user = $this->utilisateur(RoleUtilisateur::Clerc);

        foreach (['Faya', 'FD', 'GN-CON-2020', '000123'] as $terme) {
            $reponse = $this->actingAs($user)->getJson('/societes/autocomplete?q=' . urlencode($terme));

            $reponse->assertOk();
            $this->assertCount(1, $reponse->json(), "La recherche « {$terme} » doit trouver la fiche.");
        }
    }

    public function test_lautocompletion_sans_terme_propose_les_fiches_recentes(): void
    {
        // Parti pris de ClientController::autocomplete : permettre de parcourir le registre
        // sans connaître déjà le nom recherché.
        $this->fiche();
        $this->fiche(['denomination' => 'Guinée Tech SAS', 'sigle' => 'GT', 'rccm_numero' => null, 'nif' => null]);

        $reponse = $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))->getJson('/societes/autocomplete');

        $reponse->assertOk();
        $this->assertCount(2, $reponse->json());
    }

    public function test_une_societe_desactivee_nest_plus_proposee(): void
    {
        $this->fiche(['actif' => false]);

        $reponse = $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))->getJson('/societes/autocomplete?q=Faya');

        $reponse->assertOk();
        $this->assertCount(0, $reponse->json());
    }

    public function test_la_fiche_expose_les_personnes_connues_du_dossier_de_constitution(): void
    {
        // Pour une cession, le cédant est presque toujours un associé déjà fiché : le proposer
        // évite qu'une fiche client concurrente soit créée pour la même personne.
        $constitution = $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya Distribution SARLU']);
        $client = Client::create(['type' => 'physique', 'nom_famille' => 'DIALLO', 'prenoms' => 'Ibrahima']);
        Partie::create([
            'dossier_id' => $constitution->id,
            'client_id'  => $client->id,
            'nom'        => 'Ibrahima DIALLO',
            'role'       => 'associe_unique',
        ]);
        $societe = $this->fiche(['dossier_id' => $constitution->id]);

        $reponse = $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->getJson("/societes/{$societe->id}");

        $reponse->assertOk();
        $this->assertCount(1, $reponse->json('personnesConnues'));
        $this->assertSame($client->id, $reponse->json('personnesConnues.0.client_id'));
    }

    // ── Autorisations ────────────────────────────────────────────────────────

    public function test_un_utilisateur_sans_role_ne_peut_pas_creer_de_societe(): void
    {
        // Un compte sans rôle n'a pas vocation à tenir le registre — même raisonnement que
        // ClientPolicy, dont le test avait dû être corrigé pour ce motif exact.
        $this->actingAs($this->utilisateur())
            ->postJson('/societes', ['denomination' => 'Société interdite'])
            ->assertForbidden();

        $this->assertSame(0, Societe::count());
    }

    public function test_un_clerc_peut_creer_et_corriger_une_fiche(): void
    {
        $user = $this->utilisateur(RoleUtilisateur::Clerc);

        $creation = $this->actingAs($user)->postJson('/societes', [
            'denomination'     => 'Société hors registre SARL',
            'forme'            => 'SARL',
            'capital_chiffres' => 25_000_000,
        ]);

        $creation->assertCreated();
        $id = $creation->json('id');
        $this->assertSame('Société hors registre SARL', Societe::find($id)->denomination);

        $this->actingAs($user)
            ->patchJson("/societes/{$id}", ['denomination' => 'Société corrigée SARL', 'forme' => 'SARL'])
            ->assertOk();

        $this->assertSame('Société corrigée SARL', Societe::find($id)->denomination);
    }

    public function test_un_formaliste_ne_peut_pas_corriger_une_fiche(): void
    {
        $societe = $this->fiche();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Formaliste))
            ->patchJson("/societes/{$societe->id}", ['denomination' => 'Tentative'])
            ->assertForbidden();
    }

    public function test_aucune_route_ne_permet_de_supprimer_une_fiche(): void
    {
        // Supprimer une fiche référencée casserait la projection `soc.*` des dossiers qui s'en
        // servent, donc leurs actes. Une société hors périmètre est désactivée.
        $societe = $this->fiche();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->deleteJson("/societes/{$societe->id}")
            ->assertStatus(405);
    }

    // ── Projection ───────────────────────────────────────────────────────────

    public function test_la_fiche_se_projette_dans_les_cles_soc_du_questionnaire(): void
    {
        // Sans cette projection, choisir une société afficherait la bonne fiche à l'écran mais
        // laisserait les balises ${soc.*} vides dans les actes générés.
        $societe = $this->fiche([
            'capital_chiffres' => 50_000_000,
            'siege_quartier'   => 'Almamya',
            'objet_social'     => 'Commerce général',
        ]);

        $donnees = $societe->versQuestionnaire();

        $this->assertSame('Faya Distribution SARLU', $donnees['soc.denomination']);
        $this->assertSame('SARLU', $donnees['soc.forme']);
        $this->assertSame('GN-CON-2020-B-0001', $donnees['soc.rccm']);
        $this->assertSame('Almamya', $donnees['soc.siege_quartier']);
        $this->assertSame('Commerce général', $donnees['soc.objet_social']);
        // Les champs vides ne sont pas projetés : écraser une saisie avec du vide ferait
        // perdre une information que l'utilisateur vient d'ajouter.
        $this->assertArrayNotHasKey('soc.telephone_societe', $donnees);
    }

    public function test_le_libelle_de_forme_couvre_les_neuf_formes(): void
    {
        // Le `match` précédent ignorait SNC, SCS et SAU — les trois formes ajoutées par le CR
        // de juillet 2026 — et les affichait sous leur sigle brut.
        foreach (['SNC', 'SCS', 'SAU'] as $forme) {
            $libelle = Societe::create(['denomination' => "Test {$forme}", 'forme' => $forme])->formeLabel();

            $this->assertNotSame($forme, $libelle, "La forme {$forme} doit avoir un libellé complet.");
            $this->assertStringContainsString($forme, $libelle);
        }
    }

    // ── Dirigeant en exercice ────────────────────────────────────────────────
    //
    // Signalé à l'usage : « le gérant reste à saisir alors qu'on a sélectionné la société ». Le
    // champ `soc.gerant_actuel` n'était projeté par personne, alors que le nom s'affichait juste
    // au-dessus dans « Personnes connues de cette société ».

    /** Société rattachée à un dossier de constitution portant les parties passées. */
    private function societeAvecParties(string $code, array $parties): Societe
    {
        $dossier = $this->dossier($code, ['soc.denomination' => 'Société de test']);

        foreach ($parties as $role => $nom) {
            $dossier->parties()->create(['nom' => $nom, 'role' => $role]);
        }

        return Societe::create([
            'denomination' => 'Société de test',
            'dossier_id'   => $dossier->id,
        ])->fresh();
    }

    public function test_le_gerant_vient_de_la_partie_gerante_du_dossier_dorigine(): void
    {
        $societe = $this->societeAvecParties('SOC-SARL', [
            'associe' => 'Mariama SOW',
            'gerant'  => 'Ibrahima DIALLO',
        ]);

        // Et non « Mariama SOW », qui figure pourtant en premier : l'ordre des rôles compte.
        $this->assertSame('Ibrahima DIALLO', $societe->gerantActuel());
    }

    public function test_lassocie_unique_dune_sarlu_est_reconnu_comme_dirigeant(): void
    {
        // Le questionnaire de constitution ne crée une partie `gerant` distincte que si la case
        // « le gérant est une personne différente de l'associé unique » a été cochée.
        $societe = $this->societeAvecParties('SOC-SARLU', ['associe_unique' => 'Ibrahima DIALLO']);

        $this->assertSame('Ibrahima DIALLO', $societe->gerantActuel());
    }

    public function test_le_president_dune_sas_est_reconnu_comme_dirigeant(): void
    {
        // Le cas qui manquait : `associesConnus()` ne retient que associe/associe_unique/gerant,
        // si bien qu'une SAS restait sans dirigeant. Constaté sur la base réelle.
        $societe = $this->societeAvecParties('SOC-SAS', [
            'associe'   => 'Mariama SOW',
            'president' => 'Mamadou Alpha DIALLO',
        ]);

        $this->assertSame('Mamadou Alpha DIALLO', $societe->gerantActuel());
    }

    public function test_le_pca_dune_sa_prime_sur_son_directeur_general(): void
    {
        // Le PCA est le représentant légal d'une SA — l'ordre de ROLES_DIRECTION le garantit.
        $societe = $this->societeAvecParties('SOC-SA', [
            'dg'  => 'Fanta YARRA',
            'pca' => 'Alpha Mamadou BAH',
        ]);

        $this->assertSame('Alpha Mamadou BAH', $societe->gerantActuel());
    }

    public function test_la_direction_declaree_prime_sur_le_dossier_dorigine(): void
    {
        // `direction` est écrite par SocieteMutationService quand un changement de gérance est
        // porté au registre : elle doit alors l'emporter sur le gérant d'origine, sinon un
        // changement appliqué serait invisible.
        $societe = $this->societeAvecParties('SOC-SARL', ['gerant' => 'Ibrahima DIALLO']);
        $societe->update(['direction' => ['gerant' => 'Aïssata KANTÉ']]);

        $this->assertSame('Aïssata KANTÉ', $societe->fresh()->gerantActuel());
    }

    public function test_une_societe_sans_dossier_dorigine_na_pas_de_dirigeant_connu(): void
    {
        // Société qu'un autre office a constituée : rien n'est connu, et il faut retourner null
        // plutôt que d'échouer — le champ reste alors saisissable.
        $societe = Societe::create(['denomination' => 'Société hors registre']);

        $this->assertNull($societe->gerantActuel());
    }

    public function test_le_dirigeant_est_projete_dans_le_questionnaire(): void
    {
        // C'est cette projection qui préremplit le champ dans l'assistant **et** qui alimente la
        // balise `${soc.gerant_actuel}` du PV et des statuts mis à jour.
        $societe = $this->societeAvecParties('SOC-SARL', ['gerant' => 'Ibrahima DIALLO']);

        $this->assertSame('Ibrahima DIALLO', $societe->versQuestionnaire()['soc.gerant_actuel'] ?? null);
    }

    public function test_le_dirigeant_est_expose_par_lautocompletion(): void
    {
        $societe = $this->societeAvecParties('SOC-SARL', ['gerant' => 'Ibrahima DIALLO']);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->getJson("/societes/{$societe->id}")
            ->assertOk()
            ->assertJsonPath('gerant_actuel', 'Ibrahima DIALLO');
    }

    // ── Fiche incomplète : la saisie du dossier enrichit le registre ─────────
    //
    // Signalé à l'usage : `MICH SARL` était au registre **sans numéro RCCM**, or ce champ est
    // obligatoire à l'acte. Il était masqué au motif que la fiche le portait — donc obligatoire et
    // impossible à saisir, le seul recours affiché étant de détacher la société et de perdre tout
    // le préremplissage.

    public function test_une_colonne_vide_de_la_fiche_est_completee_depuis_le_questionnaire(): void
    {
        $societe = Societe::create([
            'denomination' => 'MICH SARL',
            'forme'        => 'SARLU',
            'rccm_numero'  => null,
        ]);

        $ajouts = $societe->completerDepuisQuestionnaire([
            'soc.rccm' => 'GN-CON-2020-B-0042',
            'soc.nif'  => '000123456',
        ]);

        $this->assertSame(['rccm_numero' => 'GN-CON-2020-B-0042', 'nif' => '000123456'], $ajouts);
        $this->assertSame('GN-CON-2020-B-0042', $societe->fresh()->rccm_numero);
    }

    public function test_une_colonne_renseignee_nest_jamais_ecrasee(): void
    {
        // Une valeur déjà au registre est la vérité de référence : la corriger relève d'une
        // modification statutaire (SocieteMutationService, à l'entrée en Expédition), pas d'un
        // formulaire de création.
        $societe = Societe::create([
            'denomination' => 'MICH SARL',
            'rccm_numero'  => 'GN-CON-2020-B-0001',
        ]);

        $ajouts = $societe->completerDepuisQuestionnaire(['soc.rccm' => 'GN-CON-2099-B-9999']);

        $this->assertSame([], $ajouts);
        $this->assertSame('GN-CON-2020-B-0001', $societe->fresh()->rccm_numero);
    }

    public function test_une_valeur_vide_du_questionnaire_ne_complete_rien(): void
    {
        $societe = Societe::create(['denomination' => 'MICH SARL']);

        $this->assertSame([], $societe->completerDepuisQuestionnaire(['soc.rccm' => '']));
        $this->assertNull($societe->fresh()->rccm_numero);
    }

    public function test_une_modification_sur_societe_hors_registre_cree_sa_fiche(): void
    {
        // Chemin « société hors registre » de l'assistant : la saisie doit entrer au registre, sinon
        // elle ne servirait qu'une fois et le dossier suivant la redemanderait.
        $notaire = $this->utilisateur(RoleUtilisateur::Notaire);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->post('/dossiers', [
                'type_acte_id' => $this->typeActe('SOC-MOD')->id,
                'objet'        => "Transfert du siège social de FANTAS SARL",
                'notaire_id'   => $notaire->id,
                'donnees'      => [
                    'soc.denomination' => 'FANTAS SARL',
                    'soc.rccm'         => 'GN-CON-12345',
                    'modif.types'      => ['Transfert du siège social'],
                ],
            ])
            ->assertRedirect();

        $societe = Societe::where('denomination', 'FANTAS SARL')->first();

        $this->assertNotNull($societe, 'La société saisie doit rejoindre le registre.');
        $this->assertSame('GN-CON-12345', $societe->rccm_numero);
        $this->assertSame($societe->id, Dossier::latest('id')->first()->societe_id);

        // `dossier_id` reste nul : ce dossier ne constitue pas la société. C'est aussi ce qui fait
        // que son dossier constitutif reste exigé.
        $this->assertNull($societe->dossier_id);
        $this->assertTrue($societe->exigePiecesConstitutives());
    }

    public function test_une_constitution_se_declare_origine_de_la_fiche(): void
    {
        // Non-régression du contraire : une création, elle, est bien l'origine de la société.
        $notaire = $this->utilisateur(RoleUtilisateur::Notaire);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->post('/dossiers', [
                'type_acte_id' => $this->typeActe('SOC-SARLU')->id,
                'objet'        => 'Constitution de NOUVELLE SARLU',
                'notaire_id'   => $notaire->id,
                'donnees'      => ['soc.denomination' => 'NOUVELLE SARLU'],
            ])
            ->assertRedirect();

        $societe = Societe::where('denomination', 'NOUVELLE SARLU')->first();

        $this->assertNotNull($societe->dossier_id);
        $this->assertFalse($societe->exigePiecesConstitutives());
    }

    public function test_creer_un_dossier_de_modification_complete_la_fiche_et_le_journalise(): void
    {
        $societe = Societe::create(['denomination' => 'MICH SARL', 'forme' => 'SARLU']);
        $notaire = $this->utilisateur(RoleUtilisateur::Notaire);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->post('/dossiers', [
                'type_acte_id' => $this->typeActe('SOC-MOD')->id,
                'societe_id'   => $societe->id,
                'objet'        => 'Transfert du siège social de MICH SARL',
                'notaire_id'   => $notaire->id,
                'donnees'      => [
                    'soc.denomination' => 'MICH SARL',
                    'soc.rccm'         => 'GN-CON-2020-B-0042',
                    'modif.types'      => ['Transfert du siège social'],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('GN-CON-2020-B-0042', $societe->fresh()->rccm_numero);

        // Une fiche de référence qui change sans trace n'est pas acceptable — même exigence que
        // pour la mise à jour d'une fiche client depuis le Répertoire.
        $this->assertDatabaseHas('journal_activites', ['type' => 'modification']);
        $this->assertStringContainsString(
            'complétée au registre',
            Dossier::latest('id')->first()->journal()->where('type', 'modification')->first()->action,
        );
    }
}
