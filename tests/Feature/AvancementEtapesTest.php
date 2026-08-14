<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutRevision;
use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\Revision;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cohérence entre les prérequis appliqués par le serveur
 * (DossierStepService::verifierPrerequis) et l'indicateur `peutAvancer` exposé à la
 * liste des dossiers, qui pilote l'état du bouton « Avancer ».
 *
 * Les deux sont deux `match` distincts sur EtapeDossier : ils peuvent diverger en
 * silence. C'est ce qui s'était produit à l'étape Expédition, où un
 * `default => true` laissait le bouton actif alors que le serveur exigeait des
 * documents signés/cachetés — l'utilisateur cliquait, l'action échouait.
 */
class AvancementEtapesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->syncRoles([RoleUtilisateur::Administrateur]);
    }

    private function dossier(EtapeDossier $etape, array $attrs = []): Dossier
    {
        // Catégorie volontairement NON `societe` : les règles légales de constitution
        // (capital minimum, commissaire aux comptes, capacité juridique — CR juillet 2026)
        // sont bloquantes et ne s'appliquent qu'aux sociétés. Ces tests portent sur les
        // transitions d'étapes ; ReglesSocieteTest couvre les règles elles-mêmes.
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'procuration',
        ]);

        return Dossier::create(array_merge([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => $etape,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => "Dossier de test pour l'avancement des étapes",
        ], $attrs));
    }

    /** Valeur de `peutAvancer` telle que la liste la reçoit. */
    private function peutAvancer(Dossier $dossier): bool
    {
        $ligne = collect(
            $this->actingAs($this->admin)
                ->get('/dossiers')
                ->viewData('page')['props']['dossiers']['data']
        )->firstWhere('reference', $dossier->reference);

        $this->assertNotNull($ligne, 'Le dossier doit apparaître dans la liste.');

        return $ligne['peutAvancer'];
    }

    public function test_toutes_les_etapes_sont_couvertes_par_les_deux_match(): void
    {
        // Garde-fou de structure : les deux `match` sont exhaustifs (aucune branche
        // `default`). Traverser chaque étape prouve qu'aucun cas ne lève
        // UnhandledMatchError — et qu'une étape ajoutée sans décision fera échouer
        // ce test au lieu de passer inaperçue.
        foreach (EtapeDossier::cases() as $etape) {
            $dossier = $this->dossier($etape);
            $this->peutAvancer($dossier);
            $this->assertTrue(true, "Étape {$etape->value} traitée.");
        }
    }

    // ── Initialisation → Édition ─────────────────────────────────────────────
    // Étape réintroduite le 2026-08-04 : la création déposait le dossier directement en
    // Édition, et verifierEdition() y mélangeait « constituer le dossier » et « produire
    // les actes ».

    public function test_un_dossier_neuf_nait_en_initialisation(): void
    {
        $clerc = User::factory()->create();
        $clerc->syncRoles([RoleUtilisateur::Clerc]);
        $notaire = User::factory()->create();
        $notaire->syncRoles([RoleUtilisateur::Notaire]);
        $reviseur = User::factory()->create();
        $reviseur->syncRoles([RoleUtilisateur::Reviseur]);

        $typeActe = TypeActe::create(['code' => 'TST-NEUF', 'label' => 'Type de test', 'categorie' => 'procuration']);

        $reponse = $this->actingAs($clerc)->post('/dossiers', [
            'type_acte_id' => $typeActe->id,
            'objet'        => "Constitution de société pour test d'initialisation",
            'notaire_id'   => $notaire->id,
            'reviseur_id'  => $reviseur->id,
        ]);

        $dossier = Dossier::latest('id')->first();
        $this->assertSame(EtapeDossier::Initialisation, $dossier->etape);
        // Et on atterrit sur l'onglet Informations, où se trouve le travail de l'étape.
        $reponse->assertRedirectContains('tab=informations');
    }

    public function test_linitialisation_bloque_sans_accord_client(): void
    {
        $dossier = $this->dossier(EtapeDossier::Initialisation, [
            'notaire_id'  => $this->admin->id,
            'reviseur_id' => $this->admin->id,
        ]);

        $this->assertFalse($this->peutAvancer($dossier));

        $this->actingAs($this->admin)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasErrors('accord_client');

        $this->assertSame(EtapeDossier::Initialisation, $dossier->fresh()->etape);
    }

    public function test_linitialisation_avance_et_genere_les_actes_a_ce_moment_la(): void
    {
        $dossier = $this->dossier(EtapeDossier::Initialisation, [
            'notaire_id'  => $this->admin->id,
            'reviseur_id' => $this->admin->id,
        ]);
        $this->deposerAccordClient($dossier);

        // Point clé : aucun acte avant le passage — ils ne sont plus générés à la création.
        $this->assertCount(1, $dossier->fresh()->documents, "Seul l'accord client doit exister.");
        $this->assertTrue($this->peutAvancer($dossier->fresh()));

        $this->actingAs($this->admin)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasNoErrors();

        $this->assertSame(EtapeDossier::Edition, $dossier->fresh()->etape);
    }

    public function test_un_acte_corrige_nest_pas_ecrase_en_repassant_par_ledition(): void
    {
        // Un renvoi en correction ramène le dossier en Édition, donc repasse par la
        // génération : elle ne doit pas écraser un acte retravaillé à la main.
        $dossier = $this->dossier(EtapeDossier::Initialisation, [
            'notaire_id'  => $this->admin->id,
            'reviseur_id' => $this->admin->id,
        ]);
        $this->deposerAccordClient($dossier);
        $dossier->documents()->create(['nom' => 'Statuts SARLU', 'categorie' => 'acte_principal']);

        $this->actingAs($this->admin)->post("/dossiers/{$dossier->reference}/avancer");

        $statuts = $dossier->fresh()->documents->where('nom', 'Statuts SARLU');
        $this->assertCount(1, $statuts, "L'acte existant ne doit pas être dupliqué ni régénéré.");
    }

    /** Dépose un accord client signé, prérequis bloquant de l'Initialisation. */
    private function deposerAccordClient(Dossier $dossier): void
    {
        $dossier->documents()->create([
            'nom'               => 'Accord client',
            'categorie'         => 'accord_client',
            'est_signe_cachete' => true,
            'signe_cachete_at'  => now(),
        ]);
    }

    // ── Expédition → Clôturé ─────────────────────────────────────────────────
    // La règle a changé le 2026-08-04 : ce n'était plus « tout document configuré
    // obligatoire doit être signé/cacheté » (configuration supprimée avec
    // ModeleActe.obligatoire_cloture) mais « toutes les pièces de l'inventaire doivent
    // avoir été vérifiées ». Le détail de l'inventaire est couvert par
    // ClotureInventaireTest ; ici on vérifie la cohérence serveur ↔ bouton de la liste.

    public function test_expedition_sans_aucune_piece_peut_avancer(): void
    {
        $dossier = $this->dossier(EtapeDossier::Expedition);

        $this->assertTrue($this->peutAvancer($dossier));
    }

    public function test_expedition_avec_une_piece_non_verifiee_ne_peut_pas_avancer(): void
    {
        // Le cas de la divergence, transposé à la nouvelle règle : le bouton de la liste
        // et le serveur doivent dire la même chose.
        $dossier = $this->dossier(EtapeDossier::Expedition);
        $dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $this->assertFalse($this->peutAvancer($dossier));

        $this->actingAs($this->admin)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasErrors('cloture');

        $this->assertSame(EtapeDossier::Expedition, $dossier->fresh()->etape);
    }

    public function test_un_courrier_non_verifie_bloque_aussi(): void
    {
        // Les courriers entrent dans l'inventaire au même titre que les actes — ils ne
        // dépendent plus d'un marquage « obligatoire » sur leur modèle.
        $dossier = $this->dossier(EtapeDossier::Expedition);
        Courrier::create([
            'reference'    => 'COU-2026-9001',
            'dossier_id'   => $dossier->id,
            'redacteur_id' => $this->admin->id,
            'destinataire' => 'APIP',
            'objet'        => 'Lettre de transmission APIP',
            'type'         => 'transmission',
            'statut'       => 'brouillon',
        ]);

        $this->assertFalse($this->peutAvancer($dossier));

        $this->actingAs($this->admin)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasErrors('cloture');
    }

    public function test_une_piece_verifiee_ne_bloque_plus(): void
    {
        $dossier = $this->dossier(EtapeDossier::Expedition);
        $document = $dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        \App\Models\ClotureVerification::create([
            'dossier_id'      => $dossier->id,
            'verifiable_type' => \App\Models\DocumentFichier::class,
            'verifiable_id'   => $document->id,
            'verifie_par_id'  => $this->admin->id,
            'verifie_at'      => now(),
        ]);

        $this->assertTrue($this->peutAvancer($dossier->fresh()));

        $this->actingAs($this->admin)
            ->post("/dossiers/{$dossier->reference}/avancer")
            ->assertSessionHasNoErrors();

        $this->assertSame(EtapeDossier::Cloture, $dossier->fresh()->etape);
    }

    public function test_un_dossier_cloture_ne_peut_pas_avancer(): void
    {
        // `default => true` répondait « oui » sur un dossier clôturé. Le bouton
        // n'était pas rendu côté liste, mais la donnée exposée était fausse.
        $dossier = $this->dossier(EtapeDossier::Cloture);

        $this->assertFalse($this->peutAvancer($dossier));
    }

    public function test_certification_non_validee_ne_peut_pas_avancer(): void
    {
        $dossier = $this->dossier(EtapeDossier::Revision);
        Revision::create([
            'dossier_id' => $dossier->id,
            'statut'     => StatutRevision::EnCours,
        ]);

        $this->assertFalse($this->peutAvancer($dossier));

        $dossier->revision->update(['statut' => StatutRevision::Valide]);
        $this->assertTrue($this->peutAvancer($dossier->fresh()));
    }

    public function test_revision_validee_compare_le_cas_denum_et_non_sa_chaine(): void
    {
        $dossier = $this->dossier(EtapeDossier::Revision);
        Revision::create(['dossier_id' => $dossier->id, 'statut' => StatutRevision::Valide]);

        $this->assertTrue($dossier->fresh()->revisionValidee());

        $dossier->revision->update(['statut' => StatutRevision::Renvoye]);
        $this->assertFalse($dossier->fresh()->revisionValidee());
    }

    public function test_signature_exige_les_deux_dates(): void
    {
        $dossier = $this->dossier(EtapeDossier::Signature);
        $this->assertFalse($this->peutAvancer($dossier));

        $dossier->update(['date_signature_client' => now()]);
        $this->assertFalse($this->peutAvancer($dossier->fresh()));

        $dossier->update(['date_signature_notaire' => now()]);
        $this->assertTrue($this->peutAvancer($dossier->fresh()));
    }
}
