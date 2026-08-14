<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutRevision;
use App\Models\Dossier;
use App\Models\Revision;
use App\Models\RevisionPoint;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validation d'une certification.
 *
 * Deux défauts constatés en usage réel sur SOC-2026-0010 :
 *
 * 1. **L'accord client était compté comme un acte à certifier.** `tousEvalues()` comparait
 *    les points évalués au nombre **total** de documents du dossier, accord signé du client
 *    inclus — alors que la grille n'a de point que pour les actes. La certification devenait
 *    arithmétiquement invalidable : 5 points évalués contre 6 documents comptés.
 * 2. **Un état incomplet renvoyait « Accès refusé » (403).** `RevisionPolicy::valider()`
 *    mêlait la permission (rôle, étape) aux préconditions d'état (grille complète) : un
 *    administrateur ayant tous les droits recevait un 403 sans savoir ce qui manquait.
 */
class CertificationValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $notaire;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notaire = User::factory()->create();
        $this->notaire->syncRoles([RoleUtilisateur::Administrateur]);
    }

    /**
     * Dossier en certification avec `$nbActes` actes, un accord client, et une grille dont
     * `$evalues` points sont marqués conformes.
     */
    private function dossierEnCertification(int $nbActes = 2, int $evalues = 2, int $nonConformes = 0): Dossier
    {
        $typeActe = TypeActe::firstOrCreate(
            ['code' => 'TST-CERT'],
            ['label' => 'Type de test', 'categorie' => 'procuration'],
        );

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Revision,
            'redacteur_id' => User::factory()->create()->id,
            'notaire_id'   => $this->notaire->id,
            'reviseur_id'  => $this->notaire->id,
            'objet'        => 'Dossier de test pour la validation de certification',
        ]);

        // L'accord client : un document du dossier, mais pas un acte à certifier.
        $dossier->documents()->create([
            'nom' => 'Accord client', 'categorie' => 'accord_client',
            'est_signe_cachete' => true, 'signe_cachete_at' => now(),
        ]);

        $revision = Revision::create([
            'dossier_id'  => $dossier->id,
            'reviseur_id' => $this->notaire->id,
            'statut'      => StatutRevision::EnCours,
        ]);

        for ($i = 1; $i <= $nbActes; $i++) {
            $acte = $dossier->documents()->create(['nom' => "Acte {$i}", 'categorie' => 'acte_principal']);

            if ($i <= $evalues) {
                RevisionPoint::create([
                    'revision_id' => $revision->id,
                    'point_id'    => (string) $acte->id,
                    'etat'        => $i <= $nonConformes ? 'a_corriger' : 'ok',
                    'commentaire' => $i <= $nonConformes ? 'À revoir' : null,
                    'perime'      => false,
                ]);
            }
        }

        return $dossier->fresh();
    }

    public function test_laccord_client_nest_pas_compte_parmi_les_actes_a_certifier(): void
    {
        $dossier = $this->dossierEnCertification(nbActes: 2, evalues: 2);

        // 3 documents au total, mais 2 actes seulement.
        $this->assertSame(3, $dossier->documents()->count());
        $this->assertSame(2, $dossier->revision->documentsACertifier()->count());
        $this->assertTrue($dossier->revision->tousEvalues());
        $this->assertTrue($dossier->revision->estValidable());
    }

    public function test_la_grille_naffiche_pas_laccord_client_comme_point_a_certifier(): void
    {
        // Symétrie indispensable : la grille affichait un point de plus qu'elle n'en
        // attendait, rendant la validation inatteignable.
        $dossier = $this->dossierEnCertification(nbActes: 2, evalues: 0);

        $documents = $this->actingAs($this->notaire)
            ->get("/dossiers/{$dossier->reference}/revision")
            ->viewData('page')['props']['documents'];

        $this->assertCount(2, $documents);
        $this->assertNotContains('Accord client', collect($documents)->pluck('nom')->all());
    }

    public function test_une_certification_complete_est_validee_et_le_dossier_avance(): void
    {
        $dossier = $this->dossierEnCertification(nbActes: 2, evalues: 2);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/revision/valider")
            ->assertSessionHasNoErrors();

        $this->assertSame(StatutRevision::Valide, $dossier->fresh()->revision->statut);
        $this->assertSame(EtapeDossier::Signature, $dossier->fresh()->etape);
    }

    public function test_une_grille_incomplete_explique_ce_qui_manque_au_lieu_dun_403(): void
    {
        $dossier = $this->dossierEnCertification(nbActes: 3, evalues: 1);

        $reponse = $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/revision/valider");

        // Ni 403 ni « Accès refusé » : l'utilisateur a les droits, c'est l'état qui bloque.
        $reponse->assertSessionHasErrors('revision');
        $this->assertStringContainsString(
            "2 acte(s) sur 3 n'ont pas encore été évalués",
            session('errors')->get('revision')[0],
        );
    }

    public function test_un_acte_a_corriger_empeche_la_validation_avec_un_message_clair(): void
    {
        $dossier = $this->dossierEnCertification(nbActes: 2, evalues: 2, nonConformes: 1);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/revision/valider")
            ->assertSessionHasErrors('revision');

        $this->assertStringContainsString('à corriger', session('errors')->get('revision')[0]);
        $this->assertSame(EtapeDossier::Revision, $dossier->fresh()->etape);
    }

    public function test_un_dossier_sans_acte_explique_quil_faut_les_generer(): void
    {
        $dossier = $this->dossierEnCertification(nbActes: 0, evalues: 0);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/revision/valider")
            ->assertSessionHasErrors('revision');

        $this->assertStringContainsString('Aucun acte à certifier', session('errors')->get('revision')[0]);
    }

    public function test_un_renvoi_sans_point_a_corriger_est_refuse(): void
    {
        // Un renvoi doit dire ce qui est à reprendre.
        $dossier = $this->dossierEnCertification(nbActes: 2, evalues: 2);

        $this->actingAs($this->notaire)
            ->post("/dossiers/{$dossier->reference}/revision/renvoyer", ['motif' => 'Sans raison'])
            ->assertSessionHasErrors('revision');

        $this->assertSame(EtapeDossier::Revision, $dossier->fresh()->etape);
    }

    public function test_la_sauvegarde_preliminaire_naffiche_pas_de_message_de_succes(): void
    {
        // `prelude` : sans ce drapeau, « Grille de certification sauvegardée » s'affichait
        // même quand la validation qui suit échouait — un succès trompeur.
        $dossier = $this->dossierEnCertification(nbActes: 1, evalues: 0);
        $acte    = $dossier->documents->firstWhere('categorie', 'acte_principal');

        $this->actingAs($this->notaire)
            ->put("/dossiers/{$dossier->reference}/revision", [
                'prelude' => true,
                'points'  => [(string) $acte->id => ['etat' => 'ok']],
            ])
            ->assertSessionMissing('success');

        // Sans le drapeau, le message reste affiché : la sauvegarde manuelle doit se voir.
        $this->actingAs($this->notaire)
            ->put("/dossiers/{$dossier->reference}/revision", [
                'points' => [(string) $acte->id => ['etat' => 'ok']],
            ])
            ->assertSessionHas('success', 'Grille de certification sauvegardée.');
    }

    public function test_un_redacteur_ne_peut_pas_valider(): void
    {
        // La permission reste vérifiée par la policy : seul l'état en est sorti.
        $redacteur = User::factory()->create();
        $redacteur->syncRoles([RoleUtilisateur::Clerc]);
        $dossier = $this->dossierEnCertification(nbActes: 1, evalues: 1);

        $this->actingAs($redacteur)
            ->post("/dossiers/{$dossier->reference}/revision/valider")
            ->assertForbidden();
    }
}
