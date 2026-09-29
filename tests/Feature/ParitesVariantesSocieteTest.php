<?php

namespace Tests\Feature;

use App\Enums\StatutSociete;
use App\Enums\TypeModificationStatutaire;
use App\Enums\VarianteDissolution;
use Tests\TestCase;

/**
 * Garde-fous de duplication PHP ⇄ JS pour tout ce qui décline un dossier de société.
 *
 * Trois listes vivent des deux côtés sans pouvoir être alimentées depuis PHP — les
 * questionnaires sont des modules statiques. La duplication est donc inévitable, ce qui, dans
 * ce dépôt, veut dire **gardée** (règle 2).
 *
 * Le test sur `modif.types` **n'existait pas** : ouvert le 2026-08-11 avec le multi-modifications,
 * il est resté un trou jusqu'au 2026-09-28. Un libellé mal orthographié d'un seul côté y passait
 * en silence — `TypeModificationStatutaire::depuisLibelle()` rend `null`, `depuisLibelles()`
 * ignore les valeurs non reconnues, et la case cochée ne produisait alors ni acte, ni barème,
 * ni formalité. C'est exactement la forme de défaut que le dépôt refuse : silencieux.
 */
class ParitesVariantesSocieteTest extends TestCase
{
    /**
     * Valeurs d'un tableau de littéraux exporté par un module JS.
     *
     * Extraction **ligne par ligne** par expression régulière, comme
     * {@see ListesMatrimonialesTest::listeJs()} : c'est ce qui oblige les listes à rester écrites
     * en clair côté JS plutôt que dérivées par un `.map()`, qu'aucune analyse statique ne saurait
     * évaluer ici.
     *
     * @return array<int, string>
     */
    private function litterauxJs(string $fichier, string $bloc, ?string $champ = null): array
    {
        $source = file_get_contents(resource_path($fichier));

        $this->assertSame(
            1,
            preg_match($bloc, $source, $captures),
            "Bloc introuvable dans {$fichier} — le motif {$bloc} n'a rien capturé.",
        );

        $corps = $captures[1];

        if ($champ !== null) {
            preg_match_all("/{$champ}:\s*'((?:[^'\\\\]|\\\\.)*)'/", $corps, $valeurs);
        } else {
            preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'|\"((?:[^\"\\\\]|\\\\.)*)\"/", $corps, $valeurs);
            $valeurs[1] = array_map(
                fn ($simple, $double) => $simple !== '' ? $simple : $double,
                $valeurs[1],
                $valeurs[2],
            );
        }

        return array_map(
            fn (string $v) => str_replace(["\\'", '\\"'], ["'", '"'], $v),
            $valeurs[1],
        );
    }

    // ── Statut de la fiche société ───────────────────────────────────────────

    public function test_les_statuts_de_societe_php_et_js_ont_les_memes_valeurs(): void
    {
        $js = $this->litterauxJs(
            'js/data/statutsSociete.js',
            '/export const STATUTS_SOCIETE = \[(.*?)\n\];/s',
            'valeur',
        );

        $this->assertSame(
            array_map(fn (StatutSociete $s) => $s->value, StatutSociete::cases()),
            $js,
            'Ajoutez le statut aux deux endroits : App\Enums\StatutSociete et '
            . 'resources/js/data/statutsSociete.js. Le `match` exhaustif côté PHP échoue '
            . "bruyamment ; côté JS, rien n'échoue du tout.",
        );
    }

    public function test_les_statuts_de_societe_php_et_js_ont_les_memes_libelles(): void
    {
        $js = $this->litterauxJs(
            'js/data/statutsSociete.js',
            '/export const STATUTS_SOCIETE = \[(.*?)\n\];/s',
            'label',
        );

        $this->assertSame(
            array_map(fn (StatutSociete $s) => $s->label(), StatutSociete::cases()),
            $js,
        );
    }

    public function test_les_statuts_de_societe_php_et_js_ont_les_memes_couleurs(): void
    {
        // La couleur voyage jusqu'au badge du sélecteur : une divergence rendrait une société
        // radiée en vert, ce qui est pire qu'un badge absent.
        $js = $this->litterauxJs(
            'js/data/statutsSociete.js',
            '/export const STATUTS_SOCIETE = \[(.*?)\n\];/s',
            'couleur',
        );

        $this->assertSame(
            array_map(fn (StatutSociete $s) => $s->couleur(), StatutSociete::cases()),
            $js,
        );
    }

    // ── Variantes de modification statutaire (le trou du 2026-08-11) ─────────

    public function test_les_libelles_de_modification_php_et_js_sont_identiques(): void
    {
        $js = $this->litterauxJs(
            'js/data/questionnaires.js',
            "/\{ id: 'modif\.types'.*?options: \[(.*?)\n\s*\],/s",
        );

        $php = array_map(
            fn (TypeModificationStatutaire $t) => $t->label(),
            TypeModificationStatutaire::cases(),
        );

        // Ensembles, pas séquences : l'ordre d'affichage des cases est un choix d'interface,
        // celui de l'enum pilote l'ordre des documents. Les deux n'ont aucune raison de coïncider.
        sort($php);
        sort($js);

        $this->assertSame(
            $php,
            $js,
            "Un libellé de `modif.types` doit correspondre exactement à un "
            . 'TypeModificationStatutaire::label(). Sinon `depuisLibelle()` rend null, la case '
            . "cochée ne produit ni acte ni barème ni formalité, et rien ne le signale.",
        );
    }

    // ── Variantes de dissolution ─────────────────────────────────────────────

    public function test_les_libelles_de_phase_de_dissolution_php_et_js_sont_identiques(): void
    {
        $js = $this->litterauxJs(
            'js/data/questionnaires.js',
            "/\{ id: 'dissolution\.phase'.*?options: \[(.*?)\],/s",
        );

        $php = array_map(
            fn (VarianteDissolution $v) => $v->label(),
            VarianteDissolution::cases(),
        );

        $this->assertSame(
            $php,
            $js,
            'La phase choisie au questionnaire décide de la variante, donc des actes produits '
            . "et de l'effet sur la fiche société. Un libellé divergent ne déclenche rien.",
        );
    }
}
