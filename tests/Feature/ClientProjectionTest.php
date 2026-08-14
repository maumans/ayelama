<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ClientProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La fiche Client est la source de vérité de l'identité ; `questionnaires.donnees`
 * n'en est qu'une projection dérivée, alimentée depuis l'emplacement décrit par la
 * Partie (donnees_prefixe / donnees_bloc / donnees_index).
 */
class ClientProjectionTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(array $donnees = [], EtapeDossier $etape = EtapeDossier::Edition): Dossier
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => $etape,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour la projection des fiches clients',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->load('questionnaire');
    }

    private function clientPhysique(array $attrs = []): Client
    {
        return Client::create(array_merge([
            'type'           => 'physique',
            'civilite'       => 'M.',
            'prenom_nom'     => 'Ibrahima DIALLO',
            'ne_a'           => 'Conakry',
            'date_naissance' => '1985-03-15',
            'nationalite'    => 'Guinéenne',
            'piece_type'     => 'CNI CEDEAO',
            'piece_numero'   => 'GN0123456',
            'quartier'       => 'Tanerie',
            'commune'        => 'Matoto',
            'demeurant_ville' => 'Conakry',
            'pays'           => 'Guinée',
            'telephone'      => '621898902',
        ], $attrs));
    }

    private function service(): ClientProjectionService
    {
        return app(ClientProjectionService::class);
    }

    public function test_une_section_scalaire_est_projetee_sous_son_prefixe(): void
    {
        $dossier = $this->dossier();
        $client  = $this->clientPhysique();

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->prenom_nom,
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $this->assertTrue($this->service()->reprojeter($dossier->fresh()));

        $donnees = $dossier->fresh()->questionnaire->donnees;
        $this->assertSame('Ibrahima DIALLO', $donnees['pp.prenom_nom']);
        $this->assertSame('M.', $donnees['pp.civilite']);
        $this->assertSame('GN0123456', $donnees['pp.piece_numero']);
        $this->assertSame('CNI CEDEAO', $donnees['pp.piece_type']);
        $this->assertSame('Conakry', $donnees['pp.demeurant_ville']);
        // Les dates sont sérialisées en ISO : les modèles Word attendent une chaîne
        // et le frontend sait réafficher ce format.
        $this->assertSame('1985-03-15', $donnees['pp.date_naissance']);
        // Adresse composite, pour les schémas qui n'ont qu'un champ d'adresse libre.
        $this->assertSame('Tanerie, Matoto, Conakry', $donnees['pp.adresse']);
    }

    public function test_le_prefixe_de_la_partie_pilote_la_destination(): void
    {
        $dossier = $this->dossier();
        $client  = $this->clientPhysique(['prenom_nom' => 'Mariama SOW']);

        // Le même client, désigné comme gérant : c'est donnees_prefixe qui décide
        // où écrire — le serveur ne connaît pas le schéma des questionnaires.
        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->prenom_nom,
            'role'            => 'gerant',
            'donnees_prefixe' => 'ger',
        ]);

        $this->service()->reprojeter($dossier->fresh());

        $donnees = $dossier->fresh()->questionnaire->donnees;
        $this->assertSame('Mariama SOW', $donnees['ger.prenom_nom']);
        $this->assertArrayNotHasKey('pp.prenom_nom', $donnees);
    }

    public function test_un_bloc_repetable_ne_perd_pas_les_donnees_propres_a_lacte(): void
    {
        // Le piège central de la projection indexée : écraser l'item effacerait le
        // nombre de parts, qui est propre à CET acte et non à la personne.
        $dossier = $this->dossier([
            'associes' => [
                ['nom' => 'Ancien A', 'parts_chiffres' => '40'],
                ['nom' => 'Ancien B', 'parts_chiffres' => '60'],
            ],
        ]);

        $client = $this->clientPhysique(['prenom_nom' => 'Fatoumata BAH']);

        Partie::create([
            'dossier_id'    => $dossier->id,
            'client_id'     => $client->id,
            'nom'           => $client->prenom_nom,
            'role'          => 'associe',
            'donnees_bloc'  => 'associes',
            'donnees_index' => 1,
        ]);

        $this->service()->reprojeter($dossier->fresh());

        $associes = $dossier->fresh()->questionnaire->donnees['associes'];

        // Ligne 1 projetée…
        $this->assertSame('Fatoumata BAH', $associes[1]['nom']);
        // …sans perdre ses parts…
        $this->assertSame('60', $associes[1]['parts_chiffres']);
        // …et sans toucher la ligne 0, qui n'a pas de fiche rattachée.
        $this->assertSame('Ancien A', $associes[0]['nom']);
        $this->assertSame('40', $associes[0]['parts_chiffres']);
    }

    public function test_une_personne_morale_projette_ses_champs_propres(): void
    {
        $dossier = $this->dossier();
        $client  = Client::create([
            'type'                 => 'morale',
            'denomination'         => 'Ecobank Guinée SA',
            'forme'                => 'SA',
            'rccm'                 => 'GN-CON-2010-B-0001',
            'representant_legal'   => 'Alpha CONDE',
            'representant_qualite' => 'Directeur Général',
            'quartier'             => 'Almamya',
            'commune'              => 'Kaloum',
            'demeurant_ville'      => 'Conakry',
            'pays'                 => 'Guinée',
        ]);

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->denomination,
            'role'            => 'creancier',
            'donnees_prefixe' => 'bq',
        ]);

        $this->service()->reprojeter($dossier->fresh());

        $donnees = $dossier->fresh()->questionnaire->donnees;
        $this->assertSame('Ecobank Guinée SA', $donnees['bq.denomination']);
        $this->assertSame('SA', $donnees['bq.forme']);
        $this->assertSame('Alpha CONDE', $donnees['bq.representant_nom']);
        // Le seul champ qui manquait à la fiche Client avant cette refonte.
        $this->assertSame('Directeur Général', $donnees['bq.representant_qualite']);
        // Les modèles utilisent siege_* pour l'adresse d'une personne morale.
        $this->assertSame('Almamya', $donnees['bq.siege_quartier']);
        $this->assertSame('Conakry', $donnees['bq.siege_ville']);
        // `ville` était absent du mapping JS d'origine — corrigé ici.
        $this->assertSame('Conakry', $donnees['bq.ville']);
        $this->assertSame('Société', $donnees['bq.civilite']);
    }

    public function test_une_partie_sans_fiche_client_nest_pas_projetee(): void
    {
        // L'échappatoire « Saisir sans fiche client » : la saisie libre doit survivre
        // intacte à une reprojection.
        $dossier = $this->dossier(['pp.prenom_nom' => 'Saisi à la main']);

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => null,
            'nom'             => 'Saisi à la main',
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $this->assertFalse($this->service()->reprojeter($dossier->fresh()));
        $this->assertSame('Saisi à la main', $dossier->fresh()->questionnaire->donnees['pp.prenom_nom']);
    }

    public function test_un_dossier_cloture_nest_jamais_reprojete(): void
    {
        // Un dossier clôturé est scellé : sa vérité est celle du jour de la clôture.
        $dossier = $this->dossier(['pp.prenom_nom' => 'Nom au moment de la clôture'], EtapeDossier::Cloture);
        $client  = $this->clientPhysique(['prenom_nom' => 'Nom corrigé depuis']);

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->prenom_nom,
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $this->assertFalse($this->service()->reprojeter($dossier->fresh()));
        $this->assertSame(
            'Nom au moment de la clôture',
            $dossier->fresh()->questionnaire->donnees['pp.prenom_nom'],
        );
    }

    public function test_modifier_une_fiche_repercute_sur_les_dossiers_lies(): void
    {
        $dossierA = $this->dossier();
        $dossierB = $this->dossier();
        $clos     = $this->dossier([], EtapeDossier::Cloture);
        $client   = $this->clientPhysique();

        foreach ([$dossierA, $dossierB, $clos] as $d) {
            Partie::create([
                'dossier_id'      => $d->id,
                'client_id'       => $client->id,
                'nom'             => $client->prenom_nom,
                'role'            => 'associe_unique',
                'donnees_prefixe' => 'pp',
            ]);
        }

        $client->update(['piece_numero' => 'GN9999999']);
        $touches = $this->service()->reprojeterDossiersDuClient($client->fresh());

        $this->assertEqualsCanonicalizing([$dossierA->reference, $dossierB->reference], $touches);
        $this->assertSame('GN9999999', $dossierA->fresh()->questionnaire->donnees['pp.piece_numero']);
        $this->assertSame('GN9999999', $dossierB->fresh()->questionnaire->donnees['pp.piece_numero']);
        // Le dossier clôturé n'est même pas listé.
        $this->assertNotContains($clos->reference, $touches);

        // Une modification déclenchée hors du dossier doit rester visible pour
        // l'équipe qui le suit.
        $this->assertDatabaseHas('journal_activites', [
            'dossier_id' => $dossierA->id,
            'type'       => 'modification',
        ]);
    }

    public function test_la_route_de_modification_reprojette_et_annonce_les_dossiers(): void
    {
        $dossier = $this->dossier();
        $client  = $this->clientPhysique();

        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $client->id,
            'nom'             => $client->prenom_nom,
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $notaire = User::factory()->create();
        $notaire->syncRoles([RoleUtilisateur::Notaire]);

        $this->actingAs($notaire)
            ->patchJson("/clients/{$client->id}", [
                'type'       => 'physique',
                'prenom_nom' => 'Ibrahima DIALLO',
                'civilite'   => 'M.',
                'quartier'   => 'Kipé',
            ])
            ->assertOk()
            ->assertJsonPath('dossiers_mis_a_jour', [$dossier->reference]);

        $this->assertSame('Kipé', $dossier->fresh()->questionnaire->donnees['pp.quartier']);
    }

    public function test_la_table_php_ne_derive_pas_du_mapping_javascript(): void
    {
        // ClientProjectionService (PHP) est un miroir assumé de clientFields.js : le
        // serveur projette quand une fiche est corrigée hors du wizard, le client
        // projette pour l'aperçu immédiat. Une divergence entre les deux ferait
        // qu'un champ resterait affiché en saisie libre côté écran alors que le
        // serveur l'écrase — ou l'inverse. Ce test attrape la dérive.
        $js = file_get_contents(resource_path('js/lib/clientFields.js'));
        preg_match_all("/set\('([a-z_]+)'/", $js, $m);

        $suffixesJs  = array_unique($m[1]);
        $suffixesPhp = ClientProjectionService::suffixesProjetes();

        $manquantsEnPhp = array_diff($suffixesJs, $suffixesPhp);
        $this->assertSame([], array_values($manquantsEnPhp),
            'Suffixes projetés par clientFields.js mais absents de ClientProjectionService : '
            . implode(', ', $manquantsEnPhp));

        // Et réciproquement, pour que estChampIdentite() masque bien tout ce que le
        // serveur est susceptible d'écrire.
        $manquantsEnJs = array_diff($suffixesPhp, $suffixesJs);
        $this->assertSame([], array_values($manquantsEnJs),
            'Suffixes projetés par ClientProjectionService mais absents de clientFields.js : '
            . implode(', ', $manquantsEnJs));
    }

    public function test_aucun_champ_propre_a_lacte_nest_projetable(): void
    {
        // Garde-fou de conception : si l'un de ces champs entrait dans
        // SUFFIXES_IDENTITE, une correction de fiche client écraserait des données
        // qui appartiennent à l'acte, pas à la personne.
        $projetables = ClientProjectionService::suffixesProjetes();

        foreach (['parts_chiffres', 'actions_chiffres', 'apport_chiffres', 'fonction',
                  'qualite', 'montant_credit_chiffres', 'taux_interet', 'rang_hypothecaire',
                  'est_different'] as $champActe) {
            $this->assertNotContains($champActe, $projetables, "« {$champActe} » est propre à l'acte et ne doit jamais être projeté depuis une fiche client.");
        }
    }
}
