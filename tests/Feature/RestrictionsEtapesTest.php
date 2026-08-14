<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Restrictions d'étape : chaque action n'est possible qu'au moment où elle a du sens.
 *
 * Plusieurs abilities encodaient **qui** peut agir mais pas **quand** — au point que le
 * questionnaire restait modifiable jusqu'à l'Expédition, alors que le modifier régénère
 * les actes et vide donc la certification de son sens. Un test par constat de l'audit du
 * 2026-08-04, pour qu'une régression soit détectée nommément.
 */
class RestrictionsEtapesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $notaire;
    private TypeActe $typeActe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleUtilisateur::Administrateur]);

        $this->notaire = User::factory()->create();
        $this->notaire->syncRoles([RoleUtilisateur::Notaire]);

        $this->typeActe = TypeActe::create([
            'code' => 'TST-RES', 'label' => 'Type de test', 'categorie' => 'societe',
        ]);
    }

    private function dossier(EtapeDossier $etape): Dossier
    {
        return Dossier::create([
            'reference'    => 'TST-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe->id,
            'etape'        => $etape,
            'redacteur_id' => $this->notaire->id,
            'notaire_id'   => $this->notaire->id,
            'objet'        => 'Dossier de test pour les restrictions d\'étape',
        ]);
    }

    /** Étapes ouvertes autres que celle passée en argument. */
    /** @param EtapeDossier|EtapeDossier[] $sauf */
    private function autresEtapesOuvertes(EtapeDossier|array $sauf): array
    {
        $sauf = is_array($sauf) ? $sauf : [$sauf];

        return array_values(array_filter(
            EtapeDossier::cases(),
            fn (EtapeDossier $e) => !in_array($e, $sauf, true) && $e !== EtapeDossier::Cloture,
        ));
    }

    // ── A. Le questionnaire n'est modifiable qu'à l'Initialisation ────────────
    // Étape réintroduite le 2026-08-04 : constituer le dossier (questionnaire, pièces,
    // accord client) et produire les actes sont deux métiers distincts.

    public function test_le_questionnaire_est_modifiable_en_initialisation(): void
    {
        $dossier = $this->dossier(EtapeDossier::Initialisation);

        $this->assertTrue($this->notaire->can('modifierQuestionnaire', $dossier));
    }

    public function test_le_questionnaire_nest_plus_modifiable_apres_linitialisation(): void
    {
        // Le trou le plus grave de l'audit : updateQuestionnaire() régénère les actes.
        // Modifiable en Signature/Formalités/Expédition, une certification validée
        // pouvait porter sur un contenu réécrit après coup. L'Édition est désormais
        // fermée aussi : elle ne porte plus que les actes.
        foreach ($this->autresEtapesOuvertes(EtapeDossier::Initialisation) as $etape) {
            $dossier = $this->dossier($etape);

            $this->assertFalse(
                $this->notaire->can('modifierQuestionnaire', $dossier),
                "Le questionnaire ne doit pas être modifiable à l'étape {$etape->value}.",
            );

            $this->actingAs($this->notaire)
                ->patch("/dossiers/{$dossier->reference}/questionnaire", ['donnees' => ['soc.denomination' => 'Piratée']])
                ->assertForbidden();
        }
    }

    public function test_meme_un_administrateur_ne_modifie_pas_le_questionnaire_hors_edition(): void
    {
        // Le raccourci administrateur précédait le contrôle d'étape dans plusieurs
        // abilities : il ne doit pas rouvrir ce que l'étape ferme.
        $dossier = $this->dossier(EtapeDossier::Signature);

        $this->assertFalse($this->admin->can('modifierQuestionnaire', $dossier));

        $this->actingAs($this->admin)
            ->patch("/dossiers/{$dossier->reference}/questionnaire", ['donnees' => []])
            ->assertForbidden();
    }

    public function test_les_parties_ne_sont_plus_modifiables_apres_ledition(): void
    {
        $dossier = $this->dossier(EtapeDossier::Formalites);
        $partie  = Partie::create(['dossier_id' => $dossier->id, 'nom' => 'Ibrahima DIALLO', 'role' => 'associe_unique']);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/parties", ['nom' => 'Ajout tardif', 'role' => 'temoin'])
            ->assertForbidden();

        $this->actingAs($this->notaire)
            ->delete("/parties/{$partie->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('parties', 1);
    }

    public function test_laccord_client_ne_peut_plus_etre_remplace_apres_ledition(): void
    {
        $dossier = $this->dossier(EtapeDossier::Formalites);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/accord-client", [
                'fichier' => \Illuminate\Http\UploadedFile::fake()->create('faux-accord.pdf', 10, 'application/pdf'),
            ])
            ->assertForbidden();
    }

    // ── B. Les dates de signature ────────────────────────────────────────────

    public function test_les_signatures_sont_enregistrables_a_letape_signature(): void
    {
        $dossier = $this->dossier(EtapeDossier::Signature);

        $this->actingAs($this->notaire)
            ->patch("/dossiers/{$dossier->reference}/signatures", [
                'date_signature_client' => '2026-08-01',
            ])
            ->assertRedirect();

        $this->assertNotNull($dossier->fresh()->date_signature_client);
        // Une date de signature est un fait daté : elle doit laisser une trace.
        $this->assertDatabaseHas('journal_activites', ['dossier_id' => $dossier->id, 'type' => 'signature']);
    }

    public function test_les_signatures_ne_sont_pas_enregistrables_aux_autres_etapes(): void
    {
        foreach ($this->autresEtapesOuvertes(EtapeDossier::Signature) as $etape) {
            $dossier = $this->dossier($etape);

            $this->actingAs($this->notaire)
                ->patch("/dossiers/{$dossier->reference}/signatures", ['date_signature_client' => '2026-08-01'])
                ->assertForbidden();

            $this->assertNull($dossier->fresh()->date_signature_client);
        }
    }

    public function test_une_date_de_signature_ne_peut_plus_etre_effacee_par_la_mise_a_jour_generique(): void
    {
        // Le formaliste pouvait vider les dates d'un dossier déjà passé en Formalités :
        // les champs ne font plus partie de UpdateDossierRequest.
        $dossier = $this->dossier(EtapeDossier::Formalites);
        $dossier->update(['date_signature_client' => '2026-07-15', 'date_signature_notaire' => '2026-07-16']);

        $this->actingAs($this->notaire)
            ->patch("/dossiers/{$dossier->reference}", [
                'date_signature_client'  => null,
                'date_signature_notaire' => null,
                'notes'                  => 'Tentative de remise à zéro',
            ])
            ->assertRedirect();

        $frais = $dossier->fresh();
        $this->assertNotNull($frais->date_signature_client);
        $this->assertNotNull($frais->date_signature_notaire);
        // Le reste de la requête passe normalement : seuls ces deux champs sont ignorés.
        $this->assertSame('Tentative de remise à zéro', $frais->notes);
    }

    // ── C. L'étape s'applique aussi à l'administrateur ────────────────────────

    public function test_la_suppression_reste_limitee_aux_deux_premieres_etapes(): void
    {
        // Un dossier peut être abandonné tant qu'il n'est pas certifié ; au-delà il est
        // engagé auprès d'un organisme.
        foreach ([EtapeDossier::Initialisation, EtapeDossier::Edition] as $etape) {
            $this->assertTrue($this->admin->can('delete', $this->dossier($etape)));
        }

        foreach ($this->autresEtapesOuvertes([EtapeDossier::Initialisation, EtapeDossier::Edition]) as $etape) {
            $this->assertFalse(
                $this->admin->can('delete', $this->dossier($etape)),
                "Un dossier à l'étape {$etape->value} ne doit pas être supprimable.",
            );
        }
    }

    public function test_la_generation_dactes_reste_limitee_a_ledition_et_la_certification(): void
    {
        foreach ([EtapeDossier::Edition, EtapeDossier::Revision] as $etape) {
            $this->assertTrue($this->admin->can('genererDocuments', $this->dossier($etape)));
        }

        foreach ([EtapeDossier::Signature, EtapeDossier::Formalites, EtapeDossier::Expedition] as $etape) {
            $this->assertFalse(
                $this->admin->can('genererDocuments', $this->dossier($etape)),
                "Régénérer un acte à l'étape {$etape->value} contournerait la certification.",
            );
        }
    }

    public function test_update_reste_autorise_a_toutes_les_etapes_ouvertes(): void
    {
        // Non-régression : `update` couvre les informations générales, légitimes à chaque
        // étape. Seul le gel d'un dossier clôturé s'y applique — ne pas avoir uniformisé
        // mécaniquement était le point délicat de cette passe.
        foreach ($this->autresEtapesOuvertes(EtapeDossier::Cloture) as $etape) {
            $this->assertTrue(
                $this->notaire->can('update', $this->dossier($etape)),
                "Les informations générales doivent rester modifiables à l'étape {$etape->value}.",
            );
        }

        $this->assertFalse($this->notaire->can('update', $this->dossier(EtapeDossier::Cloture)));
    }

    // ── D. Fiches clients ────────────────────────────────────────────────────

    public function test_un_formaliste_ne_peut_pas_creer_ni_modifier_une_fiche_client(): void
    {
        // `ClientController` n'avait aucune autorisation, alors que modifier une fiche
        // régénère les actes de tous les dossiers liés (ClientProjectionService).
        $formaliste = User::factory()->create();
        $formaliste->syncRoles([RoleUtilisateur::Formaliste]);

        $client = Client::create(['type' => 'physique', 'prenom_nom' => 'Aicha BALDE']);

        $this->actingAs($formaliste)
            ->postJson('/clients', ['type' => 'physique', 'prenom_nom' => 'Nouveau'])
            ->assertForbidden();

        $this->actingAs($formaliste)
            ->patchJson("/clients/{$client->id}", ['type' => 'physique', 'prenom_nom' => 'Renommée'])
            ->assertForbidden();

        $this->assertSame('Aicha BALDE', $client->fresh()->prenom_nom);
    }

    public function test_un_clerc_peut_gerer_les_fiches_clients(): void
    {
        $clerc = User::factory()->create();
        $clerc->syncRoles([RoleUtilisateur::Clerc]);

        $this->actingAs($clerc)
            ->postJson('/clients', ['type' => 'physique', 'prenom_nom' => 'Mamadou SOW'])
            ->assertStatus(201);
    }

    // ── E. Cloisonnement des courriers ───────────────────────────────────────

    public function test_un_utilisateur_ne_voit_pas_les_courriers_dun_dossier_tiers(): void
    {
        $dossierTiers = $this->dossier(EtapeDossier::Expedition);
        Courrier::create([
            'reference' => 'COU-2026-7777', 'dossier_id' => $dossierTiers->id,
            'redacteur_id' => $this->notaire->id, 'destinataire' => 'APIP',
            'objet' => 'Courrier confidentiel', 'type' => 'transmission', 'statut' => 'brouillon',
        ]);

        // Un formaliste actif mais assigné à aucun dossier : la liste ne filtrait pas par
        // Dossier::visiblePar(), il voyait donc les courriers de tout l'office.
        $etranger = User::factory()->create();
        $etranger->syncRoles([RoleUtilisateur::Formaliste]);

        $props = $this->actingAs($etranger)->get('/courriers')->viewData('page')['props'];

        $this->assertCount(0, $props['courriers']['data']);
        // Les compteurs doivent porter sur le même périmètre que la liste.
        $this->assertSame(0, $props['stats']['total']);
    }

    public function test_le_notaire_assigne_voit_bien_les_courriers_de_son_dossier(): void
    {
        $dossier = $this->dossier(EtapeDossier::Expedition);
        Courrier::create([
            'reference' => 'COU-2026-8888', 'dossier_id' => $dossier->id,
            'redacteur_id' => $this->notaire->id, 'destinataire' => 'APIP',
            'objet' => 'Lettre de transmission', 'type' => 'transmission', 'statut' => 'brouillon',
        ]);

        $props = $this->actingAs($this->notaire)->get('/courriers')->viewData('page')['props'];

        $this->assertCount(1, $props['courriers']['data']);
        $this->assertSame(1, $props['stats']['total']);
    }

    // ── Garde-fou de structure ───────────────────────────────────────────────

    public function test_chaque_etape_est_couverte_par_au_moins_une_restriction(): void
    {
        // Si une étape est ajoutée à EtapeDossier sans qu'aucune ability mutante ne la
        // refuse, elle serait un trou béant : tout y serait permis.
        $abilites = ['modifierQuestionnaire', 'enregistrerSignatures', 'genererDocuments',
                     'gererFormalites', 'cloturerDocuments', 'genererCourriers', 'delete'];

        foreach (EtapeDossier::cases() as $etape) {
            $dossier = $this->dossier($etape);
            $refusees = array_filter($abilites, fn ($a) => !$this->admin->can($a, $dossier));

            $this->assertNotEmpty(
                $refusees,
                "L'étape {$etape->value} n'est refusée par aucune ability : elle autoriserait tout.",
            );
        }
    }
}
