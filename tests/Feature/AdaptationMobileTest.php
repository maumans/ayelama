<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * L'interface doit tenir dans la zone visible d'un téléphone (2026-09-10).
 *
 * Deux défauts signalés à l'usage, tous deux **invisibles pour la suite de tests** et pour
 * `vite build` — d'où ces garde-fous de structure, dans l'esprit de `PariteRenduQuestionnaireTest`.
 *
 * 1. **Le menu passait sous la barre d'état.** Les layouts employaient `h-screen`, c'est-à-dire
 *    `100vh` : la hauteur de l'écran **barre d'adresse masquée**, plus grande que la zone
 *    réellement visible, et dont l'écart varie selon l'appareil — d'où « sur certains écrans ça
 *    loge, sur d'autres non ». Avec `overflow-hidden` sur la racine, ce qui dépassait n'était même
 *    pas rattrapable par défilement.
 *
 * 2. **La page zoomait à la connexion.** Sous 16 px, iOS Safari agrandit la page à la mise au point
 *    d'un champ et ne la réduit pas ensuite. Le champ e-mail était en `text-sm` (14 px), d'où le
 *    tableau de bord agrandi juste après.
 */
class AdaptationMobileTest extends TestCase
{
    /** Les layouts qui posent la hauteur de la fenêtre. */
    private const LAYOUTS = [
        'js/Layouts/AppLayout.jsx',
        'js/Layouts/GuestLayout.jsx',
        'js/Layouts/GuestPublicLayout.jsx',
    ];

    private function source(string $chemin): string
    {
        $absolu = resource_path($chemin);

        $this->assertFileExists($absolu, "{$chemin} doit exister.");

        return file_get_contents($absolu);
    }

    // ── La hauteur de la fenêtre ────────────────────────────────────────────────────────

    public function test_aucun_layout_nemploie_la_hauteur_de_fenetre_statique(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $source = $this->source($layout);

            // On cible l'usage réel dans un `className`, pas une mention en commentaire —
            // AppLayout explique précisément pourquoi `h-screen` a été abandonné.
            $this->assertSame(
                0,
                preg_match('/className=(?:"|\{cn\()[^"}]*\bh-screen\b/', $source),
                "{$layout} emploie h-screen (100vh) : sur téléphone, cette hauteur dépasse la zone "
                . 'visible et le contenu passe sous la barre d\'état. Employer h-dvh.',
            );
        }
    }

    public function test_les_layouts_emploient_la_hauteur_dynamique(): void
    {
        foreach (self::LAYOUTS as $layout) {
            $this->assertStringContainsString(
                'h-dvh',
                $this->source($layout),
                "{$layout} doit poser sa hauteur en h-dvh, qui suit la zone réellement visible.",
            );
        }
    }

    public function test_le_socle_css_emploie_aussi_la_hauteur_dynamique(): void
    {
        // `html { height: 100% }` se résout sur le bloc conteneur initial — la grande fenêtre sur
        // mobile. Le corriger dans les layouts sans corriger le socle laissait le défaut entier.
        $css = $this->source('css/app.css');

        $this->assertStringContainsString('height: 100dvh', $css);
        $this->assertStringContainsString(
            'height: 100vh',
            $css,
            'Un repli 100vh doit précéder 100dvh pour les navigateurs qui ignorent l\'unité.',
        );
    }

    public function test_la_page_ged_deduit_sa_hauteur_du_token_declare(): void
    {
        // 🐛 `theme(spacing.header)` était employé alors que le token **n'existait pas** :
        // Tailwind émettait un avertissement et **ne générait pas la classe**. La page n'avait donc
        // aucune hauteur, et son défilement interne n'a jamais fonctionné. Une classe non générée
        // est un défaut silencieux — exactement ce que la règle 2 de CLAUDE.md demande de rendre
        // bruyant.
        $config = file_get_contents(base_path('tailwind.config.js'));

        $this->assertMatchesRegularExpression(
            '/spacing:\s*\{[^}]*header:/s',
            $config,
            'Le token spacing.header doit être déclaré : Ged/Index.jsx en dépend.',
        );

        $this->assertStringContainsString(
            'calc(100dvh-theme(spacing.header))',
            $this->source('js/Pages/Ged/Index.jsx'),
        );
    }

    // ── L'encoche ───────────────────────────────────────────────────────────────────────

    public function test_les_marges_dencoche_sont_declarees_une_fois(): void
    {
        $css = $this->source('css/app.css');

        foreach (['pt-encoche', 'pb-encoche', 'pl-encoche'] as $utilitaire) {
            $this->assertStringContainsString(".{$utilitaire}", $css);
        }

        // Le repli `0px` est ce qui rend la marge inoffensive sur un écran sans découpe : sans lui,
        // `env()` non résolu invaliderait la déclaration.
        $this->assertStringContainsString('env(safe-area-inset-top, 0px)', $css);
    }

    public function test_les_barres_fixes_portent_une_marge_dencoche(): void
    {
        // C'est le menu latéral et la barre supérieure qui se confondaient avec l'heure et la
        // batterie : ce sont eux qui touchent le bord haut de l'écran.
        $source = $this->source('js/Layouts/AppLayout.jsx');

        $this->assertStringContainsString('pt-encoche', $source);
        $this->assertSame(
            2,
            preg_match_all('/pt-encoche/', $source),
            'La barre supérieure **et** le menu latéral doivent porter la marge d\'encoche.',
        );
    }

    public function test_le_viewport_ne_couvre_pas_les_zones_systeme(): void
    {
        // `viewport-fit=cover` demanderait au navigateur de passer **sous** les zones système :
        // l'inverse du but. On garde le comportement par défaut, et les marges d'encoche protègent
        // des navigateurs qui couvrent d'eux-mêmes.
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringNotContainsString('viewport-fit=cover', $blade);
    }

    // ── Le zoom au premier clic ─────────────────────────────────────────────────────────

    public function test_les_champs_font_seize_pixels_sur_petit_ecran(): void
    {
        $css = $this->source('css/app.css');

        $this->assertMatchesRegularExpression(
            '/@media\s*\(max-width:\s*767px\)\s*\{[^}]*input[^{]*\{[^}]*font-size:\s*16px/s',
            $css,
            'Sous 16 px, iOS agrandit la page à la mise au point d\'un champ et ne la réduit pas.',
        );
    }

    public function test_le_pincement_pour_zoomer_reste_possible(): void
    {
        // Alternative écartée : `maximum-scale=1` / `user-scalable=no`. Une ligne, mais sur un
        // outil où l'on relit des actes et des pièces d'identité, désactiver le zoom est une
        // régression d'accessibilité réelle (WCAG 1.4.4).
        $blade = file_get_contents(resource_path('views/app.blade.php'));

        $this->assertStringNotContainsString('maximum-scale', $blade);
        $this->assertStringNotContainsString('user-scalable', $blade);
    }

    // ── Le dialogue et ses menus flottants ──────────────────────────────────────────────

    public function test_un_dialogue_ne_se_ferme_pas_sur_un_menu_flottant(): void
    {
        // 🐛 Choisir un moyen de paiement fermait le dialogue : le menu d'un `Select` est **portalé**
        // hors du dialogue — dans le DOM il en est frère, pas descendant — si bien que le clic était
        // pris pour un clic « dehors ». La saisie en cours était perdue.
        //
        // La garde vit dans la primitive, une fois pour toutes les modales : cliquer dans le menu
        // d'un champ du dialogue n'est pas cliquer dehors.
        $source = file_get_contents(resource_path('js/Components/ui/dialog.jsx'));

        $this->assertStringContainsString(
            'onInteractOutside',
            $source,
            'DialogContent doit filtrer les interactions venues d\'un calque flottant.',
        );

        // Les attributs surveillés doivent être ceux que Radix **émet réellement** : un sélecteur
        // inventé ne filtrerait rien, en silence. Ceux-ci ont été relevés dans les paquets installés.
        foreach ([
            'data-radix-popper-content-wrapper',
            'data-radix-select-viewport',
            'data-radix-menu-content',
        ] as $attribut) {
            $this->assertStringContainsString($attribut, $source);
        }
    }

    public function test_les_attributs_surveilles_existent_bien_dans_radix(): void
    {
        // Le garde-fou du garde-fou : si une mise à jour de Radix renommait ces attributs, le
        // filtre cesserait d'opérer **sans erreur** et le défaut reviendrait tel quel.
        $paquets = [
            'data-radix-popper-content-wrapper' => 'react-popper',
            'data-radix-select-viewport'        => 'react-select',
            'data-radix-menu-content'           => 'react-menu',
        ];

        foreach ($paquets as $attribut => $paquet) {
            $dist = base_path("node_modules/@radix-ui/{$paquet}/dist/index.mjs");

            if (! is_file($dist)) {
                $this->markTestSkipped("@radix-ui/{$paquet} absent — dépendances non installées.");
            }

            $this->assertStringContainsString(
                $attribut,
                file_get_contents($dist),
                "@radix-ui/{$paquet} n'émet plus « {$attribut} » : le filtre du dialogue ne protège "
                . 'plus rien. Relever le nouvel attribut et mettre CALQUES_FLOTTANTS à jour.',
            );
        }
    }

    // ── Vestige ─────────────────────────────────────────────────────────────────────────


    public function test_le_layout_breeze_mort_a_bien_disparu(): void
    {
        // Plus aucune page ne l'importait depuis la décision #4 ; le laisser en place, c'était
        // offrir un modèle périmé — en `min-h-screen` — à la prochaine page créée.
        $this->assertFileDoesNotExist(resource_path('js/Layouts/AuthenticatedLayout.jsx'));
    }
}
