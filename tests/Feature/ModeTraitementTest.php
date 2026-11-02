<?php

namespace Tests\Feature;

use App\Enums\ModeTraitement;
use App\Models\Questionnaire;
use Tests\TestCase;

/**
 * Le mode de traitement, de la case cochée jusqu'au service qui le lira.
 *
 * Addendum v1.2 § 5.2. Le choix est **enregistré avant d'être consommé** : les gabarits du
 * dépôt au rang des minutes attendent le lot 5. Ces tests verrouillent donc la seule chose qui
 * existe déjà — la lecture — pour que le jour où un service s'en sert, le contrat soit celui
 * qu'on croit : un libellé reconnu, ou la rédaction par l'étude.
 *
 * ⚠️ Le repli n'est pas une commodité. Neuf dossiers de société existent en base sans ce champ
 * ({@see \App\Enums\ModeTraitement::defaut()}) : sans repli, la lecture rendrait `null` et
 * chaque appelant devrait s'en souvenir. Avec un repli qui inventerait « dépôt », ces neuf
 * dossiers changeraient d'histoire.
 */
class ModeTraitementTest extends TestCase
{
    public function test_le_libelle_du_questionnaire_est_relu_comme_un_mode(): void
    {
        foreach (ModeTraitement::cases() as $mode) {
            $questionnaire = new Questionnaire(['donnees' => ['mode_traitement' => $mode->label()]]);

            $this->assertSame(
                $mode,
                $questionnaire->modeTraitement(),
                "Le libellé « {$mode->label()} » doit être relu comme {$mode->value}.",
            );
        }
    }

    public function test_un_questionnaire_sans_le_champ_vaut_redaction_par_letude(): void
    {
        $questionnaire = new Questionnaire(['donnees' => ['soc.denomination' => 'FAYA SARL']]);

        $this->assertSame(ModeTraitement::Redaction, $questionnaire->modeTraitement());
    }

    public function test_un_libelle_inconnu_ne_rend_pas_null(): void
    {
        // Le cas d'une orthographe dérivée — précisément ce que le test de parité interdit,
        // mais que des données déjà en base pourraient porter. Il ne doit pas faire tomber un
        // appelant qui aura déclaré un `ModeTraitement` non nullable.
        $questionnaire = new Questionnaire(['donnees' => ['mode_traitement' => 'Depot au rang des minutes']]);

        $this->assertSame(ModeTraitement::Redaction, $questionnaire->modeTraitement());
    }

    public function test_chaque_mode_explique_ce_quil_change(): void
    {
        // Un sélecteur qui n'annonce pas sa conséquence est un piège : le clerc choisit entre
        // deux intitulés sans savoir que l'un supprime les statuts mis à jour de la production.
        foreach (ModeTraitement::cases() as $mode) {
            $this->assertNotSame('', trim($mode->explication()));
            $this->assertNotSame($mode->label(), $mode->explication());
        }
    }
}
