<?php

namespace Tests\Feature;

use App\Enums\OrganismeFormalite;
use App\Models\Bareme;
use Tests\TestCase;

/**
 * Les organismes de formalité, déclarés une fois et vérifiés partout.
 *
 * ⚠️ **La liste vivait en cinq exemplaires** — la validation du contrôleur, le libellé du
 * modèle, la liste des barèmes, `ORGANISME_META` et les onglets de l'écran — et elles
 * divergeaient déjà. `Autre` existait côté barème sans exister côté formalité : une formalité
 * posée sous cet organisme était **refusée à la correction manuelle et absente des onglets**.
 * Elle existait en base, personne ne la voyait.
 *
 * Ces tests sont le filet qui manquait. Chacun échoue si une des cinq copies dérive.
 */
class OrganismesFormaliteTest extends TestCase
{
    public function test_les_organismes_php_et_js_ont_les_memes_cles(): void
    {
        $this->assertSame(
            OrganismeFormalite::valeurs(),
            array_keys($this->organismeMetaJs()),
            "Une clé de `ORGANISME_META` sans cas d'enum rend un badge que rien n'alimente ; "
            . "un cas d'enum sans clé rend une formalité sans libellé ni couleur, affichée en "
            . 'gris neutre au milieu des autres.',
        );
    }

    public function test_les_abreges_php_et_js_sont_identiques(): void
    {
        $php = array_map(fn (OrganismeFormalite $o) => $o->abrege(), OrganismeFormalite::cases());

        $this->assertSame(
            $php,
            array_values(array_column($this->organismeMetaJs(), 'label')),
            "L'abrégé voyage jusqu'à la pastille. Une divergence afficherait « GREFFE » sur une "
            . 'formalité déposée au journal.',
        );
    }

    public function test_le_journal_dannonces_legales_est_un_organisme_a_part_entiere(): void
    {
        // Lignes 1 à 5 et 15 du tableau de validation signé le 2026-10-28 : la publication est
        // obligatoire pour chaque modification et pour la dissolution. Rangée sous « Autre »,
        // elle était la démarche la plus systématique du cycle de vie d'une société, et la
        // seule qu'on ne pouvait pas suivre.
        $this->assertContains(OrganismeFormalite::Jal, OrganismeFormalite::suivis());
        $this->assertSame('JAL', OrganismeFormalite::Jal->abrege());
    }

    public function test_autre_est_declare_mais_nouvre_pas_donglet(): void
    {
        // Déclaré, donc validé et affichable ; sans onglet, parce qu'une file de dépôt
        // « divers » n'en est pas une.
        $this->assertContains('autre', OrganismeFormalite::valeurs());
        $this->assertNotContains(OrganismeFormalite::Autre, OrganismeFormalite::suivis());
    }

    public function test_chaque_libelle_de_bareme_se_relit_comme_un_organisme(): void
    {
        // La conversion `baremes.organisme` → `formalites.organisme` passait par un
        // `Str::slug()` de repli, qui fabriquait des organismes inconnus de tout le reste.
        foreach (OrganismeFormalite::cases() as $organisme) {
            $this->assertSame(
                $organisme,
                OrganismeFormalite::depuisLibelleBareme($organisme->libelleBareme()),
                "« {$organisme->libelleBareme()} » doit se relire comme {$organisme->value}.",
            );
        }

        // Un libellé hors liste retombe sur un cas **déclaré**, pas sur un slug inventé.
        $this->assertSame(OrganismeFormalite::Autre, OrganismeFormalite::depuisLibelleBareme('Trésor public'));
        $this->assertSame(OrganismeFormalite::Autre, OrganismeFormalite::depuisLibelleBareme(null));
    }

    public function test_un_bareme_rend_un_organisme_de_formalite_valide(): void
    {
        // Le bout de chaîne qui comptait : ce que `organismeFormalite()` rend doit passer la
        // validation de `FormaliteController` et trouver sa place dans les onglets.
        foreach (OrganismeFormalite::cases() as $organisme) {
            $bareme = new Bareme(['organisme' => $organisme->libelleBareme()]);

            $this->assertContains(
                $bareme->organismeFormalite(),
                OrganismeFormalite::valeurs(),
                "Un barème « {$organisme->libelleBareme()} » produit une formalité que le "
                . 'contrôleur refuserait de corriger.',
            );
        }

        // Et « Notaire », seule valeur de la colonne qui ne corresponde à aucune démarche :
        // des honoraires ne se déposent nulle part. Il doit retomber proprement, pas planter.
        $this->assertSame('autre', (new Bareme(['organisme' => 'Notaire']))->organismeFormalite());
    }

    /**
     * `ORGANISME_META` lu dans le module JS, clé par clé.
     *
     * Extraction par expression régulière, comme les autres tests de parité du dépôt : c'est
     * ce qui oblige la liste à rester écrite en clair côté JS plutôt que dérivée d'un `.map()`
     * qu'aucune analyse statique ne saurait évaluer ici.
     *
     * @return array<string, array{label: string}>
     */
    private function organismeMetaJs(): array
    {
        $source = file_get_contents(resource_path('js/data/formaliteStatuts.js'));

        $this->assertSame(
            1,
            preg_match('/export const ORGANISME_META = \{(.*?)\n\};/s', $source, $bloc),
            'Bloc ORGANISME_META introuvable dans formaliteStatuts.js.',
        );

        preg_match_all(
            "/^\s*([a-z_]+):\s*\{\s*label:\s*'((?:[^'\\\\]|\\\\.)*)'/m",
            $bloc[1],
            $entrees,
            PREG_SET_ORDER,
        );

        $meta = [];

        foreach ($entrees as $entree) {
            $meta[$entree[1]] = ['label' => str_replace("\\'", "'", $entree[2])];
        }

        return $meta;
    }
}
