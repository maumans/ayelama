<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Le formulaire client public n'expose jamais la représentation (2026-09-25).
 *
 * Trois raisons, dont deux structurelles :
 *
 *  1. `Intake/Show.jsx` rend les champs du rôle **sans évaluer `showIf`** et exige tous les
 *     `required`. Le bloc de représentation y apparaîtrait déployé en entier, et ses champs
 *     conditionnellement obligatoires seraient exigés sans condition : un client qui ne se fait
 *     pas représenter ne pourrait plus envoyer sa demande.
 *  2. Qualifier un mandat est un acte juridique — un pouvoir est-il valable, une tutelle
 *     existe-t-elle, un gérant peut-il engager la société. Ce n'est pas au client de le dire.
 *  3. L'intake ne produit qu'une seule `Partie` et n'accepte aucun téléversement : un
 *     représentant sans fiche ni procuration déposée n'apporterait rien d'exploitable.
 *
 * L'exclusion est faite **par construction** dans `getPublicIntakeFields()`. Ce test l'affirme
 * parce que le schéma est partagé avec les écrans internes : y ajouter un champ `repr_*` le
 * réexposerait au public en silence.
 *
 * Contrôle par lecture du source — comme ListesMatrimonialesTest, et pour la même raison :
 * exécuter du JavaScript depuis PHPUnit coûterait plus cher que la garantie n'en vaut.
 */
class IntakePublicRepresentationTest extends TestCase
{
    private function source(string $chemin): string
    {
        $absolu = base_path($chemin);
        $this->assertFileExists($absolu, "Fichier attendu : {$chemin}");

        return file_get_contents($absolu);
    }

    public function test_le_formulaire_public_filtre_les_champs_de_representation(): void
    {
        $source = $this->source('resources/js/lib/partiesPayload.js');

        $this->assertSame(
            1,
            preg_match('/export function getPublicIntakeFields\(.*?\n\}/s', $source, $corps),
            'getPublicIntakeFields doit être exportée par partiesPayload.js.',
        );

        $this->assertSame(
            2,
            substr_count($corps[0], 'estChampRepresentation'),
            "Les deux sources de champs du formulaire public — `roleFields` (le rôle du client) "
            . "et `extraFields` (les champs `publicIntake`) — doivent **chacune** être filtrées. "
            . "N'en filtrer qu'une laisserait la représentation atteindre le client par l'autre.",
        );
    }

    public function test_le_predicat_couvre_la_case_le_motif_et_le_sous_espace(): void
    {
        // Ce que `estChampRepresentation` doit reconnaître. Si le schéma gagne un champ de
        // représentation nommé autrement, il échapperait au filtre — d'où cette énumération,
        // confrontée au prédicat réel.
        $predicat = $this->source('resources/js/lib/clientFields.js');

        $this->assertSame(
            1,
            preg_match('/export function estChampRepresentation\(.*?\n\}/s', $predicat, $corps),
            'estChampRepresentation doit être exportée par clientFields.js.',
        );

        foreach (['est_represente', 'representation_motif', 'SOUS_PREFIXES_PERSONNE'] as $attendu) {
            $this->assertStringContainsString(
                $attendu,
                $corps[0],
                "Le prédicat doit reconnaître « {$attendu} ».",
            );
        }
    }

    public function test_aucun_champ_de_representation_nest_marque_publicIntake(): void
    {
        // Ceinture et bretelles : `publicIntake` fait entrer un champ dans `extraFields`, hors
        // du rôle du client. Le filtre l'attrape, mais marquer un `repr_*` ainsi signalerait une
        // intention contraire à la décision — autant l'interdire à la source.
        $schema = $this->source('resources/js/data/questionnaires.js');

        preg_match_all("/\{[^{}]*id:\s*'([^']*repr_[^']*|[^']*est_represente|[^']*representation_motif)'[^{}]*\}/", $schema, $champs);

        foreach ($champs[0] as $i => $declaration) {
            $this->assertStringNotContainsString(
                'publicIntake',
                $declaration,
                "Le champ « {$champs[1][$i]} » ne doit pas être exposé au formulaire public.",
            );
        }
    }
}
