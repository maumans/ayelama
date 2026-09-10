<?php

namespace Tests\Feature;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Régime et situation matrimoniaux — listes fermées, déclarées une fois (2026-08-12).
 *
 * Le régime était en saisie libre : la base portait **quatre orthographes pour deux régimes**, dont
 * « Communaté de bien » (deux fautes), que les modèles Word reprenaient telle quelle dans les actes
 * authentiques.
 */
class ListesMatrimonialesTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    /** Extrait une constante exportée de `questionnaires.js`. */
    private function listeJs(string $nom): array
    {
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));

        $trouve = preg_match(
            "/export const {$nom} = \[(.*?)\];/s",
            $source,
            $captures,
        );

        $this->assertSame(1, $trouve, "La constante {$nom} doit être exportée par questionnaires.js.");

        preg_match_all("/'([^']*)'/", $captures[1], $valeurs);

        return $valeurs[1];
    }

    // ── Garde-fou de duplication ─────────────────────────────────────────────

    public function test_les_regimes_php_et_js_sont_identiques(): void
    {
        // Les questionnaires étant statiques, la liste ne peut pas être alimentée depuis PHP : la
        // duplication est inévitable, donc **gardée**. Sans ce test, un régime ajouté d'un seul côté
        // serait sélectionnable et refusé — ou validé et introuvable.
        $this->assertSame(
            Client::REGIMES_MATRIMONIAUX,
            $this->listeJs('REGIMES_MATRIMONIAUX'),
            'Ajoutez le régime aux deux endroits : Client::REGIMES_MATRIMONIAUX et questionnaires.js.',
        );
    }

    public function test_les_situations_php_et_js_sont_identiques(): void
    {
        $this->assertSame(
            Client::SITUATIONS_MATRIMONIALES,
            $this->listeJs('SITUATIONS_MATRIMONIALES'),
        );
    }

    public function test_aucune_declaration_de_questionnaire_ne_recopie_les_options(): void
    {
        // C'est le défaut déjà corrigé sur FORMES_SOCIETE : dix déclarations portaient chacune leur
        // copie, et une valeur ajoutée n'entrait dans aucune.
        $source = file_get_contents(resource_path('js/data/questionnaires.js'));

        $this->assertStringNotContainsString("options: ['Célibataire'", $source);
        $this->assertSame(
            0,
            preg_match("/regime_matrimonial'[^}]*type: 'text'/", $source),
            'Le régime matrimonial ne doit plus être un champ de saisie libre.',
        );
    }

    public function test_aucune_constante_du_module_nest_utilisee_avant_sa_declaration(): void
    {
        // Défaut réellement rencontré le 2026-09-09 : `SITUATIONS_MATRIMONIALES` était déclarée
        // sous les schémas qui la consomment. Or les littéraux de schéma s'évaluent à
        // l'initialisation du module — la constante était donc dans sa **zone morte temporelle**,
        // et la page mourait sur « Cannot access 'SITUATIONS_MATRIMONIALES' before
        // initialization ».
        //
        // Ce qui rend ce défaut coûteux : `vite build` le laisse passer (c'est une erreur
        // d'exécution, pas de compilation), et le symptôme est une page blanche. Deuxième
        // occurrence d'une erreur de portée JS invisible à la construction, après `typesDocument`
        // sorti du scope de module dans Parametres/Index.jsx.
        $lignes = explode("
", file_get_contents(resource_path('js/data/questionnaires.js')));

        $declarations = [];
        foreach ($lignes as $numero => $ligne) {
            if (preg_match('/^(?:export )?const ([A-Za-z_$][\w$]*)\s*=/', $ligne, $c)) {
                $declarations[$c[1]] = $numero + 1;
            }
        }

        $this->assertNotEmpty($declarations, 'Les constantes du module doivent être détectables.');

        $fautives = [];

        foreach ($declarations as $nom => $ligneDeclaration) {
            foreach ($lignes as $numero => $ligne) {
                if ($numero + 1 >= $ligneDeclaration) {
                    break;
                }

                if (str_starts_with(ltrim($ligne), '//')) {
                    continue;
                }

                if (preg_match('/\b' . preg_quote($nom, '/') . '\b/', $ligne)) {
                    $fautives[] = "{$nom} (déclarée l.{$ligneDeclaration}, utilisée l." . ($numero + 1) . ')';
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            $fautives,
            "Déplacez ces déclarations au-dessus de leur premier usage : " . implode(', ', $fautives),
        );
    }

    // ── Les valeurs déjà enregistrées ───────────────────────────────────────

    public function test_la_migration_traduit_les_orthographes_connues(): void
    {
        // Les deux fautes réellement rencontrées en base.
        foreach ([
            'Communaté de bien' => 'Communauté de biens',
            'Séparation'        => 'Séparation de biens',
        ] as $faute => $attendu) {
            $client = Client::create([
                'type' => 'physique', 'nom_famille' => 'TEST',
                'situation_matrimoniale' => 'Marié(e)',
            ]);
            // Écriture directe : le modèle n'empêche pas la valeur, c'est la validation HTTP qui le
            // fait — et c'est bien l'état antérieur qu'on reproduit ici.
            DB::table('clients')->where('id', $client->id)->update(['regime_matrimonial' => $faute]);

            $this->rejouerMigration();

            $this->assertSame($attendu, $client->fresh()->regime_matrimonial, "« {$faute} » doit devenir « {$attendu} ».");
        }
    }

    public function test_une_valeur_intraduisible_est_laissee_intacte(): void
    {
        // Corriger de travers le régime matrimonial d'un client serait pire que de ne rien
        // corriger : la migration journalise et laisse en place.
        $client = Client::create([
            'type' => 'physique', 'nom_famille' => 'TEST', 'situation_matrimoniale' => 'Marié(e)',
        ]);
        DB::table('clients')->where('id', $client->id)->update(['regime_matrimonial' => 'Régime coutumier local']);

        $this->rejouerMigration();

        $this->assertSame('Régime coutumier local', $client->fresh()->regime_matrimonial);
    }

    public function test_la_migration_est_idempotente(): void
    {
        $client = Client::create([
            'type' => 'physique', 'nom_famille' => 'TEST', 'situation_matrimoniale' => 'Marié(e)',
        ]);
        DB::table('clients')->where('id', $client->id)->update(['regime_matrimonial' => 'Communaté de bien']);

        $this->rejouerMigration();
        $this->rejouerMigration();

        $this->assertSame('Communauté de biens', $client->fresh()->regime_matrimonial);
    }

    public function test_une_fiche_reprise_est_de_nouveau_enregistrable(): void
    {
        // La raison d'être de la migration : sans elle, la nouvelle règle `Rule::in` aurait refusé
        // cette fiche au premier enregistrement, même pour un changement de téléphone.
        $client = Client::create([
            'type' => 'physique', 'nom_famille' => 'DIALLO', 'prenoms' => 'Thierno',
            'situation_matrimoniale' => 'Marié(e)',
        ]);
        DB::table('clients')->where('id', $client->id)->update(['regime_matrimonial' => 'Communaté de bien']);

        $this->rejouerMigration();
        $frais = $client->fresh();

        $this->actingAs($this->clerc())
            ->patchJson("/clients/{$frais->id}", [
                'type'                   => 'physique',
                'nom_famille'            => 'DIALLO',
                'prenoms'                => 'Thierno',
                'situation_matrimoniale' => 'Marié(e)',
                'regime_matrimonial'     => $frais->regime_matrimonial,
                'telephone'              => '622 78 37 32',
            ])
            ->assertOk();
    }

    /** Rejoue la seule migration de reprise, `migrate` étant déjà à jour sous RefreshDatabase. */
    private function rejouerMigration(): void
    {
        $chemin = database_path('migrations/2026_08_12_140000_normaliser_regimes_matrimoniaux_clients.php');
        (require $chemin)->up();
    }
}
