<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Un seul moteur de rendu de champ, pour les trois écrans (2026-09-09).
 *
 * ⚠️ **Le défaut que ce fichier interdit est le plus coûteux rencontré sur ce projet.** Le rendu des
 * champs de questionnaire était recopié trois fois — assistant de création, modal « Modifier le
 * questionnaire », formulaire public d'intake — et **quatre comportements de suite** n'ont été
 * implémentés que d'un côté :
 *
 *   1. `checkbox_group` — absent de deux écrans sur trois ;
 *   2. la cascade géo ville → commune → quartier — ajoutée à l'assistant seul ;
 *   3. `readonly` — le champ « Pays » restait modifiable dans l'assistant alors qu'il était
 *      verrouillé dans le modal et sur le formulaire public ;
 *   4. la cohérence des dates — nulle part, alors que la fiche client la contrôlait.
 *
 * Chaque fois, l'étude l'a découvert à l'usage et a dû le signaler. Aucun test ne pouvait l'attraper,
 * puisqu'il n'y avait pas de règle à vérifier : il y avait trois implémentations, toutes « justes »
 * chez elles. Ce fichier crée la règle.
 */
class PariteRenduQuestionnaireTest extends TestCase
{
    /** Les trois écrans qui affichent des champs de questionnaire. */
    private const ECRANS = [
        'js/Pages/Dossiers/Create.jsx',
        'js/Pages/Dossiers/Show.jsx',
        'js/Pages/Intake/Show.jsx',
    ];

    private function source(string $chemin): string
    {
        $absolu = resource_path($chemin);

        $this->assertFileExists($absolu, "L'écran {$chemin} doit exister.");

        return file_get_contents($absolu);
    }

    public function test_les_trois_ecrans_emploient_le_moteur_partage(): void
    {
        foreach (self::ECRANS as $ecran) {
            $this->assertStringContainsString(
                'ChampQuestionnaire',
                $this->source($ecran),
                "{$ecran} doit rendre ses champs via ChampQuestionnaire, jamais par sa propre copie.",
            );
        }
    }

    public function test_aucun_ecran_ne_redeclare_le_rendu_par_type_de_champ(): void
    {
        // Le marqueur du défaut : une cascade de `field.type === '…'` dans un écran signifie qu'il
        // s'est remis à décider lui-même du rendu, et la divergence recommence.
        foreach (self::ECRANS as $ecran) {
            $trouves = preg_match_all(
                "/field\.type\s*===\s*'/",
                $this->source($ecran),
                $captures,
            );

            $this->assertSame(
                0,
                $trouves,
                "{$ecran} redéclare le rendu de {$trouves} type(s) de champ. Ajoutez-le à "
                . 'ChampQuestionnaire, ou passez par rendreRepeatable / rendreChoixMultiple.',
            );
        }
    }

    public function test_le_moteur_partage_couvre_tous_les_types_declares(): void
    {
        // Un type déclaré dans les questionnaires mais absent du moteur sortirait en champ texte,
        // silencieusement — c'est ce qui est arrivé à `checkbox_group`.
        $moteur = $this->source('js/Components/Questionnaire/ChampQuestionnaire.jsx');
        $schemas = $this->source('js/data/questionnaires.js');

        preg_match_all("/type: '([a-z_]+)'/", $schemas, $captures);
        $typesDeclares = array_unique($captures[1]);

        $this->assertNotEmpty($typesDeclares);

        foreach ($typesDeclares as $type) {
            // `text` est le repli du moteur : il n'a pas de branche nommée, et c'est voulu.
            if ($type === 'text') {
                continue;
            }

            $this->assertStringContainsString(
                "'{$type}'",
                $moteur,
                "Le type « {$type} » est déclaré dans questionnaires.js mais ChampQuestionnaire ne "
                . 'le traite pas : il serait rendu en champ texte sans erreur.',
            );
        }
    }

    public function test_les_controles_propres_au_questionnaire_ne_sont_plus_importes_par_les_ecrans(): void
    {
        // Un import résiduel est le premier pas vers une nouvelle copie locale : l'écran a de
        // nouveau sous la main de quoi rendre un champ lui-même.
        //
        // ⚠️ Seuls les contrôles **propres au questionnaire** sont interdits. `date-field`,
        // `number-field` et `phone-field` servent aussi ailleurs — l'échéance du dossier, la date de
        // signature, un montant de paiement — et les proscrire ici serait une règle fausse.
        foreach (self::ECRANS as $ecran) {
            $source = $this->source($ecran);

            foreach (['lieu-select', 'champ-verrouille'] as $controle) {
                $this->assertSame(
                    0,
                    preg_match("#import .*{$controle}'#", $source),
                    "{$ecran} importe encore {$controle} : ce contrôle appartient à ChampQuestionnaire.",
                );
            }
        }
    }

    public function test_le_moteur_partage_porte_les_quatre_comportements_perdus(): void
    {
        // Les quatre défauts nommés dans l'en-tête, vérifiés là où ils vivent désormais.
        $moteur = $this->source('js/Components/Questionnaire/ChampQuestionnaire.jsx');

        $this->assertStringContainsString('roleGeo', $moteur, 'Cascade géo.');
        $this->assertStringContainsString('patchGeo', $moteur, 'Réinitialisation des niveaux inférieurs.');
        $this->assertStringContainsString('ChampVerrouille', $moteur, 'Champ readonly déverrouillable.');
        $this->assertStringContainsString('checkbox_group', $moteur, 'Choix multiple.');
    }

    public function test_les_blocants_sont_calcules_par_les_ecrans_authentifies(): void
    {
        // La cohérence des dates et les champs requis passent par `blocantsEtape`. Le modal ne
        // l'appelait pas : on y enregistrait un questionnaire incohérent sans le savoir.
        foreach (['js/Pages/Dossiers/Create.jsx', 'js/Pages/Dossiers/Show.jsx'] as $ecran) {
            $this->assertStringContainsString(
                'blocantsEtape',
                $this->source($ecran),
                "{$ecran} doit énumérer ce qui bloque, avec les mêmes règles que l'autre écran.",
            );
        }
    }
}
