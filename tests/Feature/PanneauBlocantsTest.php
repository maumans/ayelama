<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Le panneau « à compléter » doit emmener quelque part (2026-09-10).
 *
 * Deux défauts signalés à l'usage, invisibles pour la suite de tests parce qu'ils ne concernent ni
 * une valeur ni une règle, mais **où le regard atterrit** :
 *
 * 1. **Le panneau restait ouvert** après un clic sur « Aller ». Il recouvrait le champ vers lequel
 *    il venait de faire défiler : l'étude devait le refermer à la main pour voir où elle était
 *    arrivée. Le geste ne menait donc nulle part de visible.
 * 2. **Un champ porté par une fiche client** ne se remplit pas dans la page : il vit dans la modale
 *    de la fiche. Défiler jusqu'à la carte de la section n'y menait pas, et rien ne disait qu'il
 *    fallait ouvrir la fiche.
 *
 * Ces garde-fous surveillent la mécanique, faute de pouvoir cliquer : le repli avant défilement, le
 * rôle porté par un blocant masqué, l'action dédiée, le halo d'arrivée et l'ancre de champ.
 */
class PanneauBlocantsTest extends TestCase
{
    private function source(string $chemin): string
    {
        $absolu = resource_path($chemin);

        $this->assertFileExists($absolu, "{$chemin} doit exister.");

        return file_get_contents($absolu);
    }

    // ── Le panneau s'efface avant d'emmener ─────────────────────────────────────────────

    public function test_le_panneau_se_replie_avant_de_faire_defiler(): void
    {
        $source = $this->source('js/Components/Dossiers/BlocantsPanel.jsx');

        // `onFermer()` **puis** le défilement, séparés par l'attente du repli : défiler avant
        // viserait une position que le retrait du panneau décale de toute sa hauteur.
        $this->assertMatchesRegularExpression(
            '/onFermer\(\);\s*window\.setTimeout\(\(\) => allerAuBlocant\(ancre\), DUREE_REPLI\)/s',
            $source,
            'Le panneau doit se replier, attendre la fin du repli, puis seulement défiler.',
        );

        $this->assertStringContainsString(
            'const DUREE_REPLI',
            $source,
            'La durée du repli doit être nommée, pour rester alignée sur la transition.',
        );
    }

    public function test_aucune_ligne_ne_fait_defiler_sans_replier_le_panneau(): void
    {
        // Le défaut d'origine : `onClick={() => allerAuBlocant(...)}` directement sur la ligne.
        $source = $this->source('js/Components/Dossiers/BlocantsPanel.jsx');

        $this->assertSame(
            0,
            preg_match('/onClick=\{\(\) => allerAuBlocant\(/', $source),
            'Une ligne ne doit plus appeler allerAuBlocant sans replier le panneau : il recouvrirait '
            . 'le champ atteint.',
        );
    }

    // ── Un champ porté par une fiche ouvre la fiche ─────────────────────────────────────

    public function test_un_blocant_masque_porte_le_role_de_sa_fiche(): void
    {
        // Sans ce rôle, la seule action possible restait de défiler vers la carte de la section —
        // où le champ ne figure pas, puisqu'il vit dans la modale de la fiche.
        $this->assertStringContainsString(
            'roleClient: masque ?',
            $this->source('js/lib/blocantsEtape.js'),
        );
    }

    public function test_le_panneau_propose_d_ouvrir_la_fiche(): void
    {
        $source = $this->source('js/Components/Dossiers/BlocantsPanel.jsx');

        $this->assertStringContainsString('onOuvrirFiche', $source);
        $this->assertStringContainsString('Ouvrir la fiche', $source);
    }

    public function test_l_assistant_relie_l_action_a_la_fiche_du_role(): void
    {
        // Le panneau ne connaît pas les fiches rattachées : c'est l'assistant qui traduit un rôle
        // en fiche et ouvre la modale d'édition.
        $source = $this->source('js/Pages/Dossiers/Create.jsx');

        $this->assertStringContainsString('onOuvrirFiche', $source);
        $this->assertMatchesRegularExpression(
            '/onOuvrirFiche=\{\(role\).*?clientLinks\[role\].*?setEditingClient/s',
            $source,
            'L\'action doit ouvrir la fiche du rôle concerné.',
        );
    }

    // ── On voit où l'on arrive ──────────────────────────────────────────────────────────

    public function test_le_champ_atteint_est_signale_par_un_halo(): void
    {
        // Défiler ne dit pas *où* l'on a atterri : sur une carte de dix champs, l'œil doit encore
        // chercher. Le halo dure le temps d'être vu puis s'efface.
        $this->assertStringContainsString(
            "classList.add('halo-cible')",
            $this->source('js/lib/blocantsEtape.js'),
        );

        $css = $this->source('css/app.css');

        $this->assertStringContainsString('.halo-cible', $css);
        $this->assertStringContainsString('@keyframes halo-cible', $css);

        // Un utilisateur qui a demandé moins d'animations garde le repère, sans le mouvement.
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce\)\s*\{\s*\.halo-cible/s',
            $css,
        );
    }

    public function test_les_trois_ecrans_ancrent_leurs_champs(): void
    {
        // Le halo se pose sur l'enveloppe du champ — c'est elle qui a la taille du bloc, libellé
        // compris. Sans `data-champ`, il ne cerclerait que le contrôle.
        foreach ([
            'js/Pages/Dossiers/Create.jsx',
            'js/Pages/Dossiers/Show.jsx',
            'js/Pages/Intake/Show.jsx',
        ] as $ecran) {
            $this->assertStringContainsString(
                'data-champ',
                $this->source($ecran),
                "{$ecran} doit marquer l'enveloppe de ses champs.",
            );
        }

        $this->assertStringContainsString(
            "closest('[data-champ]')",
            $this->source('js/lib/blocantsEtape.js'),
        );
    }
}
