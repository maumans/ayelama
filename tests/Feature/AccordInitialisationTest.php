<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\ExigenceAccord;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\DossierStepService;
use App\Support\AccordsInitialisation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La pièce d'accord de l'Initialisation, déclarée par type d'acte — 2026-09-28.
 *
 * L'exigence était **uniforme** pour les 24 types : tous devaient téléverser une pièce d'accord
 * signée avant de produire le moindre acte. `Dossier::pieceAccordAttendue()` savait déjà en
 * changer le nom selon le type, jamais son caractère obligatoire — si bien qu'un dossier de
 * dissolution réclamait une « fiche de recueil signée » imprimée par l'étude, et restait bloqué
 * dessus.
 *
 * Mesuré ce jour-là : le blocage date du 2026-08-14, et **13 des 20 dossiers** ayant dépassé
 * l'Initialisation n'ont aucune pièce d'accord — tous antérieurs à cette date.
 *
 * Le test qui compte le plus est celui de **non-régression** : rien ne doit changer pour une
 * constitution ni pour une modification, les deux seuls cas avec des données réelles.
 */
class AccordInitialisationTest extends TestCase
{
    use RefreshDatabase;

    private function typeActe(string $code, string $categorie = 'societe'): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => $categorie, 'prefixe_reference' => 'TST'],
        );
    }

    private function dossier(string $code, array $donnees = [], ?Societe $societe = null): Dossier
    {
        $notaire = User::factory()->create();

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'societe_id'   => $societe?->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => $notaire->id,
            'notaire_id'   => $notaire->id,
            'reviseur_id'  => $notaire->id,
            'objet'        => "Dossier de test de la pièce d'accord à l'Initialisation",
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    /** Ce que le serveur reproche à ce dossier, à plat. */
    private function blocages(Dossier $dossier): array
    {
        $methode = new \ReflectionMethod(DossierStepService::class, 'erreursDeConstitution');
        $methode->setAccessible(true);

        return $methode->invoke(app(DossierStepService::class), $dossier);
    }

    // ═══ Non-régression : rien ne change pour les types existants ════════

    public function test_une_constitution_exige_toujours_la_fiche_signee(): void
    {
        $attendue = AccordsInitialisation::reference('SOC-SARLU');

        $this->assertSame(ExigenceAccord::Bloquante, $attendue['exigence']);
        $this->assertSame('Accord client — questionnaire signé', $attendue['nom']);
        $this->assertTrue($attendue['imprimable'], "L'étude imprime la fiche de recueil et la fait signer.");
        $this->assertFalse($attendue['aVerifier']);

        $this->assertArrayHasKey('accord_client', $this->blocages($this->dossier('SOC-SARLU')));
    }

    public function test_une_modification_exige_toujours_la_decision_des_associes(): void
    {
        $attendue = AccordsInitialisation::reference('SOC-MOD');

        $this->assertSame(ExigenceAccord::Bloquante, $attendue['exigence']);
        $this->assertSame("Décision d'assemblée des associés", $attendue['nom']);
        $this->assertFalse($attendue['imprimable'], "Le client apporte la décision, l'étude n'imprime rien.");
    }

    public function test_un_type_dacte_inconnu_retombe_sur_le_comportement_historique(): void
    {
        // Les types d'acte sont des **données** : ils se créent depuis Paramètres et dans les
        // tests. Un `match` exhaustif exploserait ici ; le registre a donc un repli, et ce repli
        // doit être exactement l'ancien comportement — sans quoi ouvrir une nomenclature
        // changerait en silence les règles d'avancement.
        $attendue = AccordsInitialisation::reference('TST-9999');

        $this->assertSame(ExigenceAccord::Bloquante, $attendue['exigence']);
        $this->assertSame('Accord client — questionnaire signé', $attendue['nom']);

        $this->assertArrayHasKey('accord_client', $this->blocages($this->dossier('TST-9999', [], null)));
    }

    // ═══ La dissolution ══════════════════════════════════════════════════

    public function test_une_dissolution_nest_plus_bloquee_par_la_piece_daccord(): void
    {
        // Le défaut corrigé : c'était le **seul** obstacle des deux dossiers réels.
        $blocages = $this->blocages($this->dossier('SOC-DIS', [
            'dissolution.phase'          => 'Dissolution anticipée',
            'soc.denomination'           => 'Faya Distribution SARLU',
            'dissolution.date_assemblee' => '15/03/2026',
            'liquidateur.prenom_nom'     => 'Ibrahima DIALLO',
        ]));

        $this->assertSame([], $blocages);
    }

    public function test_la_dissolution_annonce_quand_meme_la_piece(): void
    {
        // « Attendue » n'est pas « sans objet » : si la carte disparaissait, la question ne se
        // poserait jamais et l'arbitrage de l'étude n'arriverait pas.
        $attendue = AccordsInitialisation::reference('SOC-DIS');

        $this->assertSame(ExigenceAccord::Attendue, $attendue['exigence']);
        $this->assertTrue($attendue['exigence']->saffiche());
        $this->assertFalse($attendue['exigence']->bloque());
    }

    public function test_lexigence_de_dissolution_est_marquee_a_verifier(): void
    {
        // ⚠️ Ce test échouera le jour où l'étude tranchera — **c'est son rôle** : lever le
        // marqueur et retirer la mention « à confirmer » de l'écran, au lieu de les oublier.
        $attendue = AccordsInitialisation::reference('SOC-DIS');

        $this->assertTrue($attendue['aVerifier']);
        $this->assertNotEmpty(
            $attendue['source'],
            "Une exigence non arbitrée doit dire d'où vient son cadrage.",
        );
    }

    // ═══ La conséquence, composée et jamais écrite en dur ════════════════

    public function test_les_instructions_disent_la_consequence_du_niveau(): void
    {
        // Les deux libellés historiques se terminaient par « Le dossier ne pourra pas passer en
        // certification sans elle » : la conséquence était encodée dans le texte, et devenait
        // fausse dès qu'on basculait le niveau. Elle est désormais composée.
        $this->assertStringContainsString(
            'ne pourra pas passer',
            AccordsInitialisation::reference('SOC-SARLU')['instructions'],
        );
        $this->assertStringContainsString(
            "n'est pas exigée pour avancer",
            AccordsInitialisation::reference('SOC-DIS')['instructions'],
        );
    }

    public function test_une_surcharge_reecrit_la_consequence_sans_la_dedoubler(): void
    {
        $type = $this->typeActe('SOC-DIS');
        $type->update(['exigence_accord' => ExigenceAccord::Bloquante]);

        $instructions = AccordsInitialisation::pour($type->fresh())['instructions'];

        $this->assertStringContainsString('ne pourra pas passer', $instructions);
        $this->assertStringNotContainsString("n'est pas exigée pour avancer", $instructions);
    }

    // ═══ La surcharge depuis Paramètres ══════════════════════════════════

    public function test_une_surcharge_prime_sur_la_reference_et_bloque_reellement(): void
    {
        // Sans ce test, `exigence_accord` rejoindrait `fiche_modification_obligatoire` et
        // `actes_requis` : deux colonnes de cette même table que personne ne lit.
        $dossier = $this->dossier('SOC-DIS', [
            'dissolution.phase'          => 'Dissolution anticipée',
            'soc.denomination'           => 'Faya Distribution SARLU',
            'dissolution.date_assemblee' => '15/03/2026',
            'liquidateur.prenom_nom'     => 'Ibrahima DIALLO',
        ]);

        $this->assertSame([], $this->blocages($dossier));

        $dossier->typeActe->update(['exigence_accord' => ExigenceAccord::Bloquante]);

        $this->assertArrayHasKey('accord_client', $this->blocages($dossier->fresh()));
    }

    public function test_null_rend_la_main_a_la_reference(): void
    {
        $type = $this->typeActe('SOC-DIS');

        $type->update(['exigence_accord' => ExigenceAccord::Bloquante]);
        $this->assertTrue(AccordsInitialisation::divergeDeLaReference($type->fresh()));

        $type->update(['exigence_accord' => null]);
        $this->assertFalse(AccordsInitialisation::divergeDeLaReference($type->fresh()));
        $this->assertSame(ExigenceAccord::Attendue, AccordsInitialisation::pour($type->fresh())['exigence']);
    }

    public function test_une_surcharge_leve_le_marqueur_a_verifier(): void
    {
        // Une exigence posée à la main n'est plus une hypothèse : continuer d'afficher
        // « à confirmer avec l'étude » excuserait une décision déjà prise.
        $type = $this->typeActe('SOC-DIS');
        $type->update(['exigence_accord' => ExigenceAccord::Attendue]);

        $attendue = AccordsInitialisation::pour($type->fresh());

        $this->assertFalse($attendue['aVerifier']);
        $this->assertSame('', $attendue['source']);
    }

    // ═══ Le registre lui-même ════════════════════════════════════════════

    public function test_le_registre_ne_declare_que_les_types_arbitres(): void
    {
        // Instantané volontaire : ajouter une dérogation oblige à venir ici déclarer son
        // intention, plutôt que de l'ajouter en passant.
        $this->assertSame(['SOC-MOD', 'SOC-DIS'], AccordsInitialisation::codesDerogeants());
    }

    public function test_aucune_entree_du_registre_ne_vise_un_type_inexistant(): void
    {
        // L'inverse exact du sort de `actes_requis`, dont la colonne a disparu sans que la
        // déclaration suive.
        $this->seed(\Database\Seeders\TypeActeSeeder::class);

        foreach (AccordsInitialisation::codesDerogeants() as $code) {
            $this->assertNotNull(
                TypeActe::where('code', $code)->first(),
                "Le registre déroge pour « {$code} », qui n'existe pas au référentiel.",
            );
        }
    }

    // ═══ Les règles métier remontent au panneau ══════════════════════════

    public function test_une_anomalie_metier_est_exposee_au_panneau_et_bloque(): void
    {
        // Les deux, et c'est le point : le panneau « conditions requises » n'affichait pas ces
        // règles, si bien qu'un clerc déposait l'accord, cliquait « Avancer », et découvrait
        // *alors seulement* l'incohérence. Le docbloc de ReglesSocieteService affirmait pourtant
        // le contraire.
        $societe = Societe::create(['denomination' => 'FINAGRO', 'forme' => 'SARL']);
        $dossier = $this->dossier('SOC-DIS', [
            'dissolution.phase'      => 'Clôture de la liquidation',
            'cloture.date_assemblee' => '20/09/2026',
        ], $societe);

        $anomalies = app(DossierStepService::class)->anomaliesMetier($dossier);

        $this->assertContains('dissolution_cycle_vie', array_column($anomalies, 'cle'));
        $this->assertArrayHasKey('dissolution_cycle_vie', $this->blocages($dossier));
    }

    public function test_les_anomalies_ne_sont_pas_calculees_hors_des_etapes_concernees(): void
    {
        // `ReglesSocieteService` charge toute la table des questionnaires pour contrôler
        // l'unicité de dénomination : le payer à l'affichage d'un dossier déjà signé serait
        // gratuit, et la fiche s'ouvre bien plus souvent qu'elle n'avance.
        $dossier = $this->dossier('SOC-DIS');
        $dossier->update(['etape' => EtapeDossier::Signature]);

        $this->assertSame([], app(DossierStepService::class)->anomaliesMetier($dossier->fresh()));
    }

    public function test_deux_regles_homonymes_ne_seffacent_pas(): void
    {
        // `array_merge` écrase à clé égale — le code le documente comme un risque. La liste à
        // plat concatène : deux messages sous la même clé restent deux messages.
        $societe = Societe::create(['denomination' => 'FINAGRO', 'forme' => 'SARL']);
        $dossier = $this->dossier('SOC-DIS', ['dissolution.phase' => 'Clôture de la liquidation'], $societe);

        $anomalies = app(DossierStepService::class)->anomaliesMetier($dossier);
        $cles      = array_column($anomalies, 'cle');

        // Chaque entrée porte sa propre clé et son propre texte, aucune n'est nulle.
        foreach ($anomalies as $a) {
            $this->assertNotEmpty($a['cle']);
            $this->assertNotEmpty($a['texte']);
        }
        $this->assertContains('dissolution_cycle_vie', $cles);
        $this->assertContains('dissolution_assemblee', $cles);
    }

    // ═══ L'indicateur de la liste suit ═══════════════════════════════════

    public function test_le_bouton_avancer_de_la_liste_suit_le_niveau_dexigence(): void
    {
        // `constitutionComplete()` codait `'accord_client'` en dur : le bouton serait resté grisé
        // sur une dissolution que le serveur accepte — la divergence à l'envers de celle
        // corrigée le 2026-08-03.
        $dossier = $this->dossier('SOC-DIS', [
            'dissolution.phase'          => 'Dissolution anticipée',
            'soc.denomination'           => 'Faya Distribution SARLU',
            'dissolution.date_assemblee' => '15/03/2026',
            'liquidateur.prenom_nom'     => 'Ibrahima DIALLO',
        ]);

        $admin = User::factory()->create(['actif' => true]);
        $admin->syncRoles([RoleUtilisateur::Administrateur]);

        $this->actingAs($admin)
            ->get('/dossiers')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where(
                'dossiers.data.' . $this->rangDansLaListe($page->toArray(), $dossier->reference) . '.peutAvancer',
                true,
            ));
    }

    /** Position d'un dossier dans la liste paginée, l'ordre n'étant pas garanti. */
    private function rangDansLaListe(array $page, string $reference): int
    {
        foreach ($page['props']['dossiers']['data'] as $i => $ligne) {
            if (($ligne['reference'] ?? null) === $reference) {
                return $i;
            }
        }

        $this->fail("Le dossier {$reference} n'apparaît pas dans la liste.");
    }
}
