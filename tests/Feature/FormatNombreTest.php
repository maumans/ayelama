<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use App\Support\FormatNombre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use ZipArchive;

/**
 * Séparateur de milliers « . » dans les documents — retours R13 et R16 (2026-11-16).
 */
class FormatNombreTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_point_separe_les_milliers(): void
    {
        $this->assertSame('50.000.000', FormatNombre::milliers('50000000'));
        $this->assertSame('1.000', FormatNombre::milliers(1000));
        $this->assertSame('99', FormatNombre::milliers('99'));          // une durée ne change pas
        $this->assertSame('1.500,5', FormatNombre::milliers('1500.5'));
        $this->assertSame('1.500,5', FormatNombre::milliers('1500,50'));
        // Déjà séparé : relu tel quel, et non comme « 1,0 ».
        $this->assertSame('1.000.000', FormatNombre::milliers('1.000.000'));
        $this->assertSame('1.000.000', FormatNombre::milliers("1\u{202F}000\u{202F}000"));
        // Pas un nombre : rendu intact plutôt que « 0 ».
        $this->assertSame('à préciser', FormatNombre::milliers('à préciser'));
    }

    public function test_les_cles_concernees_sont_les_montants_et_les_comptes(): void
    {
        foreach (['soc.capital_chiffres', 'bail.loyer_chiffres', 'soc.nombre_parts', 'bien.superficie', 'prix_cession', 'parts_chiffres'] as $cle) {
            $this->assertTrue(FormatNombre::doitFormater($cle), $cle);
        }
        foreach (['soc.premier_exercice_annee', 'pp.telephone', 'soc.duree', 'soc.rccm'] as $cle) {
            $this->assertFalse(FormatNombre::doitFormater($cle), $cle);
        }
    }

    public function test_un_acte_genere_porte_le_point(): void
    {
        Storage::fake('local');

        // Un gabarit minimal, avec les balises les plus employées des modèles actifs.
        $word = new PhpWord();
        $word->addSection()->addText('CAP=${soc.capital_chiffres}|FMT=${soc.capital_formate}|PARTS=${soc.nombre_parts}|DUREE=${soc.duree}|LET=${soc.capital_lettres}');
        $gabarit = Storage::disk('local')->path('modeles/test-milliers.docx');
        File::ensureDirectoryExists(dirname($gabarit));
        $word->save($gabarit);

        $user = User::factory()->create();
        $type = TypeActe::create(['code' => 'TST-MIL', 'label' => 'Type de test', 'categorie' => 'societe']);
        $dossier = Dossier::create([
            'reference' => 'TST-MIL-0001', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Edition,
            'redacteur_id' => $user->id, 'notaire_id' => $user->id, 'objet' => 'Test du séparateur de milliers',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => [
            'soc.capital_chiffres' => '50000000', 'soc.nombre_parts' => '5000', 'soc.duree' => '99',
        ]]);

        try {
            $chemin = app(ActesGeneratorService::class)->genererDocument($dossier->fresh('questionnaire'), 'test-milliers.docx', 'test-milliers');
            $texte = $this->texteDocx(storage_path('app/public/' . $chemin));

            $this->assertStringContainsString('CAP=50.000.000|', $texte);
            $this->assertStringContainsString('FMT=50.000.000|', $texte);
            $this->assertStringContainsString('PARTS=5.000|', $texte);
            $this->assertStringContainsString('DUREE=99|', $texte);
            // Les lettres ne bougent pas.
            $this->assertStringContainsString('LET=CINQUANTE MILLIONS', $texte);
        } finally {
            File::deleteDirectory(storage_path('app/public/documents/TST-MIL-0001'));
        }
    }

    private function texteDocx(string $chemin): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($chemin) === true, "Document illisible : {$chemin}");
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return preg_replace('/<[^>]+>/', '', $xml);
    }
}
