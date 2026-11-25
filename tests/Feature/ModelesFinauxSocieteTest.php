<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Les gabarits SARLU et SARL balisés sur les modèles finaux de l'étude (2026-11-20) produisent
 * un acte **complet** — chaque balise remplie, chaque liste répétée — avec des données fictives.
 *
 * ⚠️ Ce que `BalisesGabaritsActifsTest` ne voit pas : une balise peut être résolvable et sortir
 * vide (un bloc que PhpWord ne clone pas, une variante jamais calculée). Ici on génère pour de
 * vrai et on lit le document. C'est ainsi qu'ont été trouvés, le jour même, le bloc répété trois
 * fois dont seule la dernière occurrence était clonée, et les dates de bloc jamais dérivées.
 *
 * Les gabarits sont lus dans le dépôt (`Documents reçus/Modèles finaux 2026-11/`), pas dans
 * `storage/` qui n'y est pas : le test tourne partout.
 */
class ModelesFinauxSocieteTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE = 'Documents reçus/Modèles finaux 2026-11';

    private const DOCUMENTS = ['statuts', 'page-garde', 'attestation-capital', 'declaration-honneur', 'dnsv', 'insertion', 'rccm'];

    /** Rien des deux dossiers réels ne doit subsister dans un gabarit. */
    private const DONNEES_REELLES = [
        'KOUMI', 'JALLOH', 'Ousman', 'SLR149762', 'Tongo', 'Freetown', 'NEXIUM', 'Thierno', 'Diafar',
        'Boubacar', 'O05182213', 'O05297433', 'CONTEYAH', 'Kipé', 'LAMBANYI',
    ];

    public function test_aucun_gabarit_ne_garde_de_donnee_des_dossiers_reels(): void
    {
        foreach (['SARLU' => 'sarlu', 'SARL' => 'sarl'] as $dossier => $forme) {
            foreach (self::DOCUMENTS as $doc) {
                $chemin = base_path(self::SOURCE . "/{$dossier}/{$doc}-{$forme}.docx");
                $this->assertFileExists($chemin);

                $zip = new ZipArchive();
                $zip->open($chemin);
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $partie = $zip->getNameIndex($i);
                    if (! str_ends_with($partie, '.xml')) {
                        continue;
                    }
                    $texte = strip_tags($zip->getFromIndex($i));
                    foreach (self::DONNEES_REELLES as $donnee) {
                        $this->assertStringNotContainsString($donnee, $texte, "{$doc}-{$forme} ({$partie}) contient encore « {$donnee} »");
                    }
                }
                $zip->close();
            }
        }
    }

    public function test_les_sept_actes_sarlu_sont_complets_et_accordes_au_feminin(): void
    {
        $textes = $this->generer('SARLU', 'sarlu', 'SOC-SARLU', [
            'soc.denomination' => 'Atlantique Négoce', 'soc.capital_chiffres' => '20000000', 'soc.nombre_parts' => '200',
            'soc.valeur_nominale_chiffres' => '100000', 'soc.siege_quartier' => 'Almamya', 'soc.siege_commune' => 'Kaloum',
            'soc.siege_ville' => 'Conakry', 'soc.objet_social' => "Le négoce\nLa logistique", 'soc.premier_exercice_annee' => '2027',
            'soc.date_debut_activite' => '01/12/2026',
            'pp.civilite' => 'Mme', 'pp.prenom_nom' => 'Fatoumata CAMARA', 'pp.nom_famille' => 'CAMARA', 'pp.prenoms' => 'Fatoumata',
            'pp.demeurant_ville' => 'Conakry', 'pp.quartier' => 'Almamya', 'pp.commune' => 'Kaloum', 'pp.pays' => 'Guinée',
            'pp.ne_a' => 'Kindia (Guinée)', 'pp.date_naissance' => '15/02/1980', 'pp.nationalite' => 'Guinéenne',
            'pp.piece_type' => 'passeport', 'pp.piece_numero' => 'PX000001', 'pp.piece_delivree_le' => '06/02/2025',
            'pp.piece_delivree_a' => 'Conakry', 'pp.piece_expire_le' => '06/02/2030',
        ]);

        $this->assertStringContainsString('Madame Fatoumata CAMARA, demeurant à Conakry', $textes['statuts']);
        $this->assertStringContainsString('Née à Kindia (Guinée), le quinze Février mille neuf cent quatre-vingt', $textes['statuts']);
        $this->assertStringContainsString('A ce, présente', $textes['statuts']);
        $this->assertStringContainsString('Ci-après dénommée', $textes['statuts']);
        $this->assertStringContainsString('Laquelle, a conformément', $textes['statuts']);
        $this->assertStringContainsString('DEUX CENTS (200) parts sociales de CENT MILLE (100.000)', $textes['statuts']);
        $this->assertStringContainsString('Le négoce ;La logistique ;', $textes['statuts']);
        // Le gérant d'une SARLU hérite de l'associé unique, accords compris.
        $this->assertStringContainsString('est nommée en qualité de Gérant Statutaire', $textes['statuts']);
        $this->assertStringContainsString('par une (01) personne physique, en numéraire', $textes['dnsv']);
        $this->assertStringContainsString('Je soussignée, Fatoumata CAMARA', $textes['declaration-honneur']);
        $this->assertStringContainsString('Elle a les pouvoirs', $textes['insertion']);
        $this->assertStringContainsString('DEUX MILLE VINGT-SEPT', $textes['insertion']);
        $this->assertStringContainsString('Le 01 Décembre 2026', $textes['rccm']);
        $this->assertStringContainsString('CAMARAFatoumataNée à Kindia (Guinée), le 15 Février 1980', $textes['rccm']);
        $this->assertStringContainsString('VINGT MILLIONS (20.000.000) FRANCS GUINEENS', $textes['attestation-capital']);
    }

    public function test_les_sept_actes_sarl_repetent_chaque_liste_d_associes_et_de_gerants(): void
    {
        $associe = fn (string $civ, string $nom, string $parts, string $ne) => [
            'civilite' => $civ, 'nom' => $nom, 'type_personne' => 'Personne physique', 'parts_chiffres' => $parts,
            'demeurant_ville' => 'Conakry', 'quartier' => 'Dixinn', 'commune' => 'Dixinn', 'pays' => 'Guinée',
            'ne_a' => 'Labé (Guinée)', 'date_naissance' => $ne, 'nationalite' => 'Guinéenne', 'piece_type' => 'passeport',
            'cni' => 'PX' . $parts, 'piece_delivree_le' => '02/06/2025', 'piece_delivree_a' => 'Conakry', 'piece_expire_le' => '02/06/2035',
        ];

        $textes = $this->generer('SARL', 'sarl', 'SOC-SARL', [
            'soc.denomination' => 'Fleuve Énergie', 'soc.capital_chiffres' => '100000000', 'soc.nombre_parts' => '100',
            'soc.valeur_nominale_chiffres' => '1000000', 'soc.siege_quartier' => 'Dixinn', 'soc.siege_commune' => 'Dixinn',
            'soc.siege_ville' => 'Conakry', 'soc.objet_social' => "L'énergie solaire\nLe conseil", 'soc.premier_exercice_annee' => '2027',
            'soc.date_debut_activite' => '01/12/2026',
            'associes' => [$associe('M.', 'Alpha BARRY', '60', '06/03/1993'), $associe('Mme', 'Kadiatou SOW', '40', '15/06/1990')],
            'gerants' => [[
                'civilite' => 'Mme', 'prenom_nom' => 'Kadiatou SOW', 'nom_famille' => 'SOW', 'prenoms' => 'Kadiatou',
                'demeurant_ville' => 'Conakry', 'quartier' => 'Dixinn', 'commune' => 'Dixinn', 'pays' => 'Guinée',
                'ne_a' => 'Labé (Guinée)', 'date_naissance' => '15/06/1990', 'nationalite' => 'Guinéenne', 'piece_type' => 'passeport',
                'piece_numero' => 'PX40', 'piece_delivree_le' => '02/06/2025', 'piece_delivree_a' => 'Conakry', 'piece_expire_le' => '02/06/2035',
            ]],
        ]);

        $statuts = $textes['statuts'];
        // Comparution, apports, répartition : les trois listes, chacune avec les deux associés.
        $this->assertStringContainsString('1°) Monsieur Alpha BARRY', $statuts);
        $this->assertStringContainsString('2°) Madame Kadiatou SOW', $statuts);
        $this->assertStringContainsString('Née à Labé (Guinée), le quinze Juin mille neuf cent quatre-vingt-dix', $statuts);
        $this->assertStringContainsString('Monsieur Alpha BARRY, la somme de SOIXANTE MILLIONS (60.000.000)', $statuts);
        $this->assertStringContainsString('Madame Kadiatou SOW, la somme de QUARANTE MILLIONS (40.000.000)', $statuts);
        $this->assertStringContainsString('numérotées de 1 à 60', $statuts);
        $this->assertStringContainsString('numérotées de 61 à 100', $statuts);
        $this->assertStringContainsString('Madame Kadiatou SOW, demeurant à Conakry (Guinée), quartier Dixinn, Commune de Dixinn, née à Labé', $statuts);

        // Le siège en capitales, comme la dénomination (saisi « Dixinn »).
        $this->assertStringContainsString('SIÈGE SOCIAL : DIXINN, COMMUNE DE DIXINN', $statuts);
        $this->assertStringContainsString("L'énergie solaire, le conseil, etc.", $textes['rccm']);

        $this->assertStringContainsString('par deux (02) personnes physiques, en numéraire', $textes['dnsv']);
        $this->assertStringContainsString('2°) Madame Kadiatou SOW', $textes['dnsv']);
        $this->assertStringContainsString('Je soussignée, Kadiatou SOW', $textes['declaration-honneur']);
        $this->assertStringContainsString('Madame Kadiatou SOW est nommée gérant', $textes['insertion']);
        $this->assertStringContainsString('SOWKadiatouNée le 15 Juin 1990 à Labé (Guinée)', $textes['rccm']);
    }

    /** @return array<string, string> document => texte de l'acte produit */
    private function generer(string $dossierSource, string $forme, string $code, array $donnees): array
    {
        Storage::fake('local');

        foreach (self::DOCUMENTS as $doc) {
            Storage::disk('local')->put(
                "modeles/societe/{$doc}-{$forme}.docx",
                file_get_contents(base_path(self::SOURCE . "/{$dossierSource}/{$doc}-{$forme}.docx")),
            );
        }

        $user = User::factory()->create();
        $type = TypeActe::firstOrCreate(['code' => $code], ['label' => $code, 'categorie' => 'societe']);
        $reference = "TST-{$forme}-0001";
        $dossier = Dossier::create([
            'reference' => $reference, 'type_acte_id' => $type->id, 'etape' => EtapeDossier::Edition,
            'redacteur_id' => $user->id, 'notaire_id' => $user->id, 'objet' => 'Constitution de test',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        $textes = [];
        try {
            foreach (self::DOCUMENTS as $doc) {
                $chemin = app(ActesGeneratorService::class)->genererDocument($dossier->fresh('questionnaire'), "societe/{$doc}-{$forme}.docx", "{$doc}-{$forme}");
                $texte = $this->texteDocx(storage_path('app/public/' . $chemin));

                $this->assertStringNotContainsString('${', $texte, "{$doc}-{$forme} : balise restée dans l'acte");
                $textes[$doc] = $texte;
            }
        } finally {
            File::deleteDirectory(storage_path("app/public/documents/{$reference}"));
        }

        return $textes;
    }

    private function texteDocx(string $chemin): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($chemin) === true, "Document illisible : {$chemin}");
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        return html_entity_decode(preg_replace('/<[^>]+>/', '', $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
