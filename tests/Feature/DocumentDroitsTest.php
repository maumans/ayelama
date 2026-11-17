<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutRevision;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Revision;
use App\Models\RevisionPoint;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Droits sur un document : supprimer, restaurer, corriger — retours R07, R10 et R19 du 2026-11-16.
 *
 * Avant cette passe, l'écran déduisait ces droits d'un `can.genererDocuments` **global au dossier**
 * et le serveur autorisait `destroy` sur `genererDocuments` quel que soit le document. D'où un
 * bouton « Supprimer » affiché sur les pièces de partie à l'Initialisation, que le serveur refusait
 * en 403. La règle vit désormais dans `DocumentFichierPolicy`, et ce fichier la verrouille.
 */
class DocumentDroitsTest extends TestCase
{
    use RefreshDatabase;

    private User $redacteur;
    private User $certificateur;
    private User $notaire;
    private User $comptable;
    private TypeActe $typeActe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->redacteur = $this->utilisateur(RoleUtilisateur::Clerc);
        $this->certificateur = $this->utilisateur(RoleUtilisateur::Reviseur);
        $this->notaire = $this->utilisateur(RoleUtilisateur::Notaire);
        $this->comptable = $this->utilisateur(RoleUtilisateur::Comptable);

        $this->typeActe = TypeActe::create([
            'code' => 'TST-DOC', 'label' => 'Type de test', 'categorie' => 'societe',
        ]);
    }

    private function utilisateur(RoleUtilisateur $role): User
    {
        $user = User::factory()->create();
        $user->syncRoles([$role]);

        return $user;
    }

    private function dossier(EtapeDossier $etape): Dossier
    {
        return Dossier::create([
            'reference'    => 'TST-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe->id,
            'etape'        => $etape,
            'redacteur_id' => $this->redacteur->id,
            'reviseur_id'  => $this->certificateur->id,
            'notaire_id'   => $this->notaire->id,
            'objet'        => 'Dossier de test pour les droits sur les documents',
        ]);
    }

    private function acte(Dossier $dossier): DocumentFichier
    {
        $acte = $dossier->documents()->create([
            'nom' => 'Statuts', 'categorie' => 'acte_principal', 'statut' => 'a_editer',
        ]);
        $acte->nouvelleVersion(UploadedFile::fake()->create('statuts.docx', 10), 'documents/' . $dossier->reference);

        return $acte->fresh();
    }

    private function pieceDePartie(Dossier $dossier): DocumentFichier
    {
        $partie = Partie::create(['dossier_id' => $dossier->id, 'nom' => 'Ibrahima DIALLO', 'role' => 'associe_unique']);

        return DocumentFichier::create([
            'documentable_type' => Partie::class,
            'documentable_id'   => $partie->id,
            'nom'               => 'CNI',
            'categorie'         => 'piece_justificative',
            'statut'            => 'a_editer',
        ]);
    }

    // ── R10 : la régénération manuelle n'existe plus ──────────────────────────

    public function test_la_route_de_regeneration_manuelle_a_disparu(): void
    {
        // Elle écrasait une correction faite à la main. La régénération automatique après
        // modification du questionnaire, elle, demeure (ClientProjectionService).
        $this->assertFalse(Route::has('documents.regenerer'));
        $this->assertTrue(Route::has('documents.corriger'));
    }

    public function test_update_naccepte_plus_de_fichier(): void
    {
        // Seconde porte vers le même effet que `corriger`, sans journal ni péremption.
        $dossier = $this->dossier(EtapeDossier::Edition);
        $acte = $this->acte($dossier);

        $this->actingAs($this->redacteur)
            ->post("/documents/{$acte->id}/update", ['fichier' => UploadedFile::fake()->create('x.docx', 10)]);

        $this->assertSame(1, $acte->fresh()->versions()->count());
    }

    // ── R07 : supprimer — le droit suit la famille du document ────────────────

    public function test_une_piece_de_partie_se_supprime_a_linitialisation(): void
    {
        // Le défaut mesuré : bouton visible sous `gererPieces`, refus 403 sous `genererDocuments`.
        $piece = $this->pieceDePartie($this->dossier(EtapeDossier::Initialisation));

        $this->assertTrue($this->redacteur->can('supprimer', $piece));
        $this->actingAs($this->redacteur)->delete("/documents/{$piece->id}")->assertRedirect();
        $this->assertDatabaseMissing('document_fichiers', ['id' => $piece->id]);
    }

    public function test_un_acte_ne_se_supprime_qua_ledition_et_la_certification(): void
    {
        foreach (EtapeDossier::cases() as $etape) {
            $acte = $this->acte($this->dossier($etape));
            $attendu = in_array($etape, [EtapeDossier::Edition, EtapeDossier::Revision], true);

            $this->assertSame(
                $attendu,
                $this->redacteur->can('supprimer', $acte),
                "Suppression d'un acte à l'étape {$etape->value}.",
            );
        }
    }

    public function test_ni_le_comptable_ni_le_certificateur_ne_suppriment_un_acte(): void
    {
        $acte = $this->acte($this->dossier(EtapeDossier::Edition));

        $this->assertFalse($this->comptable->can('supprimer', $acte));
        $this->assertFalse($this->certificateur->can('supprimer', $acte));
        $this->actingAs($this->comptable)->delete("/documents/{$acte->id}")->assertForbidden();
    }

    public function test_un_document_signe_ne_se_supprime_ni_ne_se_corrige(): void
    {
        $acte = $this->acte($this->dossier(EtapeDossier::Edition));
        $acte->update(['est_signe_cachete' => true]);

        $this->assertFalse($this->redacteur->can('supprimer', $acte));
        $this->assertFalse($this->redacteur->can('corriger', $acte));
    }

    public function test_les_drapeaux_servis_a_lecran_suivent_la_policy(): void
    {
        $dossier = $this->dossier(EtapeDossier::Edition);
        $this->acte($dossier);

        $this->actingAs($this->comptable)
            ->get("/dossiers/{$dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->where('dossier.documents.0.peut_supprimer', false)
                ->where('dossier.documents.0.peut_corriger', false));

        $this->actingAs($this->redacteur)
            ->get("/dossiers/{$dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->where('dossier.documents.0.peut_supprimer', true)
                ->where('dossier.documents.0.peut_corriger', true));
    }

    public function test_lhistorique_des_versions_nest_plus_expose(): void
    {
        // Retiré le 2026-11-17 à la demande de l'étude : restaurer une version antérieure
        // contredisait le retrait de la régénération (R10). Les versions restent en base.
        foreach (['documents.versions', 'documents.versions.telecharger', 'documents.versions.restaurer'] as $route) {
            $this->assertFalse(Route::has($route), "Route {$route} réapparue.");
        }

        $dossier = $this->dossier(EtapeDossier::Edition);
        $this->acte($dossier);

        $this->actingAs($this->redacteur)
            ->get("/dossiers/{$dossier->reference}")
            ->assertInertia(fn ($page) => $page
                ->missing('dossier.documents.0.url_versions')
                ->missing('dossier.documents.0.peut_restaurer'));
    }

    // ── R19 : corriger — rédacteur (créateur) et certificateur ────────────────

    public function test_le_redacteur_et_le_certificateur_corrigent_aux_bonnes_etapes(): void
    {
        foreach (EtapeDossier::cases() as $etape) {
            $acte = $this->acte($this->dossier($etape));
            $attendu = in_array($etape, [EtapeDossier::Edition, EtapeDossier::Revision], true);

            foreach (['rédacteur' => $this->redacteur, 'certificateur' => $this->certificateur] as $qui => $user) {
                $this->assertSame(
                    $attendu,
                    $user->can('corriger', $acte),
                    "Correction par le {$qui} à l'étape {$etape->value}.",
                );
            }
        }
    }

    public function test_le_comptable_ne_corrige_pas(): void
    {
        $acte = $this->acte($this->dossier(EtapeDossier::Edition));

        $this->assertFalse($this->comptable->can('corriger', $acte));
        $this->actingAs($this->comptable)
            ->post("/documents/{$acte->id}/corriger", ['fichier' => UploadedFile::fake()->create('c.docx', 10)])
            ->assertForbidden();
    }

    public function test_une_correction_ajoute_une_version_et_perime_la_certification(): void
    {
        $dossier = $this->dossier(EtapeDossier::Revision);
        $acte = $this->acte($dossier);
        $revision = Revision::create([
            'dossier_id' => $dossier->id, 'reviseur_id' => $this->certificateur->id, 'statut' => StatutRevision::EnCours,
        ]);
        $point = RevisionPoint::create([
            'revision_id' => $revision->id, 'point_id' => (string) $acte->id, 'etat' => 'a_corriger', 'commentaire' => 'Coquille',
        ]);

        $this->actingAs($this->certificateur)
            ->post("/documents/{$acte->id}/corriger", ['fichier' => UploadedFile::fake()->create('statuts-corriges.docx', 12)])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $acte->refresh();
        $this->assertSame(2, $acte->versions()->count());
        $this->assertSame('upload', $acte->versionActuelle->source);
        // Le certificateur se prononce de nouveau, y compris sur sa propre correction.
        $this->assertTrue($point->fresh()->perime);
        $this->assertDatabaseHas('journal_activites', ['dossier_id' => $dossier->id]);
    }
}
