<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ClientProjectionService;
use App\Support\Normalisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le format des dates, éprouvé **tel que l'interface l'envoie** (2026-09-09).
 *
 * Deux formats coexistent légitimement — `JJ/MM/AAAA` dans `questionnaires.donnees` (le modèle Word
 * le reçoit tel quel), l'ISO dans les colonnes castées `date`. Cinq endroits les confondaient, et
 * PHP lit `JJ/MM/AAAA` comme du mois/jour américain :
 *
 *   - `13/05/1985` : `strtotime` échoue, une date valide était **refusée** ;
 *   - `01/04/1985` : devenait le **4 janvier**, enregistré sans la moindre erreur.
 *
 * ⚠️ **Pourquoi la suite existante ne l'a pas vu** : `ControlesClientTest` était écrit en ISO. Il
 * éprouvait donc les règles, jamais le format que le formulaire poste réellement. Ce fichier part
 * de l'inverse — du format de l'interface.
 */
class FormatsDatesTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    /** @param array<string, mixed> $champs */
    private function creerClient(array $champs)
    {
        return $this->actingAs($this->clerc())->postJson('/clients', array_merge([
            'type'        => 'physique',
            'nom_famille' => 'DIALLO',
            'prenoms'     => 'Thierno',
        ], $champs));
    }

    // ── Le défaut central : une date française ne change plus de jour ────────────────────

    public function test_une_date_francaise_est_enregistree_au_bon_jour(): void
    {
        // LE test qui aurait attrapé l'inversion : « 01/04/1985 » est le 1er avril, et devenait
        // le 4 janvier — silencieusement, sans erreur de validation.
        $this->creerClient(['date_naissance' => '01/04/1985'])->assertCreated();

        $client = Client::where('nom_famille', 'DIALLO')->first();

        $this->assertSame('01/04/1985', $client->date_naissance->format('d/m/Y'));
    }

    public function test_un_jour_superieur_a_douze_en_francais_est_accepte(): void
    {
        // « Le champ date de naissance n'est pas une date valide » sur une date parfaitement
        // valide : c'est l'erreur signalée par l'étude. Tout jour > 12 était refusé.
        $this->creerClient(['date_naissance' => '13/05/1985'])->assertCreated();

        $this->assertSame(
            '13/05/1985',
            Client::where('nom_famille', 'DIALLO')->first()->date_naissance->format('d/m/Y'),
        );
    }

    public function test_les_trois_dates_de_la_fiche_sont_converties(): void
    {
        $this->creerClient([
            'date_naissance'    => '13/05/1985',
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN001',
            'piece_delivree_le' => '28/02/2020',
            'piece_expire_le'   => '28/02/2030',
        ])->assertCreated();

        $client = Client::where('piece_numero', 'GN001')->first();

        $this->assertSame('13/05/1985', $client->date_naissance->format('d/m/Y'));
        $this->assertSame('28/02/2020', $client->piece_delivree_le->format('d/m/Y'));
        $this->assertSame('28/02/2030', $client->piece_expire_le->format('d/m/Y'));
    }

    public function test_une_date_iso_reste_acceptee(): void
    {
        // Non-régression : les modales corrigées envoient de l'ISO, c'est le chemin normal.
        $this->creerClient(['date_naissance' => '1985-05-13'])->assertCreated();

        $this->assertSame(
            '13/05/1985',
            Client::where('nom_famille', 'DIALLO')->first()->date_naissance->format('d/m/Y'),
        );
    }

    public function test_la_coherence_des_dates_reste_verifiee_en_francais(): void
    {
        // La conversion précède la validation : les règles de cohérence doivent donc mordre aussi
        // sur du français, sans quoi on aurait échangé un défaut contre un autre.
        $this->creerClient([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN002',
            'piece_delivree_le' => '01/01/2020',
            'piece_expire_le'   => '01/01/2019',
        ])->assertStatus(422)->assertJsonValidationErrors('piece_expire_le');

        $this->creerClient(['date_naissance' => '25/12/2030'])
            ->assertStatus(422)->assertJsonValidationErrors('date_naissance');

        $this->creerClient([
            'date_naissance'    => '25/12/2000',
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN003',
            'piece_delivree_le' => '25/12/1995',
        ])->assertStatus(422)->assertJsonValidationErrors('piece_delivree_le');

        $this->creerClient([
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN004',
            'piece_delivree_le' => now()->addYear()->format('d/m/Y'),
        ])->assertStatus(422)->assertJsonValidationErrors('piece_delivree_le');
    }

    public function test_la_date_de_constitution_d_une_societe_est_enregistree_au_bon_jour(): void
    {
        $notaire = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $notaire->id, 'role' => RoleUtilisateur::Notaire->value]);

        $this->actingAs($notaire->fresh())->postJson('/societes', [
            'denomination'      => 'FAYA SARL',
            'forme'             => 'SARL',
            'date_constitution' => '25/07/2019',
        ])->assertCreated();

        $this->assertSame(
            '25/07/2019',
            Societe::where('denomination', 'FAYA SARL')->first()->date_constitution->format('d/m/Y'),
        );
    }

    // ── Le questionnaire, et donc l'acte ────────────────────────────────────────────────

    public function test_la_projection_ecrit_les_dates_en_francais(): void
    {
        $dossier = $this->dossierAvecPartie();

        app(ClientProjectionService::class)->reprojeter($dossier->fresh());

        $donnees = $dossier->fresh()->questionnaire->donnees;

        // C'est cette valeur que le modèle Word reçoit telle quelle. Elle portait un horodatage
        // complet — « 1970-02-05T00:00:00.000000Z » — imprimé dans l'acte authentique.
        $this->assertSame('15/03/1985', $donnees['pp.date_naissance']);
        $this->assertSame('01/06/2020', $donnees['pp.piece_delivree_le']);
    }

    public function test_aucune_donnee_de_questionnaire_ne_porte_de_date_iso(): void
    {
        // Garde-fou : la convention de `donnees` devient exécutable, plutôt que documentée dans un
        // commentaire que deux détenteurs ont interprété différemment.
        $dossier = $this->dossierAvecPartie();

        app(ClientProjectionService::class)->reprojeter($dossier->fresh());

        foreach ($dossier->fresh()->questionnaire->donnees as $cle => $valeur) {
            if (!is_string($valeur)) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '#^\d{4}-\d{2}-\d{2}#',
                $valeur,
                "« {$cle} » porte une date ISO : `donnees` attend du JJ/MM/AAAA (voir lib/dates.js).",
            );
        }
    }

    public function test_la_fiche_societe_traverse_les_deux_formats(): void
    {
        // `donnees` ↔ colonne castée, dans les deux sens : c'est la même traversée que pour la
        // fiche client, et elle portait les deux mêmes défauts.
        $attributs = Societe::depuisQuestionnaire([
            'soc.denomination'      => 'FAYA SARL',
            'soc.date_constitution' => '25/07/2019',
        ]);

        $this->assertSame('2019-07-25', $attributs['date_constitution']);

        $societe = Societe::create($attributs + ['forme' => 'SARL']);

        // Retour vers le questionnaire : c'est cette valeur que le modèle Word imprime.
        $this->assertSame('25/07/2019', $societe->fresh()->versQuestionnaire()['soc.date_constitution']);
    }

    public function test_completer_la_fiche_societe_depuis_le_questionnaire_convertit_la_date(): void
    {
        // Une société au registre sans date de constitution : la saisie du dossier complète la
        // fiche, et ne doit pas y entrer un 4 janvier à la place d'un 1er avril.
        $societe = Societe::create(['denomination' => 'MICH SARL', 'forme' => 'SARL']);

        $societe->completerDepuisQuestionnaire(['soc.date_constitution' => '01/04/2019']);

        $this->assertSame('01/04/2019', $societe->fresh()->date_constitution->format('d/m/Y'));
    }

    // ── La reprise des données déjà enregistrées ────────────────────────────────────────

    public function test_la_migration_reinverse_un_jour_inferieur_a_treize(): void
    {
        $client = $this->ficheAvecDatesBrutes(['date_naissance' => '1985-01-04']);

        $this->rejouerMigration();

        // Saisi « 01/04/1985 », enregistré « 04/01/1985 » : la reprise rend le 1er avril.
        $this->assertSame('01/04/1985', $client->fresh()->date_naissance->format('d/m/Y'));
    }

    public function test_la_migration_ne_touche_pas_un_jour_superieur_a_douze(): void
    {
        // Une telle valeur n'a pas pu être produite par l'inversion — le mois n'existerait pas.
        // Elle vient donc d'un chemin sain et doit rester intacte : la corriger inventerait une
        // date.
        $client = $this->ficheAvecDatesBrutes(['date_naissance' => '1985-05-25']);

        $this->rejouerMigration();

        $this->assertSame('25/05/1985', $client->fresh()->date_naissance->format('d/m/Y'));
        $this->assertNull($client->fresh()->dates_a_confirmer);
    }

    public function test_la_migration_est_idempotente(): void
    {
        // Le point délicat : une date réinversée porte de nouveau un jour ≤ 12. Sans le témoin,
        // la rejouer la réinverserait une seconde fois.
        $client = $this->ficheAvecDatesBrutes(['date_naissance' => '1985-01-04']);

        $this->rejouerMigration();
        $this->rejouerMigration();
        $this->rejouerMigration();

        $this->assertSame('01/04/1985', $client->fresh()->date_naissance->format('d/m/Y'));
    }

    public function test_la_fiche_reprise_porte_un_avertissement_avec_les_deux_lectures(): void
    {
        // Le mécanisme est certain, mais une ligne prise isolément reste ambiguë : l'avertissement
        // donne donc les deux lectures plutôt que de présenter la valeur reprise comme sûre.
        $client = $this->ficheAvecDatesBrutes(['date_naissance' => '1985-01-04']);

        $this->rejouerMigration();

        $avertissement = implode(' ', $client->fresh()->avertissements());

        $this->assertStringContainsString('01/04/1985', $avertissement);
        $this->assertStringContainsString('04/01/1985', $avertissement);
        $this->assertStringContainsString("pièce d'identité", $avertissement);
    }

    public function test_enregistrer_la_fiche_leve_l_avertissement(): void
    {
        // Ouvrir la fiche et la valider, c'est confirmer ses dates.
        $client = $this->ficheAvecDatesBrutes(['date_naissance' => '1985-01-04']);

        $this->rejouerMigration();
        $this->assertNotNull($client->fresh()->dates_a_confirmer);

        $this->actingAs($this->clerc())->patchJson("/clients/{$client->id}", [
            'type'        => 'physique',
            'nom_famille' => 'DIALLO',
            'prenoms'     => 'Thierno',
        ])->assertOk();

        $this->assertNull($client->fresh()->dates_a_confirmer);
        $this->assertSame([], $client->fresh()->avertissements());
    }

    public function test_le_temoin_ne_peut_pas_etre_ecrit_par_une_requete(): void
    {
        // Hors `$fillable` : ce témoin dit ce que la migration a fait, il ne se déclare pas.
        $this->creerClient(['dates_a_confirmer' => ['date_naissance' => ['retenu' => '1900-01-01']]])
            ->assertCreated();

        $this->assertNull(Client::where('nom_famille', 'DIALLO')->first()->dates_a_confirmer);
    }

    public function test_la_migration_ramene_les_dates_de_questionnaire_en_francais(): void
    {
        $dossier = $this->dossierAvecPartie();

        DB::table('questionnaires')->where('dossier_id', $dossier->id)->update([
            'donnees' => json_encode([
                'pp.date_naissance' => '1970-02-05T00:00:00.000000Z',
                'ger.date_deces'    => '2026-03-14',
                'pp.ne_a'           => 'Conakry',
                'associes'          => [['date_naissance' => '1990-01-01']],
            ]),
        ]);

        $this->rejouerMigration();

        $donnees = $dossier->fresh()->questionnaire->donnees;

        $this->assertSame('05/02/1970', $donnees['pp.date_naissance']);
        $this->assertSame('14/03/2026', $donnees['ger.date_deces']);
        // Les blocs répétables sont des tableaux de tableaux : la reprise doit y descendre.
        $this->assertSame('01/01/1990', $donnees['associes'][0]['date_naissance']);
        // Et ne toucher que ce qui est une date de bout en bout.
        $this->assertSame('Conakry', $donnees['pp.ne_a']);
    }

    // ── Le convertisseur partagé ────────────────────────────────────────────────────────

    public function test_le_convertisseur_ne_renvoie_que_les_champs_convertis(): void
    {
        // Ne renvoyer que le converti permet de fusionner dans la requête sans écraser ce qui
        // était déjà au bon format.
        $this->assertSame(
            ['a' => '1985-05-13', 'd' => '1985-04-01'],
            Normalisation::datesEnISO(
                ['a' => '13/05/1985', 'b' => '1985-05-13', 'c' => null, 'd' => '01/04/1985'],
                ['a', 'b', 'c', 'd', 'absent'],
            ),
        );
    }

    public function test_le_convertisseur_ignore_une_forme_partielle(): void
    {
        // Une saisie incomplète doit rester telle quelle et être refusée par la validation,
        // plutôt que d'être devinée ici.
        $this->assertSame([], Normalisation::datesEnISO(
            ['x' => '1/4/85', 'y' => '01/04/1985 10:00', 'z' => 'jamais'],
            ['x', 'y', 'z'],
        ));
    }

    // ── Fixtures ───────────────────────────────────────────────────────────────────────

    private function dossierAvecPartie(): Dossier
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Format des dates projetées',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => []]);

        $client = Client::create([
            'type'              => 'physique',
            'civilite'          => 'M.',
            'prenom_nom'        => 'Ibrahima DIALLO',
            'date_naissance'    => '1985-03-15',
            'piece_type'        => 'CNI',
            'piece_numero'      => 'GN0123456',
            'piece_delivree_le' => '2020-06-01',
            'piece_expire_le'   => '2030-06-01',
        ]);

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->prenom_nom,
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        return $dossier->load('questionnaire');
    }

    /** Écriture directe : c'est bien l'état antérieur à la correction qu'on reproduit. */
    private function ficheAvecDatesBrutes(array $dates): Client
    {
        $client = Client::create(['type' => 'physique', 'nom_famille' => 'DIALLO', 'prenoms' => 'Thierno']);

        DB::table('clients')->where('id', $client->id)->update($dates + ['dates_a_confirmer' => null]);

        return $client->fresh();
    }

    /** `migrate` est déjà à jour sous RefreshDatabase : on rejoue la seule reprise. */
    private function rejouerMigration(): void
    {
        (require database_path('migrations/2026_09_09_120000_corriger_dates_inversees_clients.php'))->up();
    }
}
