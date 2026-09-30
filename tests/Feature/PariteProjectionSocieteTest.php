<?php

namespace Tests\Feature;

use App\Models\Societe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * « Fiche société → clés `soc.*` » — une correspondance, deux détenteurs (2026-09-30).
 *
 * [CLAUDE.md](CLAUDE.md) désigne depuis longtemps `Societe::CHAMPS_QUESTIONNAIRE` et la table
 * `CHAMPS` de `resources/js/lib/societeFields.js` comme des « miroirs assumés », et le devbook
 * ajoute « à faire évoluer avec lui ». **Aucun test ne le vérifiait.**
 *
 * La duplication est inévitable — le serveur écrit la fiche depuis le questionnaire, le
 * navigateur préremplit le questionnaire depuis la fiche, et les deux sens vivent dans deux
 * langages. Ce qui est évitable, c'est qu'une divergence passe inaperçue : une clé oubliée côté
 * JS affiche la bonne fiche mais laisse la balise `${soc.*}` **vide dans l'acte produit** ; une
 * clé oubliée côté PHP empêche la fiche du registre de se mettre à jour. Ni l'un ni l'autre ne
 * lève d'erreur — c'est tout le problème.
 *
 * ⚠️ Le tableau de CLAUDE.md et le devbook donnaient tous deux
 * `resources/js/data/societeFields.js`, un chemin qui n'existe pas : le fichier est dans `lib/`.
 * Un test qui lit le fichier fait mieux qu'une consigne qui le désigne mal.
 *
 * Modèle suivi : `PiecesRequisesParRoleTest` — extraction par regex de la source JS, sans
 * exécuter de JavaScript.
 */
class PariteProjectionSocieteTest extends TestCase
{
    // Les deux premiers tests lisent des sources, pas la base ; le troisieme confronte la
    // correspondance au schema reel, d'ou la migration.
    use RefreshDatabase;

    private const SOURCE_JS = 'resources/js/lib/societeFields.js';

    /** La table `CHAMPS_QUESTIONNAIRE`, qui est privée — la lire ne l'élargit pas. */
    private function correspondancePhp(): array
    {
        $constante = (new ReflectionClass(Societe::class))->getConstant('CHAMPS_QUESTIONNAIRE');

        $this->assertIsArray($constante, 'Societe::CHAMPS_QUESTIONNAIRE a disparu ou changé de nature.');
        $this->assertNotEmpty($constante);

        return $constante;
    }

    /** La table `CHAMPS` du module JS, commentaires retirés pour ne pas lire un exemple. */
    private function correspondanceJs(): array
    {
        $absolu = base_path(self::SOURCE_JS);
        $this->assertFileExists($absolu, 'Fichier attendu : ' . self::SOURCE_JS);

        $code = preg_replace('#(//[^\n]*|/\*.*?\*/)#s', '', file_get_contents($absolu));

        $this->assertSame(
            1,
            preg_match('/const\s+CHAMPS\s*=\s*\{(.*?)\n\};/s', $code, $bloc),
            'La table CHAMPS de ' . self::SOURCE_JS . " est introuvable — le regex a-t-il cessé de correspondre ?",
        );

        preg_match_all("/'([^']+)'\s*:\s*'([^']+)'/", $bloc[1], $paires, PREG_SET_ORDER);

        $champs = [];
        foreach ($paires as $paire) {
            $champs[$paire[1]] = $paire[2];
        }

        $this->assertNotEmpty($champs, 'Aucune paire lue dans CHAMPS.');

        return $champs;
    }

    public function test_les_deux_tables_de_correspondance_sont_identiques(): void
    {
        $php = $this->correspondancePhp();
        $js  = $this->correspondanceJs();

        ksort($php);
        ksort($js);

        $this->assertSame(
            $php,
            $js,
            "La correspondance « clé de questionnaire → colonne de societes » a divergé entre\n"
            . "  app/Models/Societe.php  (CHAMPS_QUESTIONNAIRE)\n"
            . '  et ' . self::SOURCE_JS . " (CHAMPS).\n"
            . "Une clé absente du JS laisse la balise \${soc.*} vide dans l'acte ; absente du PHP,\n"
            . 'elle empêche la fiche du registre de se mettre à jour. Corrigez les deux.',
        );
    }

    /** Une clé qui ne désigne aucune colonne réelle projetterait dans le vide. */
    public function test_chaque_cle_projetee_designe_une_colonne_existante(): void
    {
        $colonnes = \Schema::getColumnListing('societes');

        foreach ($this->correspondancePhp() as $cle => $colonne) {
            $this->assertContains(
                $colonne,
                $colonnes,
                "{$cle} projette vers `societes.{$colonne}`, qui n'existe pas.",
            );
        }
    }

    /**
     * Le préfixe `soc.` n'est pas décoratif : `estChampSociete()` (JS) et le générateur de
     * balises s'en servent pour reconnaître une clé de société parmi les autres.
     */
    public function test_toutes_les_cles_portent_le_prefixe_soc(): void
    {
        foreach (array_keys($this->correspondancePhp()) as $cle) {
            $this->assertStringStartsWith('soc.', $cle, "Clé sans préfixe : {$cle}");
        }
    }
}
