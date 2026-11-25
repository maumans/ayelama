<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use App\Support\VariantesRedaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use ZipArchive;

/**
 * Les variantes que les actes **finaux** de l'étude écrivent sans que personne les saisisse
 * (`docs/ACTES/`, reçus le 2026-11-20 — `docs/kit-modeles/06`).
 */
class VariantesRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_civilite_donne_la_forme_longue_et_les_accords(): void
    {
        $v = VariantesRedaction::racine(['pp.civilite' => 'Mme', 'ger.civilite' => 'M.']);

        $this->assertSame('Madame', $v['pp.civilite_longue']);
        $this->assertSame('Née', $v['pp.ne']);
        $this->assertSame('e', $v['pp.accord']);
        $this->assertSame('Monsieur', $v['ger.civilite_longue']);
        $this->assertSame('Né', $v['ger.ne']);
        $this->assertSame('', $v['ger.accord']);
    }

    public function test_une_civilite_inconnue_ne_donne_aucun_accord(): void
    {
        // 11 questionnaires de société sur 28 sans civilité (2026-11-20) : le masculin par
        // défaut serait une supposition. La balise reste vide et l'historique le signale.
        $v = VariantesRedaction::racine(['pp.civilite' => '', 'associe.civilite' => 'Société']);

        $this->assertArrayNotHasKey('pp.ne', $v);
        $this->assertArrayNotHasKey('associe.ne', $v);
    }

    public function test_une_valeur_saisie_l_emporte_sur_la_variante(): void
    {
        $v = VariantesRedaction::racine(['pp.civilite' => 'M.', 'pp.civilite_longue' => 'Son Excellence']);

        $this->assertArrayNotHasKey('pp.civilite_longue', $v);
    }

    public function test_la_date_redigee_suit_l_ecriture_des_actes(): void
    {
        $this->assertSame('quinze Février mille neuf cent quatre-vingt', VariantesRedaction::dateRedigee('15/02/1980'));
        $this->assertSame('premier Mars deux mille', VariantesRedaction::dateRedigee('01/03/2000'));
        $this->assertNull(VariantesRedaction::dateRedigee('1980-02-15'));
    }

    public function test_l_objet_social_devient_une_liste(): void
    {
        $v = VariantesRedaction::racine(['soc.objet_social' => "- l'import et l'export ;\nL'industrie.\n\n• Le commerce général"]);

        $this->assertSame(
            [['libelle' => "L'import et l'export"], ['libelle' => "L'industrie"], ['libelle' => 'Le commerce général']],
            $v['soc.activites'],
        );
        $this->assertSame("L'import et l'export, l'industrie, le commerce général", $v['soc.objet_social_ligne']);
    }

    public function test_les_souscripteurs_sont_decomptes_par_nature(): void
    {
        $this->assertSame('une (01) personne physique', VariantesRedaction::racine(['pp.prenom_nom' => 'Ousman JALLOH'])['soc.souscripteurs']);

        $sarl = ['associes' => [
            ['civilite' => 'M.', 'type_personne' => 'Personne physique'],
            ['civilite' => 'M.', 'type_personne' => 'Personne physique'],
            ['civilite' => 'Société', 'type_personne' => 'Personne morale'],
        ]];
        $this->assertSame('deux (02) personnes physiques et une (01) personne morale', VariantesRedaction::racine($sarl)['soc.souscripteurs']);

        // Une nature inconnue : on ne compte rien plutôt que de compter faux.
        $this->assertArrayNotHasKey('soc.souscripteurs', VariantesRedaction::racine(['associes' => [['nom' => 'X']]]));
    }

    public function test_les_parts_sont_numerotees_dans_l_ordre_et_l_apport_calcule(): void
    {
        $items = VariantesRedaction::bloc([
            ['civilite' => 'M.', 'parts_chiffres' => '50'],
            ['civilite' => 'Mme', 'parts_chiffres' => '50'],
        ], ['soc.valeur_nominale_chiffres' => '1000000']);

        $this->assertSame(['1', '50', '50000000'], [$items[0]['parts_debut'], $items[0]['parts_fin'], $items[0]['apport_chiffres']]);
        $this->assertSame(['51', '100', 'Née'], [$items[1]['parts_debut'], $items[1]['parts_fin'], $items[1]['ne']]);

        // Un nombre de parts manquant décalerait tous les suivants : aucune numérotation.
        $incomplet = VariantesRedaction::bloc([['parts_chiffres' => '50'], ['parts_chiffres' => '']], []);
        $this->assertArrayNotHasKey('parts_debut', $incomplet[0]);
    }

    public function test_un_acte_genere_porte_les_variantes_y_compris_dans_un_bloc(): void
    {
        Storage::fake('local');

        $word = new PhpWord();
        $section = $word->addSection();
        $section->addText('CIV=${pp.civilite_longue}|NE=${pp.ne}|ACC=soussigné${pp.accord}|DN=${pp.date_naissance_texte}|PARTS=${soc.nombre_parts_lettres}|EX=${soc.premier_exercice_annee_lettres}|SOUS=${soc.souscripteurs}');
        $section->addText('${soc.activites}');
        $section->addText('ACT=${soc.activites.libelle};');
        $section->addText('${/soc.activites}');
        $section->addText('${associes}');
        $section->addText('ASS=${associes.civilite_longue} ${associes.nom} ${associes.ne} le ${associes.date_naissance_texte} / ${associes.date_naissance_lettres} parts ${associes.parts_debut}-${associes.parts_fin} apport ${associes.apport_lettres}|');
        $section->addText('${/associes}');
        // La même liste une seconde fois (apports, puis répartition) : `cloneBlock()` ne traite
        // qu'une occurrence, le moteur doit les traiter toutes.
        $section->addText('${associes}');
        $section->addText('BIS=${associes.numero}°) ${associes.nom} ${associes.date_naissance_court}|');
        $section->addText('${/associes}');
        $section->addText('DATE=${date_acte_court}|');
        // Formulaire RCCM : un dirigeant par ligne de tableau, sans marqueur de bloc.
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('NOM');
        $table->addRow();
        $table->addCell(2000)->addText('LIG=${gerants.numero}:${gerants.prenom_nom}');
        $table->addCell(2000)->addText('${gerants.ne} le ${gerants.date_naissance_jma}|');
        $gabarit = Storage::disk('local')->path('modeles/test-variantes.docx');
        File::ensureDirectoryExists(dirname($gabarit));
        $word->save($gabarit);

        $user = User::factory()->create();
        $type = TypeActe::create(['code' => 'TST-VAR', 'label' => 'Type de test', 'categorie' => 'societe']);
        $dossier = Dossier::create([
            'reference' => 'TST-VAR-0001', 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Edition,
            'redacteur_id' => $user->id, 'notaire_id' => $user->id, 'objet' => 'Test des variantes',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => [
            'pp.civilite' => 'Mme', 'pp.prenom_nom' => 'Aïssatou BAH', 'pp.date_naissance' => '15/02/1980',
            'soc.nombre_parts' => '100', 'soc.premier_exercice_annee' => '2027', 'soc.valeur_nominale_chiffres' => '1000000',
            'soc.objet_social' => "L'import et l'export\nL'industrie",
            'associes' => [
                ['civilite' => 'M.', 'nom' => 'Thierno DIALLO', 'type_personne' => 'Personne physique', 'parts_chiffres' => '50', 'date_naissance' => '06/03/1993'],
                ['civilite' => 'Mme', 'nom' => 'Mariama DIALLO', 'type_personne' => 'Personne physique', 'parts_chiffres' => '50', 'date_naissance' => '01/06/1990'],
            ],
            'gerants' => [
                ['civilite' => 'M.', 'prenom_nom' => 'Thierno DIALLO', 'date_naissance' => '06/03/1993'],
                ['civilite' => 'Mme', 'prenom_nom' => 'Mariama DIALLO', 'date_naissance' => '01/06/1990'],
            ],
        ]]);

        try {
            $chemin = app(ActesGeneratorService::class)->genererDocument($dossier->fresh('questionnaire'), 'test-variantes.docx', 'test-variantes');
            $texte = $this->texteDocx(storage_path('app/public/' . $chemin));

            $this->assertStringContainsString('CIV=Madame|NE=Née|ACC=soussignée|DN=quinze Février mille neuf cent quatre-vingt|PARTS=CENT|EX=DEUX MILLE VINGT-SEPT|SOUS=deux (02) personnes physiques', $texte);
            $this->assertStringContainsString("ACT=L'import et l'export;ACT=L'industrie;", str_replace('&apos;', "'", $texte));
            $this->assertStringContainsString('ASS=Monsieur Thierno DIALLO Né le six Mars mille neuf cent quatre-vingt-treize / SIX MARS MILLE NEUF CENT QUATRE-VINGT-TREIZE parts 1-50 apport CINQUANTE MILLIONS|', $texte);
            $this->assertStringContainsString('ASS=Madame Mariama DIALLO Née le premier Juin mille neuf cent quatre-vingt-dix', $texte);
            $this->assertStringContainsString('parts 51-100', $texte);
            $this->assertStringContainsString('BIS=1°) Thierno DIALLO 06 Mars 1993|BIS=2°) Mariama DIALLO 01 Juin 1990|', $texte);
            $this->assertMatchesRegularExpression('/DATE=\d{2} [A-ZÉû][a-zéû]+ \d{4}\|/u', $texte);
            $this->assertStringContainsString('LIG=1:Thierno DIALLONé le 06/03/1993|', $texte);
            $this->assertStringContainsString('LIG=2:Mariama DIALLONée le 01/06/1990|', $texte);
            $this->assertStringNotContainsString('${', $texte);
        } finally {
            File::deleteDirectory(storage_path('app/public/documents/TST-VAR-0001'));
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
