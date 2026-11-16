<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\DossierStepService;
use App\Support\EmpreinteQuestionnaire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Signature du questionnaire — retour R23, arbitré le 2026-11-16 : à l'écran de l'étude, par le
 * client principal, nouvelle signature exigée si le questionnaire change, papier toujours possible.
 */
class SignatureQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private User $clerc;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->clerc = User::factory()->create();
        $this->clerc->syncRoles([RoleUtilisateur::Clerc]);
    }

    private function dossier(string $code = 'SOC-SARLU', array $donnees = ['soc.denomination' => 'Faya SARLU', 'soc.capital_chiffres' => '1000000']): Dossier
    {
        $type = TypeActe::firstOrCreate(['code' => $code], ['label' => $code, 'categorie' => CategorieActe::Societe]);
        $dossier = Dossier::create([
            'reference' => 'TST-' . fake()->unique()->numerify('####'), 'type_acte_id' => $type->id,
            'etape' => EtapeDossier::Initialisation, 'redacteur_id' => $this->clerc->id, 'objet' => 'Dossier de test signature',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    /** Une vraie image PNG avec un tracé — assez lourde pour ne pas passer pour une toile vide. */
    private function signaturePng(): string
    {
        $img = imagecreatetruecolor(400, 160);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $encre = imagecolorallocate($img, 19, 41, 75);
        mt_srand(7);
        for ($i = 0; $i < 60; $i++) {
            imageline($img, mt_rand(0, 400), mt_rand(0, 160), mt_rand(0, 400), mt_rand(0, 160), $encre);
        }
        ob_start();
        imagepng($img);

        return 'data:image/png;base64,' . base64_encode(ob_get_clean());
    }

    private function signer(Dossier $dossier, array $surcharge = [])
    {
        return $this->actingAs($this->clerc)->post("/dossiers/{$dossier->reference}/accord-client/signer", [
            'signature' => $this->signaturePng(), 'signataire' => 'Mamadou BAH', 'approuve' => '1', ...$surcharge,
        ]);
    }

    public function test_la_signature_produit_un_accord_verrouille_qui_dit_qui_et_sur_quoi(): void
    {
        $dossier = $this->dossier();

        $this->signer($dossier)->assertSessionHasNoErrors();

        $accord = $dossier->fresh()->pieceAccord();
        $this->assertTrue($accord->est_signe_cachete);
        $this->assertSame('ecran', $accord->mode_signature);
        $this->assertSame('Mamadou BAH', $accord->signataire);
        $this->assertSame(EmpreinteQuestionnaire::calculer($dossier->questionnaire->donnees), $accord->empreinte_questionnaire);
        $this->assertSame('signature_ecran', $accord->versionActuelle->source);
        Storage::disk('public')->assertExists($accord->versionActuelle->chemin_fichier);
        $this->assertTrue($dossier->fresh()->accordAcquis());
        $this->assertDatabaseHas('journal_activites', ['dossier_id' => $dossier->id, 'action' => "Questionnaire signé à l'écran par Mamadou BAH"]);
    }

    public function test_une_toile_vide_ou_sans_approbation_est_refusee(): void
    {
        $dossier = $this->dossier();

        $blanc = imagecreatetruecolor(400, 160);
        imagefill($blanc, 0, 0, imagecolorallocate($blanc, 255, 255, 255));
        ob_start();
        imagepng($blanc);
        $vide = 'data:image/png;base64,' . base64_encode(ob_get_clean());

        $this->signer($dossier, ['signature' => $vide])->assertSessionHasErrors('signature');
        $this->signer($dossier, ['signature' => 'data:image/png;base64,pas-une-image'])->assertSessionHasErrors('signature');
        $this->signer($dossier, ['approuve' => null])->assertSessionHasErrors('approuve');
        $this->assertNull($dossier->fresh()->pieceAccord());
    }

    public function test_un_questionnaire_modifie_apres_la_signature_rend_laccord_caduc(): void
    {
        $dossier = $this->dossier();
        $this->signer($dossier);

        $dossier->questionnaire->update(['donnees' => [...$dossier->questionnaire->donnees, 'soc.capital_chiffres' => '2000000']]);
        $dossier = $dossier->fresh();

        $this->assertTrue($dossier->accordCaduc());
        $this->assertFalse($dossier->accordAcquis());

        try {
            app(DossierStepService::class)->avancer($dossier, $this->clerc);
            $this->fail("Un accord caduc doit bloquer la sortie d'Initialisation.");
        } catch (ValidationException $e) {
            $this->assertStringContainsString('faites-le signer de nouveau', $e->errors()['accord_client'][0]);
        }

        // Signer de nouveau rétablit l'accord.
        $this->signer($dossier)->assertSessionHasNoErrors();
        $this->assertTrue($dossier->fresh()->accordAcquis());
    }

    public function test_lempreinte_ne_depend_pas_de_lordre_des_champs(): void
    {
        $this->assertSame(
            EmpreinteQuestionnaire::calculer(['a' => '1', 'b' => ['y' => 2, 'x' => 1]]),
            EmpreinteQuestionnaire::calculer(['b' => ['x' => 1, 'y' => 2], 'a' => '1']),
        );
        $this->assertNotSame(
            EmpreinteQuestionnaire::calculer(['a' => '1']),
            EmpreinteQuestionnaire::calculer(['a' => '2']),
        );
    }

    public function test_laccord_papier_suit_la_meme_regle(): void
    {
        $dossier = $this->dossier();

        $this->actingAs($this->clerc)->post("/dossiers/{$dossier->reference}/accord-client", [
            'fichier' => UploadedFile::fake()->create('accord.pdf', 20, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $accord = $dossier->fresh()->pieceAccord();
        $this->assertSame('papier', $accord->mode_signature);
        $this->assertNotNull($accord->empreinte_questionnaire);

        $dossier->questionnaire->update(['donnees' => ['soc.denomination' => 'Autre nom']]);
        $this->assertTrue($dossier->fresh()->accordCaduc());
    }

    public function test_un_accord_anterieur_sans_empreinte_reste_valable(): void
    {
        // On ne sait pas sur quel contenu il portait : il ne doit pas immobiliser le dossier.
        $dossier = $this->dossier();
        $dossier->documents()->create([
            'nom' => 'Accord client', 'categorie' => 'accord_client', 'statut' => 'edite',
            'est_signe_cachete' => true, 'signe_cachete_at' => now()->subMonth(),
        ]);
        $dossier->questionnaire->update(['donnees' => ['soc.denomination' => 'Modifié depuis']]);

        $this->assertFalse($dossier->fresh()->accordCaduc());
        $this->assertTrue($dossier->fresh()->accordAcquis());
    }

    public function test_une_decision_dassemblee_ne_se_signe_pas_a_lecran(): void
    {
        // Une modification de statuts attend la décision écrite des associés.
        $this->signer($this->dossier('SOC-MOD'))->assertSessionHasErrors('signature');
    }

    public function test_seul_qui_peut_modifier_le_questionnaire_fait_signer(): void
    {
        $comptable = User::factory()->create();
        $comptable->syncRoles([RoleUtilisateur::Comptable]);
        $dossier = $this->dossier();

        $this->actingAs($comptable)->post("/dossiers/{$dossier->reference}/accord-client/signer", [
            'signature' => $this->signaturePng(), 'signataire' => 'X', 'approuve' => '1',
        ])->assertForbidden();
    }
}
