<?php

namespace Tests\Feature;

use App\Models\Partie;
use Tests\TestCase;

/**
 * « Rôle → pièces requises » — une règle, un détenteur (2026-09-24).
 *
 * La règle était recopiée à **quatre** endroits, et les quatre avaient divergé :
 *
 *  • `RepeatableGroup.jsx` ne couvrait que 3 des 7 rôles — cédants, cessionnaires,
 *    souscripteurs et gérants entrants n'affichaient aucune pièce avant création, alors que
 *    le serveur les exige ensuite et bloque la sortie d'Initialisation ;
 *  • `Create.jsx` et `Show.jsx` portaient une liste blanche de sept rôles (bailleur,
 *    locataire, vendeur, acheteur, liquidateur, créancier, débiteur) dont **aucun** n'a de
 *    jeu déclaré côté PHP : la section restait vide sans que rien ne le dise ;
 *  • `show()` ne passait même pas la prop, si bien qu'ajouter une personne depuis la modale
 *    d'édition n'offrait jamais de déposer ses pièces.
 *
 * Aucune de ces divergences n'était visible : une pièce jamais demandée ne produit pas
 * d'erreur, elle produit un dossier bloqué plus tard, à l'étape suivante, sans motif lisible.
 * D'où ces tests, qui interdisent la recopie plutôt que de vérifier qu'elle est correcte.
 *
 * Modèle suivi : ListesMatrimonialesTest (extraction par regex des sources JS, sans exécuter
 * de JavaScript) et PariteRenduQuestionnaireTest (interdiction de redéclarer).
 */
class PiecesRequisesParRoleTest extends TestCase
{
    private function source(string $chemin): string
    {
        $absolu = base_path($chemin);
        $this->assertFileExists($absolu, "Fichier attendu : {$chemin}");

        return file_get_contents($absolu);
    }

    /** Le code d'un module JS, commentaires retirés. */
    private function codeSansCommentaires(string $chemin): string
    {
        return preg_replace('#(//[^\n]*|/\*.*?\*/)#s', '', $this->source($chemin));
    }

    /** Les `clientRole: '…'` déclarés par le schéma de questionnaire. */
    private function rolesDuSchema(): array
    {
        preg_match_all(
            "/clientRole:\s*'([^']+)'/",
            $this->source('resources/js/data/questionnaires.js'),
            $captures,
        );

        $roles = array_values(array_unique($captures[1]));
        sort($roles);

        $this->assertNotEmpty($roles, 'Aucun clientRole trouvé — le regex a-t-il cessé de correspondre ?');

        return $roles;
    }

    // ── Le garde-fou central ─────────────────────────────────────────────────

    public function test_le_resolveur_js_ne_porte_aucune_regle_en_dur(): void
    {
        // Commentaires retirés : ils nomment délibérément rôles et jeux pour expliquer
        // d'où vient le module.
        $code = $this->codeSansCommentaires('resources/js/lib/piecesRequises.js');

        foreach (array_keys(Partie::piecesRequisesParCle()) as $jeu) {
            $this->assertStringNotContainsString(
                "'{$jeu}'",
                $code,
                "Le résolveur JS ne doit connaître aucun nom de jeu. « {$jeu} » y est écrit en dur : "
                . 'la règle doit venir du serveur (Partie::reglesPiecesRequises()).',
            );
        }

        foreach (array_keys(Partie::jeuParRole()) as $role) {
            $this->assertStringNotContainsString(
                "'{$role}'",
                $code,
                "Le résolveur JS ne doit connaître aucun rôle. « {$role} » y est écrit en dur.",
            );
        }
    }

    public function test_aucun_ecran_ne_recalcule_la_categorie_de_pieces(): void
    {
        $ecrans = [
            'resources/js/Pages/Dossiers/Create.jsx',
            'resources/js/Pages/Dossiers/Show.jsx',
            'resources/js/Components/ui/RepeatableGroup.jsx',
        ];

        foreach ($ecrans as $ecran) {
            $code = $this->codeSansCommentaires($ecran);

            foreach (['categorieRole', "'associe_physique'", "'associe_morale'"] as $marqueur) {
                $this->assertStringNotContainsString(
                    $marqueur,
                    $code,
                    "{$ecran} recompose la règle « rôle → pièces » ({$marqueur}). "
                    . 'Passez par piecesRequisesPour() de lib/piecesRequises.js.',
                );
            }
        }
    }

    public function test_les_appelants_passent_par_le_resolveur(): void
    {
        foreach ([
            'resources/js/Pages/Dossiers/Create.jsx',
            'resources/js/Pages/Dossiers/Show.jsx',
            'resources/js/Components/ui/RepeatableGroup.jsx',
        ] as $ecran) {
            $this->assertStringContainsString(
                'piecesRequisesPour',
                $this->source($ecran),
                "{$ecran} doit résoudre ses pièces via piecesRequisesPour().",
            );
        }

        // Une seule traduction « Personne morale » vers `morale`. Il y en avait deux.
        $this->assertStringContainsString(
            'typePersonneCanonique',
            $this->source('resources/js/lib/partiesPayload.js'),
        );
    }

    // ── Couverture des rôles ─────────────────────────────────────────────────

    public function test_tout_clientRole_du_schema_est_classe_cote_serveur(): void
    {
        $classes = array_merge(array_keys(Partie::jeuParRole()), Partie::rolesSansPieces());

        foreach ($this->rolesDuSchema() as $role) {
            $this->assertContains(
                $role,
                $classes,
                "Le rôle « {$role} » du questionnaire n'est classé nulle part. Ajoutez-le à "
                . "Partie::JEU_PAR_ROLE s'il exige des pièces, ou à Partie::ROLES_SANS_PIECES "
                . "avec le motif. Un rôle oublié est indistinguable d'un rôle sans pièce — "
                . 'c\'est ainsi que cedant, cessionnaire, souscripteur et gerant_entrant sont '
                . 'restés sans checklist.',
            );
        }
    }

    public function test_un_role_ne_peut_pas_etre_a_la_fois_avec_et_sans_pieces(): void
    {
        $this->assertSame(
            [],
            array_values(array_intersect(array_keys(Partie::jeuParRole()), Partie::rolesSansPieces())),
            'Un rôle déclaré dans les deux tables rend la règle ambiguë.',
        );
    }

    // ── La règle servie ──────────────────────────────────────────────────────

    public function test_les_deux_ecrans_recoivent_la_meme_regle(): void
    {
        // Assertion sur la source et non sur une requête HTTP : ce qu'on veut interdire, c'est
        // que l'un des deux écrans reconstruise sa prop autrement — ce qui était précisément
        // le cas, show() ne la passant pas du tout.
        $controleur = $this->source('app/Http/Controllers/DossierController.php');

        $this->assertSame(
            2,
            substr_count($controleur, "'piecesRequises' => Partie::reglesPiecesRequises()"),
            'create() et show() doivent servir la règle par le même accesseur, et aucun autre.',
        );
    }

    public function test_la_regle_servie_est_complete_et_coherente(): void
    {
        $regles = Partie::reglesPiecesRequises();

        $this->assertSame(['jeux', 'parRole', 'moraleParRole'], array_keys($regles));

        foreach ([...array_values($regles['parRole']), ...array_values($regles['moraleParRole'])] as $jeu) {
            $this->assertArrayHasKey(
                $jeu,
                $regles['jeux'],
                "Le jeu « {$jeu} » est désigné par une règle mais absent de PIECES_REQUISES : "
                . 'le frontend résoudrait une liste vide sans le signaler.',
            );
        }
    }

    public function test_une_personne_morale_recoit_le_jeu_societe(): void
    {
        // Le comportement que le résolveur JS doit reproduire à l'identique.
        $this->assertArrayHasKey('pv_ag', Partie::piecesRequisesPour('associe', 'morale'));
        $this->assertArrayHasKey('cni', Partie::piecesRequisesPour('associe', 'physique'));

        // `gerant_entrant` n'admet pas la personne morale : la gérance d'une SARL est exercée
        // par une personne physique.
        $this->assertArrayNotHasKey('gerant_entrant', Partie::jeuMoraleParRole());
    }
}
