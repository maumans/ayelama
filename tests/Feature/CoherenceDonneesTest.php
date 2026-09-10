<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\CoherenceDonneesService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cohérence des dates de questionnaire — côté serveur (2026-09-09).
 *
 * `questionnaires.donnees` était validé `['required', 'array']` : **toute** la validation du
 * questionnaire vivait dans le navigateur, et les 25 champs de date n'étaient contrôlés nulle part.
 * Un acte authentique pouvait donc porter une pièce expirant avant d'avoir été délivrée.
 *
 * ⚠️ Ce qui n'est **pas** vérifié ici est délibéré : les champs obligatoires dépendent des `showIf`
 * du schéma, qui vit en JavaScript — l'exiger côté serveur imposerait de dupliquer ce schéma.
 */
class CoherenceDonneesTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CoherenceDonneesService
    {
        return app(CoherenceDonneesService::class);
    }

    // ── Les quatre contrôles d'un groupe d'état civil ───────────────────────────────────

    public function test_une_expiration_anterieure_a_la_delivrance_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs([
            'pp.piece_delivree_le' => '10/10/2020',
            'pp.piece_expire_le'   => '10/10/2019',
        ]);

        $this->assertArrayHasKey('donnees.pp.piece_expire_le', $erreurs);
    }

    public function test_une_delivrance_anterieure_a_la_naissance_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs([
            'pp.date_naissance'    => '25/12/2000',
            'pp.piece_delivree_le' => '25/12/1995',
        ]);

        $this->assertArrayHasKey('donnees.pp.piece_delivree_le', $erreurs);
    }

    public function test_une_naissance_future_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs(['ger.date_naissance' => now()->addYear()->format('d/m/Y')]);

        $this->assertArrayHasKey('donnees.ger.date_naissance', $erreurs);
    }

    public function test_une_delivrance_future_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs(['acq.piece_delivree_le' => now()->addMonth()->format('d/m/Y')]);

        $this->assertArrayHasKey('donnees.acq.piece_delivree_le', $erreurs);
    }

    public function test_un_questionnaire_coherent_ne_produit_aucune_erreur(): void
    {
        $this->assertSame([], $this->service()->erreurs([
            'pp.date_naissance'    => '13/05/1985',
            'pp.piece_delivree_le' => '10/10/2020',
            'pp.piece_expire_le'   => '10/10/2030',
            'pp.ne_a'              => 'Conakry',
        ]));
    }

    public function test_une_piece_expiree_reste_acceptee(): void
    {
        // Décision reprise de la fiche client : l'étude doit pouvoir consigner la situation réelle
        // du client avant de lui demander un renouvellement. Seule l'**incohérence** bloque.
        $this->assertSame([], $this->service()->erreurs([
            'pp.piece_delivree_le' => '10/10/2010',
            'pp.piece_expire_le'   => '10/10/2015',
        ]));
    }

    // ── Le format français, celui de `donnees` ──────────────────────────────────────────

    public function test_une_date_francaise_au_jour_superieur_a_douze_est_lue_correctement(): void
    {
        // `Carbon::parse('13/05/1985')` échoue et `parse('01/04/1985')` donne le 4 janvier : c'est
        // le défaut qui a inversé sept dates en base. Le service lit explicitement `d/m/Y`.
        $this->assertSame([], $this->service()->erreurs([
            'pp.date_naissance'    => '13/05/1985',
            'pp.piece_delivree_le' => '31/12/2020',
        ]));
    }

    public function test_le_premier_avril_n_est_pas_lu_comme_le_quatre_janvier(): void
    {
        // Naissance 01/04/2000, pièce délivrée le 15/02/2000 : incohérent si l'on lit
        // « 1er avril », cohérent si l'on lit « 4 janvier ». L'erreur doit donc être signalée.
        $erreurs = $this->service()->erreurs([
            'pp.date_naissance'    => '01/04/2000',
            'pp.piece_delivree_le' => '15/02/2000',
        ]);

        $this->assertArrayHasKey('donnees.pp.piece_delivree_le', $erreurs);
    }

    public function test_une_date_iso_est_toleree(): void
    {
        // Un import peut en porter : on ne refuse pas la donnée, on la lit.
        $erreurs = $this->service()->erreurs([
            'pp.piece_delivree_le' => '2020-10-10',
            'pp.piece_expire_le'   => '2019-10-10',
        ]);

        $this->assertArrayHasKey('donnees.pp.piece_expire_le', $erreurs);
    }

    public function test_une_saisie_illisible_ne_produit_pas_d_incoherence_inventee(): void
    {
        $this->assertSame([], $this->service()->erreurs(['pp.date_naissance' => 'à compléter']));
    }

    // ── Les blocs répétables ────────────────────────────────────────────────────────────

    public function test_les_dates_d_un_bloc_repetable_sont_verifiees(): void
    {
        // Les associés, gérants et souscripteurs portent leurs champs **sans préfixe**, dans un
        // tableau. Ne pas y descendre laissait la moitié des personnes d'un dossier non vérifiée.
        $erreurs = $this->service()->erreurs([
            'associes' => [
                ['nom' => 'DIALLO', 'piece_delivree_le' => '10/10/2020', 'piece_expire_le' => '10/10/2019'],
            ],
        ]);

        $this->assertArrayHasKey('donnees.associes.0.piece_expire_le', $erreurs);
    }

    public function test_l_entree_fautive_d_un_bloc_repetable_est_nommee(): void
    {
        $erreurs = $this->service()->erreurs([
            'associes' => [
                ['nom' => 'SOW',     'piece_delivree_le' => '10/10/2020', 'piece_expire_le' => '10/10/2030'],
                ['nom' => 'CAMARA',  'date_naissance'    => now()->addYear()->format('d/m/Y')],
            ],
        ]);

        $this->assertArrayHasKey('donnees.associes.1.date_naissance', $erreurs);
        $this->assertArrayNotHasKey('donnees.associes.0.date_naissance', $erreurs);
    }

    // ── Les dates isolées, et celles qu'on ne contraint pas ──────────────────────────────

    public function test_une_constitution_future_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs(['soc.date_constitution' => now()->addYear()->format('d/m/Y')]);

        $this->assertArrayHasKey('donnees.soc.date_constitution', $erreurs);
    }

    public function test_une_date_d_effet_anterieure_a_l_assemblee_est_refusee(): void
    {
        $erreurs = $this->service()->erreurs([
            'ag.date'       => '10/05/2026',
            'ag.date_effet' => '01/05/2026',
        ]);

        $this->assertArrayHasKey('donnees.ag.date_effet', $erreurs);
    }

    public function test_une_prise_d_effet_de_bail_future_est_acceptee(): void
    {
        // Non-régression du périmètre : une prise d'effet à venir est le cas normal d'un bail, et la
        // contraindre casserait la saisie. Même chose pour un versement d'augmentation de capital.
        $this->assertSame([], $this->service()->erreurs([
            'bail.date_prise_effet'             => now()->addYear()->format('d/m/Y'),
            'modif.augmentation_date_versement' => now()->addMonths(6)->format('d/m/Y'),
        ]));
    }

    public function test_les_dates_d_acte_non_arbitrees_restent_libres(): void
    {
        // Délibérément non contraintes : je ne sais pas quelle borne leur donner, et une contrainte
        // inventée est pire qu'une contrainte absente.
        $this->assertSame([], $this->service()->erreurs([
            'dissolution.date_assemblee'    => now()->addYear()->format('d/m/Y'),
            'hypotheque.date_acte'          => now()->addYear()->format('d/m/Y'),
            'modif.date_cession'            => now()->addYear()->format('d/m/Y'),
            'gerant_sortant.date_cessation' => now()->addYear()->format('d/m/Y'),
        ]));
    }

    // ── Le point d'entrée HTTP ──────────────────────────────────────────────────────────

    public function test_la_mise_a_jour_du_questionnaire_refuse_des_dates_incoherentes(): void
    {
        $clerc   = $this->clerc();
        $dossier = $this->dossier($clerc);

        $this->actingAs($clerc)
            ->patchJson("/dossiers/{$dossier->reference}/questionnaire", [
                'donnees' => [
                    'pp.piece_delivree_le' => '10/10/2020',
                    'pp.piece_expire_le'   => '10/10/2019',
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('donnees.pp.piece_expire_le');
    }

    public function test_la_mise_a_jour_accepte_un_questionnaire_coherent(): void
    {
        $clerc   = $this->clerc();
        $dossier = $this->dossier($clerc);

        $this->actingAs($clerc)
            ->patchJson("/dossiers/{$dossier->reference}/questionnaire", [
                'donnees' => [
                    'pp.piece_delivree_le' => '10/10/2020',
                    'pp.piece_expire_le'   => '10/10/2030',
                ],
            ])
            // 302 : réponse normale d'un PATCH Inertia. Ce qui compte est l'absence d'erreur de
            // validation **et** la persistance — cet enregistrement renvoyait un 500 muet, la
            // transaction annulant la saisie (`$projection` non capturé dans la closure).
            ->assertSessionHasNoErrors();

        $this->assertSame(
            '10/10/2030',
            $dossier->fresh()->questionnaire->donnees['pp.piece_expire_le'],
        );
    }

    // ── Garde-fou de duplication PHP / JS ───────────────────────────────────────────────

    public function test_les_groupes_de_dates_php_et_js_sont_identiques(): void
    {
        // La duplication est inévitable — le schéma des questionnaires vit en JavaScript — donc
        // **gardée**. Sans ce test, un groupe ajouté d'un seul côté serait contrôlé à l'écran et pas
        // au serveur, ou l'inverse. C'est exactement le défaut qui a coûté quatre divergences.
        $this->assertSame(
            $this->declarationJs('PAIRES_DATES'),
            array_map(
                fn (array $g) => array_values(array_filter([$g['naissance'], $g['delivree'], $g['expire']])),
                CoherenceDonneesService::GROUPES_DATES,
            ),
        );
    }

    public function test_les_contraintes_php_et_js_sont_identiques(): void
    {
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));

        $this->assertSame(
            1,
            preg_match("/export const CONTRAINTES_DATES = \[(.*?)
\];/s", $source, $captures),
            'CONTRAINTES_DATES doit être exportée par questionnaires.js.',
        );

        preg_match_all("/champ: '([^']+)'/", $captures[1], $champsJs);

        $this->assertSame(
            $champsJs[1],
            array_column(CoherenceDonneesService::CONTRAINTES, 'champ'),
            'Ajoutez la contrainte aux deux endroits : CoherenceDonneesService et questionnaires.js.',
        );
    }

    /**
     * Extrait les identifiants de champ d'une déclaration de `questionnaires.js`, groupe par groupe.
     *
     * @return list<list<string>>
     */
    private function declarationJs(string $nom): array
    {
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));

        $this->assertSame(
            1,
            preg_match("/export const {$nom} = \[(.*?)\n\];/s", $source, $captures),
            "La constante {$nom} doit être exportée par questionnaires.js.",
        );

        $groupes = [];

        foreach (explode("\n", $captures[1]) as $ligne) {
            $ligne = trim($ligne);

            if ($ligne === '' || str_starts_with($ligne, '//')) {
                continue;
            }

            preg_match_all("/'([^']+)'/", $ligne, $valeurs);

            if ($valeurs[1] !== []) {
                // Les messages rédigés ne sont pas des identifiants de champ.
                $groupes[] = array_values(array_filter(
                    $valeurs[1],
                    fn (string $v) => !str_contains($v, ' '),
                ));
            }
        }

        return $groupes;
    }

    // ── Fixtures ────────────────────────────────────────────────────────────────────────

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    private function dossier(?User $redacteur = null): Dossier
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        return Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => \App\Enums\EtapeDossier::Initialisation,
            // La policy `modifierQuestionnaire` exige l'assignation au dossier : sans elle, la
            // requête répond 403 et le contrôle de cohérence n'est jamais atteint.
            'redacteur_id' => ($redacteur ?? User::factory()->create())->id,
            'objet'        => 'Cohérence des dates du questionnaire',
        ]);
    }
}
