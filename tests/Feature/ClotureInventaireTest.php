<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\RubriqueCloture;
use App\Enums\StatutFormalite;
use App\Models\ClotureVerification;
use App\Models\Courrier;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\Formalite;
use App\Models\Paiement;
use App\Models\Partie;
use App\Models\Recu;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\DossierStepService;
use App\Services\InventaireClotureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Inventaire de clôture et vérification pièce par pièce.
 *
 * Remplace la clôture « configurée par type d'acte » (`ModeleActe.obligatoire_cloture`,
 * supprimée le 2026-08-04) : il n'y a plus rien à déclarer à l'avance, l'inventaire est
 * dérivé de ce que le workflow a produit, et c'est un contrôle humain qui décide.
 */
class ClotureInventaireTest extends TestCase
{
    use RefreshDatabase;

    private User $notaire;
    private Dossier $dossier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notaire = User::factory()->create();
        $this->notaire->syncRoles([RoleUtilisateur::Notaire]);

        $typeActe = TypeActe::create([
            'code' => 'TST-CLO', 'label' => 'Type de test', 'categorie' => 'societe',
        ]);

        $this->dossier = Dossier::create([
            'reference'    => 'TST-2026-0001',
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Expedition,
            'redacteur_id' => User::factory()->create()->id,
            'notaire_id'   => $this->notaire->id,
            'objet'        => 'Dossier de test pour l\'inventaire de clôture',
        ]);
    }

    /** Peuple les six rubriques, une pièce chacune. */
    private function peuplerToutesLesRubriques(): void
    {
        // 1. Actes
        $this->dossier->documents()->create(['nom' => 'Statuts SARLU', 'categorie' => 'acte_principal']);
        // 2. Accord du client
        $this->dossier->documents()->create(['nom' => 'Accord client', 'categorie' => 'accord_client']);
        // 3. Pièces des parties
        $partie = Partie::create(['dossier_id' => $this->dossier->id, 'nom' => 'Ibrahima DIALLO', 'role' => 'associe_unique']);
        $partie->pieces()->create(['nom' => 'CNI', 'categorie' => 'cni', 'est_requis' => true]);
        // 4. Pièces des formalités
        $formalite = Formalite::create([
            'dossier_id' => $this->dossier->id, 'organisme' => 'APIP', 'statut' => StatutFormalite::RetourRecu,
        ]);
        $formalite->pieces()->create(['nom' => 'Récépissé APIP', 'categorie' => 'piece_justificative', 'est_requis' => true]);
        // 5. Courriers
        Courrier::create([
            'reference' => 'COU-2026-0001', 'dossier_id' => $this->dossier->id,
            'redacteur_id' => $this->dossier->redacteur_id,
            'destinataire' => 'APIP', 'objet' => 'Lettre de transmission',
            'type' => 'transmission', 'statut' => 'brouillon',
        ]);
        // 6. Facturation
        $facture  = Facture::create(['dossier_id' => $this->dossier->id, 'note_numero' => 'N-1', 'note_date' => now()]);
        $paiement = Paiement::create([
            'facture_id' => $facture->id, 'date_paiement' => now(), 'montant' => 1000,
            'moyen_paiement' => 'especes',
        ]);
        Recu::create(['paiement_id' => $paiement->id, 'numero' => '2026-0001-01', 'date_emission' => now()]);
    }

    private function inventaire(): InventaireClotureService
    {
        return app(InventaireClotureService::class);
    }

    public function test_linventaire_remonte_toutes_les_rubriques_dans_lordre(): void
    {
        $this->peuplerToutesLesRubriques();

        $rubriques = $this->inventaire()->pour($this->dossier->fresh());

        // `pieces_societe` s'est ajoutée le 2026-08-11 : le dossier constitutif d'une société que
        // l'étude n'a pas constituée. Elle se range après les pièces des parties — ce sont, comme
        // elles, des pièces fournies au dossier et non produites par lui.
        $this->assertSame(
            ['actes', 'accord_client', 'pieces_parties', 'pieces_societe', 'pieces_formalites', 'courriers', 'facturation'],
            $rubriques->pluck('rubrique')->all(),
        );
        // Une pièce par rubrique, sauf `pieces_societe` : ce dossier n'a pas de société rattachée.
        // C'est bien l'ensemble du workflow qui est couvert, et non les seuls documents de niveau
        // dossier comme avant.
        $this->assertSame([1, 1, 1, 0, 1, 1, 1], $rubriques->map(fn ($r) => count($r['pieces']))->all());
        $this->assertSame(6, $this->inventaire()->progression($this->dossier->fresh())['total']);
    }

    public function test_les_rubriques_vides_sont_conservees_dans_linventaire(): void
    {
        // Une rubrique vide est une information (« aucune pièce de formalité »), pas du
        // bruit : l'onglet Clôture doit pouvoir le dire.
        $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $rubriques = $this->inventaire()->pour($this->dossier->fresh());

        $this->assertCount(count(\App\Enums\RubriqueCloture::cases()), $rubriques);
        $this->assertCount(0, $rubriques->firstWhere('rubrique', 'courriers')['pieces']);
        $this->assertCount(0, $rubriques->firstWhere('rubrique', 'pieces_societe')['pieces']);
    }

    public function test_laccord_client_ne_se_confond_pas_avec_les_actes(): void
    {
        $acte   = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $accord = $this->dossier->documents()->create(['nom' => 'Accord', 'categorie' => 'accord_client']);

        $this->assertSame(RubriqueCloture::Actes, RubriqueCloture::pourDocument($acte));
        $this->assertSame(RubriqueCloture::AccordClient, RubriqueCloture::pourDocument($accord));
    }

    public function test_une_categorie_dacte_inconnue_reste_classee_dans_les_actes(): void
    {
        // `categorie` vient de ModeleActe.type_document, saisi librement par
        // l'administrateur (`dnsv`, `rccm`, `attestation`…) : le classement porte sur le
        // type de rattachement, pas sur la catégorie, précisément pour qu'une valeur
        // nouvelle ne disparaisse pas de l'inventaire.
        $doc = $this->dossier->documents()->create(['nom' => 'DNSV', 'categorie' => 'dnsv']);

        $this->assertSame(RubriqueCloture::Actes, RubriqueCloture::pourDocument($doc));
    }

    public function test_une_piece_sans_fichier_figure_quand_meme_a_linventaire(): void
    {
        // Masquer les pièces sans fichier reviendrait à cacher précisément ce qui manque.
        $this->dossier->documents()->create(['nom' => 'Acte non généré', 'categorie' => 'acte_principal']);

        $pieces = $this->inventaire()->pour($this->dossier->fresh())->firstWhere('rubrique', 'actes')['pieces'];

        $this->assertCount(1, $pieces);
        $this->assertFalse($pieces[0]['has_file']);
    }

    public function test_la_cloture_est_refusee_tant_quune_piece_nest_pas_verifiee(): void
    {
        $this->peuplerToutesLesRubriques();
        $dossier = $this->dossier->fresh();

        try {
            app(DossierStepService::class)->avancer($dossier, $this->notaire);
            $this->fail('Le passage à Clôturé aurait dû être refusé.');
        } catch (ValidationException $e) {
            $message = $e->errors()['cloture'][0];
            $this->assertStringContainsString('6 pièce(s)', $message);
            // Le message nomme les rubriques : sur un dossier de vingt pièces, une liste
            // à plat ne dit pas où aller les chercher.
            $this->assertStringContainsString('Actes', $message);
            $this->assertStringContainsString('Pièces des parties', $message);
        }

        $this->assertSame(EtapeDossier::Expedition, $dossier->fresh()->etape);
    }

    public function test_la_cloture_est_acceptee_quand_tout_est_verifie(): void
    {
        $this->peuplerToutesLesRubriques();
        $dossier = $this->dossier->fresh();

        foreach ($this->inventaire()->pour($dossier) as $rubrique) {
            foreach ($rubrique['pieces'] as $piece) {
                ClotureVerification::create([
                    'dossier_id'      => $dossier->id,
                    'verifiable_type' => InventaireClotureService::classePourType($piece['type']),
                    'verifiable_id'   => $piece['id'],
                    'verifie_par_id'  => $this->notaire->id,
                    'verifie_at'      => now(),
                ]);
            }
        }

        $avance = app(DossierStepService::class)->avancer($dossier, $this->notaire);

        $this->assertSame(EtapeDossier::Cloture, $avance->etape);
    }

    public function test_un_dossier_sans_aucune_piece_se_cloture(): void
    {
        // Pas de blocage artificiel : rien à vérifier, rien ne bloque.
        $avance = app(DossierStepService::class)->avancer($this->dossier->fresh(), $this->notaire);

        $this->assertSame(EtapeDossier::Cloture, $avance->etape);
    }

    public function test_cocher_une_piece_enregistre_qui_et_quand(): void
    {
        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'document', 'id' => $doc->id,
            ])
            ->assertRedirect();

        $verification = ClotureVerification::first();
        $this->assertSame($this->notaire->id, $verification->verifie_par_id);
        $this->assertNotNull($verification->verifie_at);
        $this->assertSame(DocumentFichier::class, $verification->verifiable_type);
    }

    public function test_retirer_la_verification_supprime_la_ligne(): void
    {
        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $this->actingAs($this->notaire)->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
            'type' => 'document', 'id' => $doc->id,
        ]);
        $this->assertDatabaseCount('cloture_verifications', 1);

        $this->actingAs($this->notaire)->delete("/dossiers/{$this->dossier->reference}/cloture/verifications", [
            'type' => 'document', 'id' => $doc->id,
        ])->assertRedirect();

        $this->assertDatabaseCount('cloture_verifications', 0);
    }

    public function test_verifier_toute_une_rubrique_coche_ses_pieces(): void
    {
        $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $this->dossier->documents()->create(['nom' => 'DNSV', 'categorie' => 'dnsv']);
        $this->dossier->documents()->create(['nom' => 'Accord', 'categorie' => 'accord_client']);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications/rubrique", ['rubrique' => 'actes'])
            ->assertRedirect();

        // Les deux actes, pas l'accord client : il relève d'une autre rubrique.
        $this->assertDatabaseCount('cloture_verifications', 2);
    }

    public function test_cocher_la_piece_dun_autre_dossier_est_refuse(): void
    {
        // Sans ce contrôle, l'autorisation porterait sur un dossier alors que la coche
        // s'appliquerait à la pièce d'un autre.
        $autre = Dossier::create([
            'reference'    => 'TST-2026-0002',
            'type_acte_id' => $this->dossier->type_acte_id,
            'etape'        => EtapeDossier::Expedition,
            'redacteur_id' => $this->dossier->redacteur_id,
            'notaire_id'   => $this->notaire->id,
            'objet'        => 'Autre dossier de test pour le cloisonnement',
        ]);
        $docAutre = $autre->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'document', 'id' => $docAutre->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('cloture_verifications', 0);
    }

    public function test_un_redacteur_ne_peut_pas_verifier(): void
    {
        $redacteur = User::factory()->create();
        $redacteur->syncRoles([RoleUtilisateur::Clerc]);
        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $this->actingAs($redacteur)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'document', 'id' => $doc->id,
            ])
            ->assertForbidden();
    }

    // ── Restrictions autour de l'étape ───────────────────────────────────────

    public function test_un_dossier_cloture_est_fige(): void
    {
        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $this->dossier->update(['etape' => EtapeDossier::Cloture]);

        // Ni cocher, ni décocher : une fois clôturé, l'inventaire ne bouge plus — c'est
        // exactement l'altération silencieuse qu'une clôture doit interdire.
        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'document', 'id' => $doc->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('cloture_verifications', 0);
    }

    public function test_meme_un_administrateur_ne_peut_pas_toucher_un_dossier_cloture(): void
    {
        // Le gel porte sur l'état du dossier, pas sur le rôle : le raccourci
        // administrateur ne doit pas le contourner.
        $admin = User::factory()->create();
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $this->dossier->update(['etape' => EtapeDossier::Cloture]);

        $this->actingAs($admin)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'document', 'id' => $doc->id,
            ])
            ->assertForbidden();
    }

    public function test_on_ne_peut_pas_verifier_avant_lexpedition(): void
    {
        // La vérification est le contrôle final : elle n'a pas de sens tant que le
        // dossier n'est pas arrivé à l'Expédition. Vaut aussi pour l'administrateur,
        // dont le raccourci précédait le contrôle d'étape.
        $admin = User::factory()->create();
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        $doc = $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $this->dossier->update(['etape' => EtapeDossier::Formalites]);

        foreach ([$this->notaire, $admin] as $utilisateur) {
            $this->actingAs($utilisateur)
                ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                    'type' => 'document', 'id' => $doc->id,
                ])
                ->assertForbidden();
        }
    }

    public function test_verifier_toute_une_rubrique_respecte_aussi_le_gel(): void
    {
        $this->dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $this->dossier->update(['etape' => EtapeDossier::Cloture]);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications/rubrique", ['rubrique' => 'actes'])
            ->assertForbidden();

        $this->assertDatabaseCount('cloture_verifications', 0);
    }

    public function test_un_dossier_cloture_refuse_toute_modification_de_contenu(): void
    {
        $this->dossier->update(['etape' => EtapeDossier::Cloture]);
        $admin = User::factory()->create();
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        // Le gel couvre l'ensemble des abilities mutantes, pas seulement la clôture :
        // sans lui, un administrateur pouvait modifier, régénérer ou supprimer un
        // dossier clos, et un comptable y enregistrer un paiement.
        foreach (['update', 'delete', 'genererDocuments', 'gererFormalites', 'gererFacturation'] as $ability) {
            $this->assertFalse(
                $admin->can($ability, $this->dossier->fresh()),
                "L'ability {$ability} doit être refusée sur un dossier clôturé.",
            );
        }

        // Figer n'est pas masquer : la consultation reste ouverte.
        $this->assertTrue($admin->can('view', $this->dossier->fresh()));
    }

    public function test_un_type_de_piece_inconnu_est_refuse(): void
    {
        // On n'instancie jamais une classe arbitraire fournie par le client : seuls les
        // trois types courts de InventaireClotureService::classesParType() passent.
        //
        // Redirection avec erreurs plutôt que 422 : bootstrap/app.php restreint les
        // réponses JSON aux routes api/* (convention Inertia), une validation échouée
        // renvoie donc toujours l'utilisateur en arrière.
        $this->actingAs($this->notaire)
            ->post("/dossiers/{$this->dossier->reference}/cloture/verifications", [
                'type' => 'App\\Models\\User', 'id' => 1,
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseCount('cloture_verifications', 0);
    }
}
