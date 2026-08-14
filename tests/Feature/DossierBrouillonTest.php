<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\DossierBrouillon;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cycle de vie d'un brouillon de dossier : enregistrer, reprendre, finaliser,
 * purger. Le point sensible est le fichier : un brouillon détient de vraies pièces
 * d'identité avant que le dossier — et donc les Partie auxquelles elles se
 * rattacheront — n'existe.
 */
class DossierBrouillonTest extends TestCase
{
    use RefreshDatabase;

    private User $clerc;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->clerc = User::factory()->create();
        $this->clerc->syncRoles([RoleUtilisateur::Clerc]);
    }

    private function typeActe(): TypeActe
    {
        return TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Constitution SARLU de test',
            'categorie' => 'societe',
        ]);
    }

    private function etat(array $extra = []): string
    {
        return json_encode(array_merge([
            'version'    => 1,
            'step'       => 1,
            'categorie'  => 'societe',
            'objet'      => 'Constitution de la société Faya Distribution SARLU',
            'formValues' => ['soc.denomination' => 'Faya Distribution SARLU'],
        ], $extra));
    }

    public function test_enregistrer_un_brouillon_le_cree_avec_son_etat(): void
    {
        $type = $this->typeActe();

        $reponse = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'etat'         => $this->etat(),
                'type_acte_id' => $type->id,
                'libelle'      => 'Constitution de la société Faya Distribution SARLU',
            ])
            ->assertOk()
            ->assertJsonPath('libelle', 'Constitution de la société Faya Distribution SARLU');

        // Assertion sur le tableau décodé, pas via assertJsonPath : les clés du
        // questionnaire contiennent un point littéral (`soc.denomination`), que la
        // notation par chemin interpréterait comme un niveau d'imbrication.
        $this->assertSame(
            'Faya Distribution SARLU',
            $reponse->json('etat')['formValues']['soc.denomination'],
        );

        $this->assertDatabaseCount('dossier_brouillons', 1);
        $this->assertSame($this->clerc->id, DossierBrouillon::first()->user_id);
    }

    public function test_reenregistrer_met_a_jour_sans_creer_de_doublon(): void
    {
        $type = $this->typeActe();

        $id = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', ['etat' => $this->etat(), 'type_acte_id' => $type->id])
            ->json('id');

        $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'brouillon_id' => $id,
                'etat'         => $this->etat(['objet' => 'Objet corrigé']),
                'type_acte_id' => $type->id,
            ])
            ->assertOk()
            ->assertJsonPath('id', $id)
            ->assertJsonPath('etat.objet', 'Objet corrigé');

        $this->assertDatabaseCount('dossier_brouillons', 1);
    }

    public function test_les_pieces_sont_conservees_sur_le_disque_prive(): void
    {
        $reponse = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'etat'   => $this->etat(),
                'pieces' => ['associe_unique' => ['cni' => UploadedFile::fake()->create('cni.pdf', 40, 'application/pdf')]],
            ])
            ->assertOk();

        $chemin = $reponse->json('etat.piecesBrouillon.associe_unique.cni.chemin');

        $this->assertNotNull($chemin);
        $this->assertSame('cni.pdf', $reponse->json('etat.piecesBrouillon.associe_unique.cni.nom'));
        $this->assertSame(1, $reponse->json('nbPieces'));
        // Disque `local` (privé) et non `public` : une pièce d'identité n'a rien à
        // faire derrière une URL tant qu'elle n'est pas rattachée à un dossier.
        Storage::disk('local')->assertExists($chemin);
    }

    public function test_remplacer_une_piece_supprime_lancien_fichier(): void
    {
        $premier = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'etat'   => $this->etat(),
                'pieces' => ['associe_unique' => ['cni' => UploadedFile::fake()->create('ancien.pdf', 10, 'application/pdf')]],
            ]);

        $id            = $premier->json('id');
        $ancienChemin  = $premier->json('etat.piecesBrouillon.associe_unique.cni.chemin');
        $etatAvecPiece = $premier->json('etat');

        $second = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'brouillon_id' => $id,
                'etat'         => json_encode($etatAvecPiece),
                'pieces'       => ['associe_unique' => ['cni' => UploadedFile::fake()->create('nouveau.pdf', 10, 'application/pdf')]],
            ])
            ->assertOk();

        Storage::disk('local')->assertMissing($ancienChemin);
        Storage::disk('local')->assertExists($second->json('etat.piecesBrouillon.associe_unique.cni.chemin'));
        $this->assertSame('nouveau.pdf', $second->json('etat.piecesBrouillon.associe_unique.cni.nom'));
    }

    public function test_une_piece_retiree_de_letat_est_supprimee_du_disque(): void
    {
        $premier = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'etat'   => $this->etat(),
                'pieces' => ['associe_unique' => ['cni' => UploadedFile::fake()->create('cni.pdf', 10, 'application/pdf')]],
            ]);

        $chemin = $premier->json('etat.piecesBrouillon.associe_unique.cni.chemin');

        // L'assistant renvoie un état sans cette pièce (bouton « Retirer »).
        $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'brouillon_id' => $premier->json('id'),
                'etat'         => $this->etat(['piecesBrouillon' => []]),
            ])
            ->assertOk()
            ->assertJsonPath('nbPieces', 0);

        Storage::disk('local')->assertMissing($chemin);
    }

    public function test_un_brouillon_nest_visible_que_par_son_auteur(): void
    {
        $brouillon = DossierBrouillon::create([
            'user_id' => $this->clerc->id,
            'etat'    => ['objet' => 'Saisie privée'],
        ]);

        // Même un administrateur n'a rien à superviser dans un formulaire à moitié
        // rempli qui n'est pas le sien.
        $admin = User::factory()->create();
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)
            ->delete("/dossiers/brouillons/{$brouillon->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('dossier_brouillons', 1);
    }

    public function test_supprimer_un_brouillon_supprime_aussi_ses_fichiers(): void
    {
        $reponse = $this->actingAs($this->clerc)
            ->post('/dossiers/brouillons', [
                'etat'   => $this->etat(),
                'pieces' => ['associe_unique' => ['cni' => UploadedFile::fake()->create('cni.pdf', 10, 'application/pdf')]],
            ]);

        $chemin = $reponse->json('etat.piecesBrouillon.associe_unique.cni.chemin');

        $this->actingAs($this->clerc)
            ->delete("/dossiers/brouillons/{$reponse->json('id')}")
            ->assertOk();

        $this->assertDatabaseCount('dossier_brouillons', 0);
        Storage::disk('local')->assertMissing($chemin);
    }

    public function test_un_etat_illisible_est_refuse(): void
    {
        $this->actingAs($this->clerc)
            ->postJson('/dossiers/brouillons', ['etat' => 'ceci-nest-pas-du-json'])
            ->assertStatus(422);

        $this->assertDatabaseCount('dossier_brouillons', 0);
    }

    public function test_la_purge_est_en_dry_run_par_defaut(): void
    {
        $brouillon = DossierBrouillon::create([
            'user_id' => $this->clerc->id,
            'etat'    => ['objet' => 'Abandonné'],
            'libelle' => 'Abandonné',
        ]);
        // updated_at forcé après création : Eloquent l'écrase sinon.
        $brouillon->forceFill(['updated_at' => now()->subDays(90)])->saveQuietly();

        $this->artisan('ayelema:brouillons-purger')
            ->expectsOutputToContain('Relancez avec --supprimer')
            ->assertSuccessful();

        // Sur des pièces d'identité, la suppression ne doit jamais être implicite.
        $this->assertDatabaseCount('dossier_brouillons', 1);

        $this->artisan('ayelema:brouillons-purger --supprimer')->assertSuccessful();
        $this->assertDatabaseCount('dossier_brouillons', 0);
    }

    public function test_la_purge_epargne_les_brouillons_recents(): void
    {
        DossierBrouillon::create([
            'user_id' => $this->clerc->id,
            'etat'    => ['objet' => 'En cours'],
        ]);

        $this->artisan('ayelema:brouillons-purger --supprimer')->assertSuccessful();

        $this->assertDatabaseCount('dossier_brouillons', 1);
    }
}
