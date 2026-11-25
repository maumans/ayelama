<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use ZipArchive;

/**
 * Actes de constitution : dénomination en MAJUSCULES et en gras, siège et sigle en gras —
 * retour R15 (2026-11-16), appliqué à la génération (GabaritWord) et non fichier par fichier.
 */
class GrasConstitutionTest extends TestCase
{
    use RefreshDatabase;

    private function generer(string $codeType, string $reference): string
    {
        Storage::fake('local');

        // Un run qui mêle un libellé et la balise, en 22 pts : le libellé doit rester maigre, la
        // valeur passer en gras **sans perdre sa taille**.
        $word = new PhpWord();
        $word->addSection()->addText(
            'Dénomination : ${soc.denomination} — Sigle : ${soc.sigle} — Siège : ${soc.siege_ville} — Durée : ${soc.duree}',
            ['size' => 22],
        );
        $gabarit = Storage::disk('local')->path('modeles/test-gras.docx');
        File::ensureDirectoryExists(dirname($gabarit));
        $word->save($gabarit);

        $user = User::factory()->create();
        $type = TypeActe::firstOrCreate(['code' => $codeType], ['label' => $codeType, 'categorie' => CategorieActe::Societe]);
        $dossier = Dossier::create([
            'reference' => $reference, 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Edition,
            'redacteur_id' => $user->id, 'notaire_id' => $user->id, 'objet' => 'Test du gras de constitution',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => [
            'soc.denomination' => 'Faya distribution', 'soc.sigle' => 'FD', 'soc.siege_ville' => 'Conakry', 'soc.duree' => '99',
        ]]);

        try {
            $chemin = app(ActesGeneratorService::class)->genererDocument($dossier->fresh('questionnaire'), 'test-gras.docx', 'test-gras');
            $zip = new ZipArchive();
            $zip->open(storage_path('app/public/' . $chemin));
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            return $xml;
        } finally {
            File::deleteDirectory(storage_path('app/public/documents/' . $reference));
        }
    }

    /** Le run qui contient ce texte, ou null. */
    private function runDe(string $xml, string $texte): ?string
    {
        preg_match_all('#<w:r>.*?</w:r>|<w:r [^>]*>.*?</w:r>#s', $xml, $runs);
        foreach ($runs[0] as $run) {
            if (str_contains(strip_tags($run), $texte)) return $run;
        }

        return null;
    }

    public function test_une_constitution_met_la_denomination_en_majuscules_et_en_gras(): void
    {
        $xml = $this->generer('SOC-SARLU', 'TST-GRA-0001');

        $denomination = $this->runDe($xml, 'FAYA DISTRIBUTION');
        $this->assertNotNull($denomination, 'La dénomination doit sortir en majuscules.');
        $this->assertStringContainsString('<w:b/>', $denomination);
        $this->assertStringContainsString('w:val="44"', $denomination, 'La taille du gabarit (22 pts) doit être conservée.');

        // Le siège passe lui aussi en capitales depuis les modèles finaux du 2026-11-20.
        foreach (['FD', 'CONAKRY'] as $valeur) {
            $this->assertStringContainsString('<w:b/>', (string) $this->runDe($xml, $valeur), "« {$valeur} » doit être en gras.");
        }

        // Le libellé du même paragraphe et une autre balise restent maigres.
        $this->assertStringNotContainsString('<w:b/>', (string) $this->runDe($xml, 'Dénomination :'));
        $this->assertStringNotContainsString('<w:b/>', (string) $this->runDe($xml, '99'));
    }

    public function test_hors_constitution_rien_ne_change(): void
    {
        // Une modification : la demande portait sur la création.
        $xml = $this->generer('SOC-MOD', 'TST-GRA-0002');

        $this->assertNotNull($this->runDe($xml, 'Faya distribution'), 'Casse saisie conservée hors constitution.');
        $this->assertStringNotContainsString('<w:b/>', $xml);
    }
}
