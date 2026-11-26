<?php

namespace Tests\Feature;

use App\Console\Commands\AlignerQuartiersConakry;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Lieu;
use App\Models\User;
use App\Models\UserRole;
use App\Support\Normalisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les 13 communes de Conakry et leurs quartiers, d'après la liste de l'étude (2026-11-25).
 *
 * L'opération doit tourner **en production sur des données paramétrées** : chaque test ci-dessous
 * verrouille une des promesses qui le permettent — rien ne se supprime, rien ne se renomme, rien ne
 * se crée en double, et ce que l'étude a saisi, validé ou employé n'est jamais touché.
 */
class QuartiersConakryTest extends TestCase
{
    use RefreshDatabase;

    private const COMMANDE = 'ayelema:lieux-conakry';

    private function donnees(): array
    {
        return json_decode(file_get_contents(database_path('data/quartiers-conakry-2024.json')), true);
    }

    /** L'état d'avant : 13 communes, et les quartiers pendus à l'ancien découpage. */
    private function ancienDecoupage(): array
    {
        $ville = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Conakry']);
        $communes = [];

        foreach (['Kaloum', 'Kassa', 'Dixinn', 'Matam', 'Ratoma', 'Lambanyi', 'Sonfonia', 'Gbessia',
            'Matoto', 'Tombolia', 'Kagbelen', 'Sanoyah', 'Manéah'] as $nom) {
            $communes[$nom] = Lieu::create([
                'parent_id' => $ville->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => $nom, 'source' => 'import:2026-09-10',
            ]);
        }

        return $communes;
    }

    private function quartier(Lieu $commune, string $nom, array $attributs = []): Lieu
    {
        // `$attributs` à gauche : l'union de tableaux garde la clé du premier opérande.
        return Lieu::create($attributs + [
            'parent_id' => $commune->id, 'niveau' => Lieu::NIVEAU_QUARTIER, 'nom' => $nom, 'a_verifier' => true,
        ]);
    }

    private function quartiersDe(string $commune): array
    {
        $id = Lieu::where('niveau', Lieu::NIVEAU_COMMUNE)->where('nom', $commune)->value('id');

        return Lieu::where('parent_id', $id)->orderBy('nom')->pluck('nom')->all();
    }

    // ── Le fichier livré ───────────────────────────────────────────────────

    public function test_le_fichier_livre_est_sain(): void
    {
        $donnees = $this->donnees();

        $this->assertSame([], AlignerQuartiersConakry::erreursDeStructure($donnees));
        $this->assertCount(13, $donnees['communes'], 'Conakry compte 13 communes depuis la loi L/2024/003.');
    }

    public function test_un_quartier_ne_porte_le_nom_d_une_autre_commune(): void
    {
        // Garde-fou repris de `LieuxTest` (2026-09-10) : « Lambanyi » figurait comme commune **et**
        // comme quartier de Ratoma. La liste de l'étude admet en revanche qu'un chef-lieu porte le
        // nom de **sa** commune — Lambanyi, Tombolia, Matam : c'est la seule exception.
        $donnees = $this->donnees();
        $communes = collect($donnees['communes'])->mapWithKeys(fn ($c) => [Normalisation::cleLieu($c['nom']) => $c['nom']]);

        foreach ($donnees['communes'] as $commune) {
            foreach (AlignerQuartiersConakry::quartiersDeclares($commune, $donnees['sources']) as $q) {
                $cle = Normalisation::cleLieu($q['nom']);

                if ($communes->has($cle)) {
                    $this->assertSame($communes[$cle], $commune['nom'], "« {$q['nom']} » est une autre commune de Conakry.");
                }
            }
        }
    }

    public function test_ce_qui_ne_repose_que_sur_wikipedia_est_a_verifier(): void
    {
        $donnees = $this->donnees();

        foreach ($donnees['communes'] as $commune) {
            if ($commune['source'] !== 'WIKIPEDIA') {
                continue;
            }

            foreach (AlignerQuartiersConakry::quartiersDeclares($commune, $donnees['sources']) as $q) {
                $this->assertTrue($q['a_verifier'], "« {$q['nom']} » ({$commune['nom']}) ne repose que sur Wikipédia.");
            }
        }
    }

    public function test_un_nom_cite_sous_deux_communes_est_a_verifier_des_deux_cotes(): void
    {
        // Yattaya Centre (Lambanyi, Sonfonia), Nassouroulaye (Lambanyi, Gbessia) : l'une des deux
        // attributions est peut-être fausse, et rien ne dit laquelle.
        $donnees = $this->donnees();
        $parCle = [];

        foreach ($donnees['communes'] as $commune) {
            foreach (AlignerQuartiersConakry::quartiersDeclares($commune, $donnees['sources']) as $q) {
                $parCle[Normalisation::cleLieu($q['nom'])][] = $q;
            }
        }

        $doubles = array_filter($parCle, fn ($liste) => count($liste) > 1);
        $this->assertNotEmpty($doubles);

        foreach ($doubles as $liste) {
            foreach ($liste as $q) {
                $this->assertTrue($q['a_verifier'], "« {$q['nom']} » est cité sous deux communes.");
            }
        }
    }

    // ── La clé de rapprochement ────────────────────────────────────────────

    public function test_la_cle_rapproche_les_variantes_d_un_meme_nom(): void
    {
        $this->assertSame(Normalisation::cleLieu('Almamya I'), Normalisation::cleLieu('Almamya 1'));
        $this->assertSame(Normalisation::cleLieu('Tanènè II'), Normalisation::cleLieu('Tanene 2'));
        $this->assertSame(Normalisation::cleLieu('Sans-fil'), Normalisation::cleLieu('Sans Fil'));
        $this->assertSame(Normalisation::cleLieu('Belle-vue École'), Normalisation::cleLieu('Belle-Vue Ecole'));
        $this->assertSame(Normalisation::cleLieu("Cité de l'Air"), Normalisation::cleLieu('Cite de l Air'));

        $this->assertNotSame(Normalisation::cleLieu('Almamya'), Normalisation::cleLieu('Almamya I'));
        $this->assertNotSame(Normalisation::cleLieu('Hafia I'), Normalisation::cleLieu('Hafia II'));
        // « i » n'est un chiffre romain que seul : « Kipé » et « Hafia » ne sont pas touchés.
        $this->assertSame('kipe', Normalisation::cleLieu('Kipé'));
    }

    // ── L'ajout ────────────────────────────────────────────────────────────

    public function test_la_simulation_n_ecrit_rien(): void
    {
        $communes = $this->ancienDecoupage();
        $this->quartier($communes['Ratoma'], 'Bambéto');
        $avant = Lieu::count();

        $this->artisan(self::COMMANDE, ['--desactiver-obsoletes' => true])->assertSuccessful();

        $this->assertSame($avant, Lieu::count());
        $this->assertTrue(Lieu::where('nom', 'Bambéto')->value('actif'));
    }

    public function test_les_quartiers_sont_crees_sous_leur_commune(): void
    {
        $this->ancienDecoupage();

        $this->artisan(self::COMMANDE, ['--appliquer' => true])->assertSuccessful();

        $this->assertContains('Nongo Centre', $this->quartiersDe('Lambanyi'));
        $this->assertContains('Kobaya', $this->quartiersDe('Sonfonia'));
        $this->assertContains("Cité de l'Air", $this->quartiersDe('Gbessia'));
        $this->assertNotContains('Nongo Centre', $this->quartiersDe('Ratoma'));

        $total = collect($this->donnees()['communes'])->sum(fn ($c) => count($c['quartiers']));
        $this->assertSame($total, Lieu::niveau(Lieu::NIVEAU_QUARTIER)->count());

        $this->assertSame(
            'conakry-2024:MATD-2024',
            Lieu::where('nom', 'Kobaya')->value('source'),
            'Chaque lieu ajouté dit d\'où il vient.',
        );
    }

    public function test_les_districts_de_kassa_ne_sont_pas_crees(): void
    {
        // Le référentiel n'a que trois niveaux : les secteurs y sont les quartiers.
        $this->ancienDecoupage();
        $this->artisan(self::COMMANDE, ['--appliquer' => true]);

        $kassa = $this->quartiersDe('Kassa');
        $this->assertContains('Fotoba Centre', $kassa);
        $this->assertNotContains('Fotoba', $kassa);
        $this->assertNotContains('Kassa', $kassa);
    }

    public function test_l_operation_est_idempotente(): void
    {
        $communes = $this->ancienDecoupage();
        $this->quartier($communes['Ratoma'], 'Bambéto');
        $options = ['--appliquer' => true, '--desactiver-obsoletes' => true];

        $this->artisan(self::COMMANDE, $options);
        $apres = Lieu::orderBy('id')->get(['id', 'nom', 'actif', 'a_verifier', 'updated_at'])->toArray();

        $this->artisan(self::COMMANDE, $options);

        $this->assertSame($apres, Lieu::orderBy('id')->get(['id', 'nom', 'actif', 'a_verifier', 'updated_at'])->toArray());
    }

    public function test_une_variante_d_orthographe_ne_cree_pas_de_doublon_et_n_est_pas_renommee(): void
    {
        // La cascade compare le nom à l'identique : renommer ferait passer en « hors référentiel »
        // toutes les fiches qui portent l'ancienne graphie.
        $communes = $this->ancienDecoupage();
        $existant = $this->quartier($communes['Kaloum'], 'Sans-fil', ['a_verifier' => false]);

        $this->artisan(self::COMMANDE, ['--appliquer' => true]);

        $this->assertSame(1, Lieu::where('parent_id', $communes['Kaloum']->id)->get()
            ->filter(fn ($l) => Normalisation::cleLieu($l->nom) === 'sansfil')->count());
        $this->assertSame('Sans-fil', $existant->fresh()->nom);
        $this->assertSame('Kagbelen', $communes['Kagbelen']->fresh()->nom, 'Une commune n\'est pas renommée non plus.');
    }

    public function test_un_lieu_existant_n_est_pas_modifie(): void
    {
        // Ni validé, ni remis à vérifier, ni réactivé : ce sont peut-être les choix de l'étude.
        $communes = $this->ancienDecoupage();
        $valide  = $this->quartier($communes['Ratoma'], 'Kipé', ['a_verifier' => false]);
        $eteint  = $this->quartier($communes['Ratoma'], 'Taouyah', ['actif' => false]);

        $this->artisan(self::COMMANDE, ['--appliquer' => true, '--desactiver-obsoletes' => true]);

        $this->assertFalse($valide->fresh()->a_verifier);
        $this->assertFalse($eteint->fresh()->actif, 'Un lieu désactivé n\'est jamais réactivé.');
    }

    // ── L'ancien découpage ─────────────────────────────────────────────────

    public function test_sans_l_option_rien_n_est_desactive(): void
    {
        $communes = $this->ancienDecoupage();
        $obsolete = $this->quartier($communes['Ratoma'], 'Bambéto');

        $this->artisan(self::COMMANDE, ['--appliquer' => true]);

        $this->assertTrue($obsolete->fresh()->actif);
    }

    public function test_un_quartier_obsolete_et_libre_est_desactive_jamais_supprime(): void
    {
        $communes = $this->ancienDecoupage();
        $obsolete = $this->quartier($communes['Ratoma'], 'Bambéto');

        $this->artisan(self::COMMANDE, ['--appliquer' => true, '--desactiver-obsoletes' => true]);

        $this->assertNotNull($obsolete->fresh(), 'Rien n\'est jamais supprimé.');
        $this->assertFalse($obsolete->fresh()->actif);
        $this->assertSame($communes['Ratoma']->id, $obsolete->fresh()->parent_id, 'Rien n\'est déplacé.');
    }

    public function test_un_quartier_obsolete_employe_reste_actif(): void
    {
        // Mesuré : « Nongo » sous Ratoma figure dans 2 fiches sociétés et 3 questionnaires. Il doit
        // rester proposé, sinon chacune s'ouvrirait sur « hors référentiel — à reprendre ».
        $communes = $this->ancienDecoupage();
        $nongo = $this->quartier($communes['Ratoma'], 'Nongo');

        Client::create([
            'type' => 'physique', 'nom_famille' => 'DIALLO',
            'demeurant_ville' => 'Conakry', 'commune' => 'Ratoma', 'quartier' => 'Nongo',
        ]);

        $this->artisan(self::COMMANDE, ['--appliquer' => true, '--desactiver-obsoletes' => true]);

        $this->assertTrue($nongo->fresh()->actif);
    }

    public function test_un_quartier_saisi_ou_valide_par_l_etude_reste_actif(): void
    {
        $admin = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $admin->id, 'role' => RoleUtilisateur::Administrateur->value]);

        $communes = $this->ancienDecoupage();
        $saisi  = $this->quartier($communes['Tombolia'], 'Dapompa', ['created_by_id' => $admin->id, 'a_verifier' => false]);
        // Amorcé sans source, puis validé : `a_verifier` levé par l'étude.
        $valide = $this->quartier($communes['Dixinn'], 'Hafia', ['a_verifier' => false]);

        $this->artisan(self::COMMANDE, ['--appliquer' => true, '--desactiver-obsoletes' => true]);

        $this->assertTrue($saisi->fresh()->actif);
        $this->assertTrue($valide->fresh()->actif);
    }

    public function test_la_commune_homonyme_hors_conakry_ne_recoit_plus_les_quartiers(): void
    {
        // La cascade filtre les quartiers par le **nom** de leur commune : tant que Dubréka · Manéah
        // (import GeoNames, antérieur au rattachement de 2024) reste active, la choisir proposerait
        // les quartiers de Manéah-Conakry.
        $this->ancienDecoupage();
        $dubreka = Lieu::create(['niveau' => Lieu::NIVEAU_VILLE, 'nom' => 'Dubréka']);
        $homonyme = Lieu::create([
            'parent_id' => $dubreka->id, 'niveau' => Lieu::NIVEAU_COMMUNE, 'nom' => 'Manéah', 'source' => 'import:2026-09-10',
        ]);

        $this->artisan(self::COMMANDE, ['--appliquer' => true, '--desactiver-obsoletes' => true]);

        $this->assertFalse($homonyme->fresh()->actif);
        $this->assertSame(
            ['Conakry'],
            collect(Lieu::referentielComplet()['commune'])->where('nom', 'Manéah')->pluck('parent')->all(),
        );
    }

    // ── Ce que la cascade reçoit ───────────────────────────────────────────

    public function test_le_referentiel_servi_porte_le_drapeau_a_verifier(): void
    {
        // `lieu-select.jsx` affiche « (à vérifier) » depuis l'origine, mais la clé n'était pas
        // servie : le drapeau ne se voyait qu'en Paramètres.
        $this->ancienDecoupage();
        $this->artisan(self::COMMANDE, ['--appliquer' => true]);

        $quartiers = collect(Lieu::referentielComplet()['quartier']);

        $this->assertTrue($quartiers->firstWhere('nom', 'Sogouéta')['a_verifier']);
        $this->assertFalse($quartiers->firstWhere('nom', 'Kobaya')['a_verifier']);
    }
}
