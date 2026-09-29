<?php

namespace Tests\Feature;

use App\Services\ClientProjectionService;
use Tests\TestCase;

/**
 * Tout ce que la projection écrit doit être déclaré résolvable (2026-09-25).
 *
 * `tools/verifier-balises.php` refuse un modèle Word qui emploie une balise absente de
 * `docs/kit-modeles/balises-resolvables.txt`. Si le sous-espace du représentant y manquait,
 * l'outil dirait `${pp.repr_prenom_nom}` non résolvable — et l'étude en conclurait que la
 * balise n'existe pas, alors que la projection l'écrit à chaque enregistrement.
 *
 * Le piège est réel : la projection re-préfixe **toute** l'identité d'une fiche
 * (`repr_nom`, `repr_adresse`, `repr_identite_notariale`…), là où le questionnaire n'en déclare
 * qu'une vingtaine en saisie. Se fier aux seuls champs du schéma aurait laissé la moitié des
 * balises hors de la liste.
 */
class BalisesRepresentationTest extends TestCase
{
    /** @return list<string> */
    private function balisesResolvables(): array
    {
        $fichier = base_path('docs/kit-modeles/balises-resolvables.txt');
        $this->assertFileExists($fichier, 'Lancez : node tools/generer-balises-resolvables.mjs');

        return array_filter(array_map('trim', file($fichier)));
    }

    public function test_le_sous_espace_du_representant_est_declare_resolvable(): void
    {
        $declarees = array_flip($this->balisesResolvables());

        // Un préfixe de section scalaire et un bloc répétable : les deux formes que la
        // projection sait écrire.
        foreach (['pp', 'ger', 'associes'] as $emplacement) {
            $this->assertArrayHasKey(
                "{$emplacement}.comparution",
                $declarees,
                "La mention de comparution de « {$emplacement} » n'est pas déclarée résolvable : "
                . 'un modèle qui l\'emploie serait refusé par verifier-balises.php.',
            );

            foreach (ClientProjectionService::suffixesRepresentation() as $suffixe) {
                if ($suffixe === 'comparution') {
                    continue; // Traité ci-dessus, sans le préfixe `repr_`.
                }

                $this->assertArrayHasKey(
                    "{$emplacement}.{$suffixe}",
                    $declarees,
                    "La balise « {$emplacement}.{$suffixe} » est écrite par la projection mais "
                    . 'absente de balises-resolvables.txt. Relancez '
                    . 'node tools/generer-balises-resolvables.mjs.',
                );
            }
        }
    }

    public function test_la_liste_nest_pas_plus_vieille_que_le_schema(): void
    {
        // Même garde que `verifier-balises.php`, appliquée ici pour qu'un oubli de régénération
        // échoue à l'intégration plutôt qu'au moment où un confrère lance l'outil.
        $liste  = base_path('docs/kit-modeles/balises-resolvables.txt');
        $schema = resource_path('js/data/questionnaires.js');

        $this->assertGreaterThanOrEqual(
            filemtime($schema),
            filemtime($liste),
            'balises-resolvables.txt est plus ancien que questionnaires.js — régénérez-le.',
        );
    }
}
