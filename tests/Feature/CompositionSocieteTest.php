<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutSociete;
use App\Enums\TypeModificationStatutaire;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\SocieteAssocie;
use App\Models\SocieteGerant;
use App\Models\SocieteHistorique;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\EtatSocieteService;
use App\Services\SocieteMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * La composition d'une société — associés, gérants, historique (addendum v1.2, lot 1).
 *
 * **Ce que ces tests rendent impossible.** Avant eux,
 * `SocieteMutationService::changementsPour(CapitalCession)` rendait un tableau vide : une
 * cession de parts ne portait **rien** au registre, et rien ne le signalait. Le commentaire
 * l'assumait — « le registre ne tient pas de table des associés ». Il en tient une.
 */
class CompositionSocieteTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

        return $user->fresh();
    }

    private function societe(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination'     => 'ALPHA SARL',
            'forme'            => 'SARL',
            'capital_chiffres' => 10_000_000,
            'nombre_parts'     => 1000,
            'siege_ville'      => 'Conakry',
        ], $attributs));
    }

    private function associe(Societe $societe, string $nom, int $parts): SocieteAssocie
    {
        return SocieteAssocie::create([
            'societe_id'  => $societe->id,
            'nom'         => $nom,
            'nb_parts'    => $parts,
            'date_entree' => '2024-01-01',
        ]);
    }

    private function dossierModification(Societe $societe, array $donnees): Dossier
    {
        $typeActe = TypeActe::firstOrCreate(
            ['code' => 'SOC-MOD'],
            ['label' => 'Modification de société', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );

        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'societe_id'   => $societe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test de composition de société',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    // ═══ La projection ═══════════════════════════════════════════════════

    public function test_une_cession_par_paire_deplace_les_parts_dans_letat_projete(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);
        $this->associe($societe, 'Aïssatou', 400);

        $projete = app(EtatSocieteService::class)->projete($societe->fresh(), [TypeModificationStatutaire::CapitalCession], [
            'modif.cessions' => [
                [
                    'cedant_nom'       => 'Aïssatou',
                    'cessionnaire_nom' => 'Ibrahima',
                    'nb_parts'         => 200,
                    'prix'             => 20_000_000,
                ],
            ],
        ]);

        $parts = collect($projete['associes'])->pluck('nb_parts', 'nom')->all();

        $this->assertSame(600, $parts['Mamadou']);
        $this->assertSame(200, $parts['Aïssatou']);
        $this->assertSame(200, $parts['Ibrahima']);
        $this->assertSame(1000, array_sum($parts));
    }

    public function test_une_cession_deplace_les_parts_dans_letat_projete(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);
        $this->associe($societe, 'Aïssatou', 400);

        $projete = app(EtatSocieteService::class)->projete($societe->fresh(), [TypeModificationStatutaire::CapitalCession], [
            'modif.cedants'       => [['nom' => 'Aïssatou', 'parts_cedees' => '200']],
            'modif.cessionnaires' => [['nom' => 'Ibrahima', 'parts_acquises' => '200']],
        ]);

        $parts = collect($projete['associes'])->pluck('nb_parts', 'nom')->all();

        $this->assertSame(600, $parts['Mamadou']);
        $this->assertSame(200, $parts['Aïssatou']);
        $this->assertSame(200, $parts['Ibrahima'], 'Le cessionnaire entre avec ses parts.');
        $this->assertSame(1000, array_sum($parts), 'Une cession ne crée ni ne détruit de parts.');
    }

    /** Un associé qui cède tout **sort** de la répartition projetée (§ 6.2). */
    public function test_un_associe_tombe_a_zero_sort_de_la_repartition(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);
        $this->associe($societe, 'Aïssatou', 400);

        $projete = app(EtatSocieteService::class)->projete($societe->fresh(), [TypeModificationStatutaire::CapitalCession], [
            'modif.cedants'       => [['nom' => 'Aïssatou', 'parts_cedees' => '400']],
            'modif.cessionnaires' => [['nom' => 'Mamadou', 'parts_acquises' => '400']],
        ]);

        $this->assertSame(['Mamadou'], array_column($projete['associes'], 'nom'));
        $this->assertSame(1000, $projete['associes'][0]['nb_parts']);
        $this->assertSame(100.0, $projete['associes'][0]['pourcentage']);
    }

    /** Cas T2 de la spec : céder plus qu'on ne détient doit être dit, pas corrigé en silence. */
    public function test_ceder_plus_de_parts_quon_nen_detient_est_signale(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Aïssatou', 400);
        $this->associe($societe, 'Mamadou', 600);

        $donnees = [
            'modif.cedants'       => [['nom' => 'Aïssatou', 'parts_cedees' => '500']],
            'modif.cessionnaires' => [['nom' => 'Ibrahima', 'parts_acquises' => '500']],
        ];

        $service = app(EtatSocieteService::class);
        $anomalies = $service->anomaliesRepartition($service->projete($societe->fresh(), [TypeModificationStatutaire::CapitalCession], $donnees), $donnees);

        $this->assertNotEmpty($anomalies);
        $this->assertStringContainsString('Aïssatou', implode(' ', $anomalies));
        $this->assertStringContainsString("de plus qu'il n'en détient", implode(' ', $anomalies));
    }

    public function test_une_part_cedee_doit_etre_acquise(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Aïssatou', 400);
        $this->associe($societe, 'Mamadou', 600);

        $donnees = [
            'modif.cedants'       => [['nom' => 'Aïssatou', 'parts_cedees' => '200']],
            'modif.cessionnaires' => [['nom' => 'Ibrahima', 'parts_acquises' => '150']],
        ];

        $service = app(EtatSocieteService::class);
        $anomalies = $service->anomaliesRepartition($service->projete($societe->fresh(), [TypeModificationStatutaire::CapitalCession], $donnees), $donnees);

        $this->assertStringContainsString('une part cédée doit être acquise', implode(' ', $anomalies));
    }

    // ═══ L'application au registre ═══════════════════════════════════════

    /**
     * Le test qui aurait manqué : avant le lot 1, une cession ne portait **rien** au registre.
     */
    public function test_une_cession_est_portee_au_registre(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);
        $this->associe($societe, 'Aïssatou', 400);

        $dossier = $this->dossierModification($societe, [
            'modif.types'         => ['Cession de parts sociales'],
            'ag.date_effet'       => '15/03/2026',
            'modif.cedants'       => [['nom' => 'Aïssatou', 'parts_cedees' => '200']],
            'modif.cessionnaires' => [['nom' => 'Ibrahima', 'parts_acquises' => '200']],
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $actuels = $societe->fresh()->associesActuels()->pluck('nb_parts', 'nom')->all();

        $this->assertSame(600, $actuels['Mamadou']);
        $this->assertSame(200, $actuels['Aïssatou']);
        $this->assertSame(200, $actuels['Ibrahima']);

        // L'ancienne ligne d'Aïssatou est **datée**, pas supprimée : l'historique de détention
        // se lit dans la table.
        $sortie = SocieteAssocie::where('nom', 'Aïssatou')->whereNotNull('date_sortie')->first();
        $this->assertNotNull($sortie, 'La ligne précédente doit être fermée, pas effacée.');
        $this->assertSame(400, $sortie->nb_parts);
        $this->assertSame('2026-03-15', $sortie->date_sortie->toDateString(), "La date d'effet est celle de l'assemblée.");
    }

    public function test_un_changement_de_gerant_est_porte_au_registre(): void
    {
        $societe = $this->societe();
        SocieteGerant::create([
            'societe_id' => $societe->id, 'nom' => 'Mamadou',
            'statutaire' => true, 'date_nomination' => '2024-01-01',
        ]);

        $dossier = $this->dossierModification($societe, [
            'modif.types'                   => ['Changement de gérant statutaire'],
            'ag.date_effet'                 => '15/03/2026',
            'gerant_sortant.prenom_nom'     => 'Mamadou',
            'gerant_sortant.motif_fin'      => 'demission',
            'gerant_entrant.prenom_nom'     => 'Ibrahima',
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $societe->refresh();
        $this->assertSame(['Ibrahima'], $societe->gerantsEnFonction()->pluck('nom')->all());

        $sortant = SocieteGerant::where('nom', 'Mamadou')->sole();
        $this->assertSame('2026-03-15', $sortant->date_fin->toDateString());
        $this->assertSame('démission', $sortant->motifFinLabel());
    }

    // ═══ L'historique ════════════════════════════════════════════════════

    public function test_lapplication_pose_une_ligne_dhistorique_avec_les_deux_instantanes(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 1000);

        $dossier = $this->dossierModification($societe, [
            'modif.types'   => ['Modification de l\'objet social'],
            'ag.date_effet' => '15/03/2026',
            'modif.objet_nouveau' => 'Négoce international et import-export',
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $ligne = SocieteHistorique::sole();
        $this->assertSame($societe->id, $ligne->societe_id);
        $this->assertSame(['objet_social'], $ligne->operations);
        $this->assertSame('2026-03-15', $ligne->date_effet->toDateString());
        $this->assertNotNull($ligne->etat_avant);
        $this->assertSame('Négoce international et import-export', $ligne->etat_apres['objet_social']);
        $this->assertArrayHasKey('objet_social', $ligne->differences());
    }

    /** Cas T12 de la spec : rejouer un avancement ne double pas l'histoire. */
    public function test_rejouer_lapplication_ne_cree_quune_seule_ligne(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 1000);

        $dossier = $this->dossierModification($societe, [
            'modif.types'         => ['Modification de l\'objet social'],
            'modif.objet_nouveau' => 'Négoce international',
        ]);

        $service = app(SocieteMutationService::class);
        $service->appliquer($dossier, $this->clerc());
        $service->appliquer($dossier->fresh(), $this->clerc());

        $this->assertSame(1, SocieteHistorique::count());
    }

    public function test_lhistorique_est_immuable(): void
    {
        $societe = $this->societe();

        $ligne = SocieteHistorique::create([
            'societe_id' => $societe->id, 'dossier_id' => null, 'operations' => ['etat_initial'],
            'date_effet' => '2026-01-01', 'etat_avant' => null, 'etat_apres' => ['capital_chiffres' => 10_000_000],
            'appliquee_le' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $ligne->update(['date_effet' => '2026-02-01']);
    }

    public function test_lhistorique_refuse_la_suppression(): void
    {
        $societe = $this->societe();

        $ligne = SocieteHistorique::create([
            'societe_id' => $societe->id, 'dossier_id' => null, 'operations' => ['etat_initial'],
            'date_effet' => '2026-01-01', 'etat_avant' => null, 'etat_apres' => [],
            'appliquee_le' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $ligne->delete();
    }

    /**
     * L'historique survit à la suppression **définitive** de son dossier.
     *
     * ⚠️ `Dossier` utilise la suppression logique : un `delete()` ordinaire ne déclenche aucune
     * contrainte, et rien ne serait prouvé. C'est `forceDelete()` qui met la clé étrangère à
     * l'épreuve — et c'est là que `journal_activites`, en `cascadeOnDelete`, disparaîtrait.
     */
    public function test_lhistorique_survit_a_la_suppression_du_dossier(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 1000);

        $dossier = $this->dossierModification($societe, [
            'modif.types'         => ['Modification de l\'objet social'],
            'modif.objet_nouveau' => 'Négoce international',
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());
        $this->assertSame(1, SocieteHistorique::count());

        $dossier->forceDelete();

        $ligne = SocieteHistorique::sole();
        $this->assertNull($ligne->dossier_id, 'Le lien se dénoue, la ligne demeure.');
        $this->assertSame('Négoce international', $ligne->etat_apres['objet_social']);
    }

    // ═══ La radiation ════════════════════════════════════════════════════

    /**
     * Le défaut latent : `JournalActivite::enregistrer()` exige un `Dossier` non nul, or une
     * société entrée au registre à la main n'en a pas — et c'est précisément elle qu'on radie.
     */
    public function test_radier_une_societe_sans_dossier_dorigine(): void
    {
        $societe = $this->societe([
            'statut'                 => StatutSociete::LiquidationCloturee,
            'cloture_liquidation_at' => '2026-01-10',
        ]);

        $this->actingAs($this->clerc())
            ->patch("/societes/{$societe->id}/radiation", ['radiation_at' => '2026-02-01'])
            ->assertRedirect();

        $this->assertSame(StatutSociete::Radiee, $societe->fresh()->statut);

        $ligne = SocieteHistorique::sole();
        $this->assertSame(['radiation'], $ligne->operations);
        $this->assertNull($ligne->dossier_id, 'Une radiation se constate sans dossier.');
    }

    // ═══ Personnes connues pour écran ═════════════════════════════════════

    /**
     * Régression : personnesConnuesPourEcran() exigeait `Partie $partie`, ce qui levait un
     * TypeError (stdClass given) dès que la société avait des associés ou gérants enregistrés.
     */
    public function test_personnes_connues_pour_ecran_avec_societe_associes_et_gerants(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);
        SocieteGerant::create([
            'societe_id'      => $societe->id,
            'nom'             => 'Aïssatou',
            'statutaire'      => true,
            'date_nomination' => '2024-01-01',
        ]);

        $personnes = $societe->personnesConnuesPourEcran();

        $this->assertCount(2, $personnes);
        $noms = array_column($personnes, 'nom');
        $this->assertContains('Mamadou', $noms);
        $this->assertContains('Aïssatou', $noms);

        $roles = array_column($personnes, 'role', 'nom');
        $this->assertSame('associe', $roles['Mamadou']);
        $this->assertSame('gerant', $roles['Aïssatou']);
    }

    /**
     * Vérifie la route réelle qui a levé l'exception : GET /dossiers/{ref}?focus=pieces&tab=informations
     */
    public function test_affichage_dossier_avec_societe_ayant_des_associes_enregistres(): void
    {
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou', 600);

        $dossier = $this->dossierModification($societe, [
            'modif.types' => ['Cession de parts sociales'],
        ]);

        $response = $this->actingAs($this->clerc())
            ->get("/dossiers/{$dossier->reference}?focus=pieces&tab=informations");

        $response->assertOk();
    }

    // ── Société externe (E5, cas T5) ─────────────────────────────────────────

    public function test_une_societe_creee_a_la_main_est_externe_et_part_dun_etat_initial(): void
    {
        $reponse = $this->actingAs($this->clerc())->postJson('/societes', [
            'denomination'     => 'OMEGA SARL',
            'forme'            => 'SARL',
            'capital_chiffres' => 5_000_000,
            'nombre_parts'     => 500,
            'siege_ville'      => 'Conakry',
            'rccm_numero'      => 'GN-CON-2019-B-0042',
        ]);

        $reponse->assertCreated()->assertJsonPath('externe', true);

        $societe = Societe::where('denomination', 'OMEGA SARL')->firstOrFail();

        // Le cas T5 : « l'historique commence à l'état initialisé ». Sans cette ligne, le
        // premier diff de la timeline n'aurait rien à quoi se comparer.
        $initiale = SocieteHistorique::where('societe_id', $societe->id)->whereNull('dossier_id')->first();

        $this->assertNotNull($initiale, "Une société externe doit naître avec sa ligne d'état initial.");
        $this->assertSame(['etat_initial'], $initiale->operations);
        $this->assertNull($initiale->etat_avant);
        $this->assertSame('OMEGA SARL', $initiale->etat_apres['denomination'] ?? null);
    }

    public function test_une_societe_issue_dun_dossier_nest_pas_externe(): void
    {
        // Le pivot de tout le dispositif : c'est `dossier_id` qui dit d'où vient l'état, et
        // rien d'autre. Une colonne `origine` séparée aurait pu le contredire.
        $societe = $this->societe();
        $dossier = $this->dossierModification($societe, []);
        $societe->update(['dossier_id' => $dossier->id]);

        $this->assertFalse($societe->fresh()->estExterne());
        $this->assertFalse($societe->fresh()->exigePiecesConstitutives());
    }

    public function test_letat_initial_ne_se_pose_quune_fois(): void
    {
        $societe = $this->societe();
        $service = app(SocieteMutationService::class);
        $etats   = app(EtatSocieteService::class);

        $this->assertTrue($service->poserEtatInitial($societe, $etats));
        $this->assertFalse($service->poserEtatInitial($societe, $etats));

        $this->assertSame(
            1,
            SocieteHistorique::where('societe_id', $societe->id)->whereNull('dossier_id')->count(),
        );
    }

    // ═══ Ce que le formulaire reçoit ═════════════════════════════════════

    public function test_la_fiche_societe_sert_sa_composition_au_formulaire(): void
    {
        // ⚠️ `GET /societes/{id}` ne renvoyait que `personnesConnues` — noms, rôles, fiches,
        // **sans les parts**. Le clerc devait donc retaper une répartition que le registre
        // connaissait, puis recalculer les pourcentages de tête.
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou CAMARA', 600);
        $this->associe($societe, 'Aïssatou SOW', 400);

        $this->actingAs($this->clerc())
            ->getJson("/societes/{$societe->id}")
            ->assertOk()
            ->assertJsonPath('etat.nombre_parts', 1000)
            ->assertJsonPath('etat.associes.0.nom', 'Mamadou CAMARA')
            ->assertJsonPath('etat.associes.0.nb_parts', 600)
            ->assertJsonPath('etat.associes.0.pourcentage', 60)
            ->assertJsonPath('etat.associes.1.nb_parts', 400)
            ->assertJsonPath('etat.associes.1.pourcentage', 40);
    }

    // ═══ L'appariement : par la fiche, pas par la chaîne ═════════════════

    public function test_la_fiche_client_dun_associe_survit_a_une_operation(): void
    {
        // ⚠️ `repartition()` forçait `client_id` à `null`. Après une seule opération, le
        // registre ne savait donc plus quelle fiche désignait quel associé — et l'appariement
        // suivant retombait sur la comparaison de chaînes.
        $societe = $this->societe();
        $client  = Client::create([
            'type'       => 'physique',
            'statut'     => 'prospect',
            'prenom_nom' => 'Mamadou CAMARA',
        ]);

        SocieteAssocie::create([
            'societe_id'  => $societe->id,
            'client_id'   => $client->id,
            'nom'         => 'Mamadou CAMARA',
            'nb_parts'    => 600,
            'date_entree' => '2024-01-01',
        ]);
        $this->associe($societe, 'Aïssatou SOW', 400);

        $dossier = $this->dossierModification($societe, [
            'modif.types'         => ['Cession de parts sociales'],
            'ag.date_effet'       => '15/03/2026',
            'modif.cedants'       => [['nom' => 'Mamadou CAMARA', 'parts_cedees' => '100']],
            'modif.cessionnaires' => [['nom' => 'Aïssatou SOW', 'parts_acquises' => '100']],
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $mamadou = $societe->fresh()->associesActuels()->firstWhere('nom', 'Mamadou CAMARA');

        $this->assertSame(500, (int) $mamadou->nb_parts);
        $this->assertSame(
            $client->id,
            $mamadou->client_id,
            "La nouvelle ligne doit garder la fiche de l'associé : sans elle, le registre ne sait "
            . 'plus qui est qui dès la deuxième opération.',
        );
    }

    public function test_un_gerant_sortant_mal_orthographie_est_quand_meme_cloture(): void
    {
        // ⚠️ `appliquerGerance()` comparait **en SQL, à la chaîne exacte**
        // (`where('nom', $sortant)`). Un gérant sortant tapé avec un accent ou une majuscule
        // de différence n'était pas clôturé — et comme le nouveau était créé juste après, la
        // société se retrouvait avec **deux gérants en fonction**. Deux lignes valides, rien
        // pour le signaler.
        $societe = $this->societe();

        SocieteGerant::create([
            'societe_id'      => $societe->id,
            'nom'             => 'Aïssatou SOW',
            'statutaire'      => true,
            'date_nomination' => '2024-01-01',
        ]);

        $dossier = $this->dossierModification($societe, [
            'modif.types'                 => ['Changement de gérant statutaire'],
            'ag.date_effet'               => '15/03/2026',
            // Sans tréma, et en capitales : la même personne pour un humain.
            'gerant_sortant.prenom_nom'   => 'AISSATOU SOW',
            'gerant_sortant.motif_fin'    => 'demission',
            'gerant_entrant.prenom_nom'   => 'Ibrahima BARRY',
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $enFonction = $societe->fresh()->gerantsEnFonction()->pluck('nom');

        $this->assertSame(
            ['Ibrahima BARRY'],
            $enFonction->all(),
            'Le mandat sortant doit être clôturé malgré l\'orthographe : ' . $enFonction->implode(' | '),
        );
    }

    public function test_un_gerant_deja_en_fonction_nest_pas_duplique(): void
    {
        // L'autre sens de la même comparaison stricte : un gérant déjà en fonction sous une
        // orthographe voisine était considéré comme absent, donc **créé une seconde fois**.
        $societe = $this->societe();

        SocieteGerant::create([
            'societe_id'      => $societe->id,
            'nom'             => 'Mamadou CAMARA',
            'statutaire'      => true,
            'date_nomination' => '2024-01-01',
        ]);

        $dossier = $this->dossierModification($societe, [
            'modif.types'               => ['Changement de gérant statutaire'],
            'ag.date_effet'             => '15/03/2026',
            'gerant_entrant.prenom_nom' => 'mamadou  camara',
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $this->assertCount(
            1,
            $societe->fresh()->gerantsEnFonction()->get(),
            'Le même gérant ne doit pas être nommé deux fois pour une variation de casse.',
        );
    }

    public function test_une_variation_de_casse_ne_cree_pas_dassocie_fantome(): void
    {
        // ⚠️ L'appariement se faisait par `keyBy('nom')`, en comparaison stricte. Le
        // questionnaire enregistre ce que le clerc a tapé : « Aissatou  SOW » au lieu de
        // « Aïssatou SOW » faisait **sortir** l'associée réelle et **entrer** une homonyme
        // avec ses parts. La somme restait juste — rien ne le signalait.
        $societe = $this->societe();
        $this->associe($societe, 'Mamadou CAMARA', 600);
        $this->associe($societe, 'Aïssatou SOW', 400);

        $dossier = $this->dossierModification($societe, [
            'modif.types'   => ['Cession de parts sociales'],
            'ag.date_effet' => '15/03/2026',
            // Deux espaces, et une casse différente — la même personne pour un humain.
            'modif.cedants'       => [['nom' => 'MAMADOU  CAMARA', 'parts_cedees' => '100']],
            'modif.cessionnaires' => [['nom' => 'Aïssatou SOW', 'parts_acquises' => '100']],
        ]);

        app(SocieteMutationService::class)->appliquer($dossier, $this->clerc());

        $actuels = $societe->fresh()->associesActuels()->get();

        $this->assertCount(
            2,
            $actuels,
            'Une variation de casse ou d\'espaces ne doit pas créer un troisième associé : '
            . $actuels->pluck('nom')->implode(' | '),
        );
    }
}
