<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\JalonLiquidation;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutSociete;
use App\Enums\VarianteDissolution;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Notifications\EcheanceLiquidationNotification;
use App\Services\ActesGeneratorService;
use App\Services\DossierStepService;
use App\Services\SocieteCycleVieService;
use App\Services\SocieteMutationService;
use App\Support\EffetsFicheSociete;
use App\Support\VariantesTypeActe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Dissolution-liquidation (`SOC-DIS`) — 2026-09-28.
 *
 * Le type d'acte existait depuis l'origine et **ne fonctionnait pas** : ses deux gabarits
 * étaient des marque-places, il héritait des barèmes de constitution (une « Immatriculation
 * RCCM » facturée sur une société qu'on radie), son questionnaire n'avait pas de sélecteur de
 * société — d'où les incohérences de capital constatées sur un dossier réel — et aucun cycle
 * de vie de société n'existait.
 *
 * Deux thèmes de tests qui n'ont l'air de rien et portent l'essentiel :
 *
 *   - **la non-régression `SOC-MOD`**, parce que cette passe a traversé la modification
 *     statutaire, qui est en service ;
 *   - **la doctrine des délais**, parce qu'aucun n'est sourcé et que le premier réflexe, en
 *     relisant ce code, sera d'en faire des règles bloquantes.
 */
class DissolutionLiquidationTest extends TestCase
{
    use RefreshDatabase;

    private function typeActe(string $code = 'SOC-DIS'): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    private function societe(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination'     => 'Faya Distribution SARLU',
            'forme'            => 'SARLU',
            'rccm_numero'      => 'GN-CON-2020-B-0001',
            'capital_chiffres' => 50_000_000,
        ], $attributs));
    }

    private function dossier(array $donnees = [], ?Societe $societe = null, string $code = 'SOC-DIS'): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'societe_id'   => $societe?->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test de dissolution',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    private function donneesDissolution(array $extra = []): array
    {
        return array_merge([
            'dissolution.phase'          => 'Dissolution anticipée',
            'dissolution.date_assemblee' => '15/03/2026',
            'liquidateur.prenom_nom'     => 'Ibrahima DIALLO',
            'liquidateur.qualite'        => 'Associé',
        ], $extra);
    }

    // ═══ Les variantes ═══════════════════════════════════════════════════

    public function test_soc_dis_est_inscrit_au_registre_des_variantes(): void
    {
        $this->assertTrue(VariantesTypeActe::existePour('SOC-DIS'));
        $this->assertSame(
            ['dissolution', 'cloture_liquidation'],
            VariantesTypeActe::valeurs('SOC-DIS'),
        );
    }

    public function test_la_phase_est_lue_depuis_le_questionnaire(): void
    {
        $dossier = $this->dossier($this->donneesDissolution());

        $this->assertSame(['dissolution'], VariantesTypeActe::valeursDuDossier($dossier));
    }

    public function test_une_phase_inconnue_est_ignoree_sans_erreur(): void
    {
        // Un libellé mal orthographié doit remonter comme « phase manquante » par les règles,
        // message actionnable — pas comme une erreur d'enum au milieu d'une génération d'actes.
        $dossier = $this->dossier(['dissolution.phase' => 'Liquidation amiable et définitive']);

        $this->assertSame([], VariantesTypeActe::valeursDuDossier($dossier));
    }

    // ═══ La référence écrite : `null`, délibérément ══════════════════════

    public function test_la_dissolution_ne_declare_aucune_reference_ecrite(): void
    {
        // Le compte rendu de juillet 2026 ne mentionne ni dissolution, ni liquidation, ni
        // radiation. Lui fabriquer une liste imposée la ferait afficher comme faisant foi par
        // l'écran Processus, et `divergeDeLaReference()` la défendrait contre les corrections
        // de l'étude. Une contrainte inventée est pire qu'une contrainte absente.
        foreach (VarianteDissolution::cases() as $variante) {
            $this->assertNull(
                $variante->documentsReference(),
                "« {$variante->label() } » ne doit déclarer aucune référence écrite tant qu'aucune source ne l'établit.",
            );
        }

        $this->assertNull(\App\Models\DocumentAttendu::reference('SOC-DIS', 'dissolution'));
        $this->assertFalse(\App\Models\DocumentAttendu::divergeDeLaReference('SOC-DIS', 'dissolution', []));
    }

    public function test_la_modification_conserve_sa_reference_ecrite(): void
    {
        // Non-régression : la généralisation du contrat ne doit pas avoir désarmé SOC-MOD,
        // dont la règle 9 du CR, elle, existe bel et bien.
        $reference = \App\Models\DocumentAttendu::reference('SOC-MOD', 'capital_cession');

        $this->assertIsArray($reference);
        $this->assertArrayHasKey('acte_cession', $reference);
    }

    // ═══ Les actes attendus ══════════════════════════════════════════════

    public function test_un_dossier_de_dissolution_annonce_ses_actes_meme_sans_gabarit(): void
    {
        // Avant le 2026-09-28, un dossier de dissolution n'affichait **rien** : ni acte, ni
        // manque. Ses deux gabarits sont inactifs (fichiers non fournis), et un marque-place
        // inactif était indiscernable d'une procédure achevée.
        $typeActe = $this->typeActe();
        $modele   = ModeleActe::create([
            'nom'            => 'Acte de dissolution et liquidation',
            'type_document'  => 'acte_principal',
            'chemin_fichier' => 'modeles/societe/dissolution.docx',
            'version'        => '1.0',
            'est_actif'      => false,
        ]);
        $modele->rattachements()->create(['type_acte_id' => $typeActe->id, 'variante' => 'dissolution']);

        $prevus = app(ActesGeneratorService::class)->actesPrevus($typeActe, ['dissolution']);

        $this->assertCount(1, $prevus);
        $this->assertSame('Acte de dissolution et liquidation', $prevus[0]['nom']);
        $this->assertFalse($prevus[0]['a_gabarit'], 'Le gabarit manque : il doit être signalé, pas passé sous silence.');
    }

    public function test_le_gabarit_dune_phase_ne_sert_pas_a_lautre(): void
    {
        $typeActe = $this->typeActe();
        $modele   = ModeleActe::create([
            'nom'            => 'PV de clôture de liquidation',
            'type_document'  => 'acte_principal',
            'chemin_fichier' => 'modeles/societe/cloture.docx',
            'version'        => '1.0',
            'est_actif'      => false,
        ]);
        $modele->rattachements()->create(['type_acte_id' => $typeActe->id, 'variante' => 'cloture_liquidation']);

        $this->assertCount(0, app(ActesGeneratorService::class)->actesPrevus($typeActe, ['dissolution']));
        $this->assertCount(1, app(ActesGeneratorService::class)->actesPrevus($typeActe, ['cloture_liquidation']));
    }

    public function test_un_type_sans_variante_reste_bavard_comme_avant(): void
    {
        // La passe qui rend les gabarits inactifs visibles est **restreinte** aux types
        // déclinés : 54 des 69 modèles du projet sont inactifs, répartis sur 22 des 24 types.
        // L'étendre à tous aurait fait afficher sept actes manquants sur une SA, noyant le
        // signal sous le bruit.
        $vente  = $this->typeActe('VTE-IMM');
        $modele = ModeleActe::create([
            'nom'            => 'Vente immobilière (marque-place)',
            'type_document'  => 'acte_principal',
            'chemin_fichier' => 'modeles/vente/vente.docx',
            'version'        => '1.0',
            'est_actif'      => false,
        ]);
        $modele->rattachements()->create(['type_acte_id' => $vente->id, 'variante' => null]);

        $this->assertSame([], app(ActesGeneratorService::class)->actesPrevus($vente, []));
    }

    // ═══ Le cycle de vie de la fiche ═════════════════════════════════════

    public function test_une_fiche_neuve_est_active(): void
    {
        $this->assertSame(StatutSociete::Active, $this->societe()->statut);
    }

    public function test_lexpedition_porte_la_dissolution_a_la_fiche(): void
    {
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(), $societe);

        app(SocieteCycleVieService::class)->appliquer($dossier);

        $societe->refresh();

        $this->assertSame(StatutSociete::EnLiquidation, $societe->statut);
        // ⚠️ La date vient de l'assemblée, pas de `now()` : un dossier peut entrer en
        // Expédition des mois après la décision, et c'est elle qui fait courir les échéances.
        $this->assertSame('15/03/2026', $societe->dissolution_at->format('d/m/Y'));
        $this->assertSame('Ibrahima DIALLO', $societe->liquidateurActuel());
        $this->assertSame('Associé', $societe->liquidateurQualite());
    }

    public function test_la_date_dassemblee_nest_pas_inversee(): void
    {
        // Le 15 mars, pas le 3 mai. `Carbon::parse` a inversé sept dates au jour et au mois
        // dans ce projet (décision #40) : la conversion se fait par `createFromFormat('d/m/Y')`.
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(['dissolution.date_assemblee' => '03/05/2026']), $societe);

        app(SocieteCycleVieService::class)->appliquer($dossier);

        $this->assertSame('2026-05-03', $societe->refresh()->dissolution_at->format('Y-m-d'));
    }

    public function test_appliquer_deux_fois_ne_change_rien_la_seconde(): void
    {
        // Un dossier renvoyé en correction repasse en Expédition : l'effet doit être idempotent.
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(), $societe);
        $service = app(SocieteCycleVieService::class);

        $this->assertNotEmpty($service->appliquer($dossier));
        $this->assertSame([], $service->appliquer($dossier->fresh()));
    }

    public function test_la_cloture_porte_le_second_jalon(): void
    {
        $societe = $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => '2026-03-15',
            'direction'      => ['liquidateur' => 'Ibrahima DIALLO'],
        ]);
        $dossier = $this->dossier([
            'dissolution.phase'      => 'Clôture de la liquidation',
            'cloture.date_assemblee' => '20/09/2029',
        ], $societe);

        app(SocieteCycleVieService::class)->appliquer($dossier);

        $societe->refresh();

        $this->assertSame(StatutSociete::LiquidationCloturee, $societe->statut);
        $this->assertSame('20/09/2029', $societe->cloture_liquidation_at->format('d/m/Y'));
        // Le liquidateur n'est pas réécrit : le questionnaire de clôture ne le demande plus,
        // et le relire l'effacerait.
        $this->assertSame('Ibrahima DIALLO', $societe->liquidateurActuel());
    }

    public function test_la_radiation_nest_jamais_posee_automatiquement(): void
    {
        // Elle n'est prouvée que par la pièce du greffe, qu'aucune étape de dossier ne
        // constate. La rattacher à une formalité supposerait de déclarer laquelle vaut
        // radiation — donc d'inventer.
        foreach (VarianteDissolution::cases() as $variante) {
            $this->assertNotSame(StatutSociete::Radiee, $variante->statutApres());
        }
    }

    public function test_le_retour_en_correction_defait_le_changement_detat(): void
    {
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(), $societe);
        $service = app(SocieteCycleVieService::class);

        $service->appliquer($dossier);
        $service->annuler($dossier->fresh());

        $societe->refresh();

        $this->assertSame(StatutSociete::Active, $societe->statut);
        $this->assertNull($societe->dissolution_at);
    }

    public function test_le_retour_en_correction_nefface_pas_le_travail_dun_autre(): void
    {
        // Garde indispensable : si la fiche n'est plus dans l'état que ce dossier avait posé,
        // quelqu'un d'autre est passé après lui.
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(), $societe);
        $service = app(SocieteCycleVieService::class);

        $service->appliquer($dossier);
        $societe->update(['statut' => StatutSociete::LiquidationCloturee]);

        $this->assertSame([], $service->annuler($dossier->fresh()));
        $this->assertSame(StatutSociete::LiquidationCloturee, $societe->refresh()->statut);
    }

    public function test_le_passage_en_expedition_declenche_leffet_et_le_renvoi_le_defait(): void
    {
        // Le chemin **réel**, par `DossierStepService::avancer()`. Les tests ci-dessus appellent
        // le service d'effet directement : ils ne diraient rien si le branchement dans le
        // service d'étape était oublié — or c'est justement ce branchement qui a changé le
        // 2026-09-28 (appel en dur remplacé par le registre).
        $societe = $this->societe();
        $dossier = $this->dossier($this->donneesDissolution(), $societe);
        $dossier->update(['etape' => EtapeDossier::Formalites]);

        $formaliste = User::factory()->create();
        $formaliste->syncRoles([RoleUtilisateur::Formaliste]);

        $etapes = app(\App\Services\DossierStepService::class);

        $this->assertSame(EtapeDossier::Expedition, $etapes->avancer($dossier->fresh(), $formaliste)->etape);
        $this->assertSame(StatutSociete::EnLiquidation, $societe->refresh()->statut);

        // Renvoi en correction : la fiche redevient ce qu'elle était.
        $etapes->reculer($dossier->fresh(), $formaliste, 'Erreur de date');

        $this->assertSame(StatutSociete::Active, $societe->refresh()->statut);
        $this->assertNull($societe->dissolution_at);
    }

    // ═══ Non-régression `SOC-MOD` — ce qui compte le plus ════════════════

    public function test_la_garde_de_la_mutation_statutaire_est_inchangee(): void
    {
        // `concerne()` reprend mot pour mot la garde qui ouvrait `appliquer()`. Le test rend
        // l'affirmation vérifiable plutôt que supposée.
        $service = app(SocieteMutationService::class);

        $avecFiche = $this->dossier(['modif.types' => ['Transfert du siège social']], $this->societe(), 'SOC-MOD');
        $sansFiche = $this->dossier(['modif.types' => ['Transfert du siège social']], null, 'SOC-MOD');
        $dissolution = $this->dossier($this->donneesDissolution(), $this->societe());

        $this->assertTrue($service->concerne($avecFiche));
        $this->assertFalse($service->concerne($sansFiche), 'Sans fiche rattachée, il n\'y a rien à muter.');
        $this->assertFalse($service->concerne($dissolution), 'Une dissolution n\'est pas une modification statutaire.');
    }

    public function test_le_registre_naiguille_quun_seul_effet_par_dossier(): void
    {
        $modification = $this->dossier(['modif.types' => ['Transfert du siège social']], $this->societe(), 'SOC-MOD');
        $dissolution  = $this->dossier($this->donneesDissolution(), $this->societe());

        $this->assertEquals(
            [SocieteMutationService::class],
            array_map('get_class', EffetsFicheSociete::pour($modification)),
        );
        $this->assertEquals(
            [SocieteCycleVieService::class],
            array_map('get_class', EffetsFicheSociete::pour($dissolution)),
        );
    }

    public function test_la_mutation_statutaire_ne_revert_pas_mais_avertit(): void
    {
        // Asymétrie assumée : ici l'état précédent devrait être reconstitué depuis le
        // questionnaire, or une correction portée au registre entre-temps serait écrasée sans
        // trace. Un défaut bruyant vaut mieux qu'un revert hasardeux.
        $societe = $this->societe();
        $dossier = $this->dossier(['modif.types' => ['Transfert du siège social']], $societe, 'SOC-MOD');

        $this->assertSame([], app(SocieteMutationService::class)->annuler($dossier));

        $this->assertDatabaseHas('journal_activites', [
            'dossier_id' => $dossier->id,
            'type'       => 'societe',
        ]);
    }

    // ═══ Les barèmes : rien d'inventé ════════════════════════════════════

    public function test_une_dissolution_nherite_plus_de_la_grille_de_constitution(): void
    {
        $this->seed(\Database\Seeders\BaremeSeeder::class);

        $actifs = Bareme::whereHas('typeActe', fn ($q) => $q->where('code', 'SOC-DIS'))
            ->where('actif', true)
            ->pluck('libelle');

        // Facturer une « Immatriculation RCCM » ou une « Obtention NIF » sur une société qu'on
        // radie est faux — et c'est ce que la base contenait.
        $this->assertNotContains('Immatriculation RCCM', $actifs);
        $this->assertNotContains('Obtention NIF', $actifs);
        $this->assertNotContains("Droits d'enregistrement des Statuts et de la DNSV", $actifs);
    }

    public function test_la_chaine_de_dependance_entre_formalites_tient_apres_seed(): void
    {
        // Le test d'une ligne qui aurait attrapé la régression du 2026-09-28 : le seeder
        // référençait un libellé de barème renommé, PHP 8 rendait `null` avec deux
        // avertissements — **pas une erreur** — et la seule chaîne de dépendance entre
        // formalités du projet disparaissait en silence.
        // ⚠️ Il faut un type de **constitution** : depuis le 2026-09-28, la grille société
        // n'est plus posée sur SOC-MOD ni SOC-DIS, et la chaîne de dépendance vit dans cette
        // grille. Sans cette ligne le test passerait à vide, ce qui est pire qu'un échec.
        $this->typeActe('SOC-SARL');
        $this->seed(\Database\Seeders\BaremeSeeder::class);

        $this->assertGreaterThan(
            0,
            Bareme::whereNotNull('depend_de_bareme_id')->count(),
            'Aucun barème ne dépend plus d\'un autre : la référence par libellé est cassée.',
        );
    }

    // ═══ Ce que les actes réels de l'étude ont imposé (2026-09-29) ══════

    public function test_la_mention_de_liquidation_est_derivee_du_statut(): void
    {
        // Les deux actes de l'étude écrivaient deux formulations pour la même société au même
        // moment : « (EN COURS DE LIQUIDATION) » au PV, « (EN LIQUIDATION) » à l'insertion. La
        // mention se dérive donc du statut — une balise, pas deux saisies libres.
        $this->assertSame('', StatutSociete::Active->mentionActe());
        $this->assertSame('(EN LIQUIDATION)', StatutSociete::EnLiquidation->mentionActe());
        $this->assertNotSame(
            StatutSociete::EnLiquidation->mentionActe(),
            StatutSociete::LiquidationCloturee->mentionActe(),
        );
    }

    public function test_la_duree_de_larticle_5_est_calculee_et_non_saisie(): void
    {
        // Le PV réel affirme : constitution le 24/08/2021, durée « réduite à deux ans quatre
        // (04) mois » expirant « le 04 Décembre 2023 ». Or 24/08/2021 + 2 ans 4 mois = 24/12/2023,
        // et l'écart réel jusqu'au 04/12/2023 est de 2 ans 3 mois 10 jours. Vingt jours d'écart
        // dans un acte authentique — une valeur calculée depuis deux dates ne peut pas s'y
        // tromper.
        $service = app(ActesGeneratorService::class);

        $date = new \ReflectionMethod(ActesGeneratorService::class, 'dateFrancaise');
        $date->setAccessible(true);
        $duree = new \ReflectionMethod(ActesGeneratorService::class, 'dureeEnLettres');
        $duree->setAccessible(true);

        $ecart = $date->invoke($service, '24/08/2021')->diff($date->invoke($service, '04/12/2023'));

        $this->assertSame('deux ans, trois mois et dix jours', $duree->invoke($service, $ecart));
    }

    public function test_une_duree_sans_composante_nulle(): void
    {
        // « deux ans, zéro mois et zéro jour » dans un acte authentique se remarquerait.
        $service = app(ActesGeneratorService::class);
        $date = new \ReflectionMethod(ActesGeneratorService::class, 'dateFrancaise');
        $date->setAccessible(true);
        $duree = new \ReflectionMethod(ActesGeneratorService::class, 'dureeEnLettres');
        $duree->setAccessible(true);

        $ecart = $date->invoke($service, '24/08/2021')->diff($date->invoke($service, '24/08/2023'));

        $this->assertSame('deux ans', $duree->invoke($service, $ecart));
    }

    public function test_le_delai_de_mandat_vient_de_lacte_et_non_du_parametre(): void
    {
        // « nommé pour une durée de trois (03) mois à compter de la dissolution » : la durée est
        // décidée par l'assemblée, pas par un seuil d'étude. Le paramètre global de 36 mois que
        // portait la première version contredisait ce principe, pas seulement sa valeur.
        $societe = $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => '2026-01-15',
        ]);
        $dossier = $this->dossier($this->donneesDissolution([
            'liquidateur.duree_mandat_chiffres' => 3,
        ]), $societe);
        $dossier->update(['societe_id' => $societe->id]);

        $mandat = collect($societe->fresh()->echeancesLiquidation())
            ->firstWhere('jalon', JalonLiquidation::FinMandatLiquidateur->value);

        $this->assertSame('15/04/2026', $mandat['echeance']->format('d/m/Y'), 'Trois mois après la dissolution.');
        $this->assertFalse($mandat['aVerifier'], "Un délai lu dans l'acte n'est plus une hypothèse.");
    }

    public function test_sans_duree_dans_lacte_le_parametre_reprend_la_main(): void
    {
        // Une fiche entrée au registre à la main peut être en liquidation sans dossier porteur.
        $societe = $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => '2026-01-15',
        ]);

        $mandat = collect($societe->echeancesLiquidation())
            ->firstWhere('jalon', JalonLiquidation::FinMandatLiquidateur->value);

        $this->assertTrue($mandat['aVerifier'], 'Le repli paramétré reste une hypothèse.');
    }

    public function test_le_liquidateur_peut_etre_une_personne_morale(): void
    {
        // Le liquidateur réel est « le Cabinet TEDSOM SARLU, représenté par Monsieur … ». Le
        // questionnaire doit pouvoir le décrire, sinon l'acte n'est pas produisible.
        //
        // ⚠️ Vérifié sur la liste **générée** et non sur la source : ces champs naissent de
        // `personneBimodale()`, donc aucune chaîne « liquidateur.type_personne » n'existe dans
        // `questionnaires.js`. Seule l'évaluation du module les fait apparaître — c'est
        // exactement ce que fait `tools/generer-balises-resolvables.mjs`.
        $declarees = array_flip(array_filter(array_map(
            'trim',
            file(base_path('docs/kit-modeles/balises-resolvables.txt')),
        )));

        foreach (['liquidateur.type_personne', 'liquidateur.forme', 'liquidateur.rccm',
                  'liquidateur.representant_legal', 'liquidateur.duree_mandat_lettres',
                  'liquidateur.remuneration'] as $cle) {
            $this->assertArrayHasKey(
                $cle,
                $declarees,
                "Le bloc liquidateur doit pouvoir décrire une personne morale : « {$cle} » manque.",
            );
        }
    }

    public function test_les_deux_gabarits_de_phase_1_sont_balises_et_resolvables(): void
    {
        // Les deux modèles étaient des marque-places sans fichier : un dossier de dissolution ne
        // produisait aucun acte. Ce test échouera si l'un disparaît ou perd ses balises.
        $declarees = array_flip(array_filter(array_map(
            'trim',
            file(base_path('docs/kit-modeles/balises-resolvables.txt')),
        )));

        foreach (['dissolution.docx', 'insertion-dissolution.docx'] as $fichier) {
            $chemin = storage_path('app/private/modeles/societe/' . $fichier);
            $this->assertFileExists($chemin, "Gabarit de dissolution manquant : {$fichier}");

            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($chemin) === true, "{$fichier} n'est pas un .docx lisible.");
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            preg_match_all('/\$\{([^}]+)\}/', $xml, $trouvees);
            $this->assertNotEmpty($trouvees[1], "{$fichier} ne porte aucune balise.");

            foreach (array_unique($trouvees[1]) as $balise) {
                $this->assertArrayHasKey(
                    $balise,
                    $declarees,
                    "« {$balise} » de {$fichier} n'est pas résolvable : elle sortirait vide de l'acte.",
                );
            }
        }
    }

    // ═══ La doctrine des délais ══════════════════════════════════════════

    public function test_tous_les_jalons_sont_marques_a_verifier(): void
    {
        // ⚠️ Ce test échouera le jour où l'étude validera un délai — **c'est son rôle** : lever
        // le marqueur `aVerifier()` plutôt que l'oublier, et retirer la mention « délai à
        // vérifier » des alertes et de l'écran.
        foreach (JalonLiquidation::cases() as $jalon) {
            $this->assertTrue(
                $jalon->aVerifier(),
                "Si « {$jalon->label()} » est désormais sourcé, mettez à jour `aVerifier()` et `source()`.",
            );
            $this->assertNotEmpty($jalon->source(), 'Un délai non garanti doit dire d\'où vient son chiffre.');
        }
    }

    public function test_un_delai_se_corrige_par_un_parametre(): void
    {
        $jalon = JalonLiquidation::ClotureLiquidation;

        $this->assertSame($jalon->delaiDefautMois(), $jalon->delaiMois());

        \App\Models\Setting::set($jalon->cleParametre(), 24);

        $this->assertSame(24, $jalon->delaiMois());
    }

    public function test_une_echeance_depassee_est_signalee_mais_ne_bloque_rien(): void
    {
        $societe = $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => now()->subYears(5)->toDateString(),
        ]);

        $echeances = $societe->echeancesLiquidation();

        $this->assertNotEmpty($echeances);
        $this->assertTrue($echeances[0]['enRetard']);
        $this->assertTrue($echeances[0]['aVerifier']);

        // Et le dossier correspondant avance malgré tout : aucune règle ne lit les jalons.
        $dossier = $this->dossier($this->donneesDissolution(), $societe);
        $this->assertArrayNotHasKey('dissolution_echeance', app(\App\Services\ReglesSocieteService::class)->anomalies($dossier));
    }

    public function test_une_fiche_sans_date_de_depart_na_pas_decheance(): void
    {
        // Une société entrée au registre à la main peut être en liquidation sans date connue.
        // Prendre `created_at` comme point de départ produirait de fausses alertes.
        $societe = $this->societe(['statut' => StatutSociete::EnLiquidation]);

        $this->assertSame([], $societe->echeancesLiquidation());
        $this->assertNull($societe->prochaineEcheanceLiquidation());
    }

    // ═══ L'alerte ════════════════════════════════════════════════════════

    public function test_la_commande_alerte_sur_une_echeance_depassee(): void
    {
        Notification::fake();

        $this->utilisateur(RoleUtilisateur::Notaire);
        $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => now()->subYears(5)->toDateString(),
        ]);

        $this->artisan('ayelema:alerter-liquidations')->assertSuccessful();

        // **Deux** alertes, et c'est voulu : la fin de mandat et la clôture sont deux jalons
        // distincts, tous deux dépassés. Leurs délais par défaut coïncident aujourd'hui (36
        // mois, deux hypothèses de travail) mais divergeront dès que l'étude les validera.
        // C'est pourquoi la clé anti-doublon porte sur la société **et** le jalon : sur la
        // société seule, la première alerte masquerait la seconde.
        Notification::assertSentTimes(EcheanceLiquidationNotification::class, 2);
    }

    public function test_la_meme_echeance_nest_pas_renotifiee_le_lendemain(): void
    {
        // Fenêtre de 7 jours, là où les commandes de dossier utilisent 12 heures : sur un
        // délai de trois ans, renotifier chaque matin serait du harcèlement — et une alerte
        // qu'on apprend à ignorer ne sert plus à rien.
        $this->utilisateur(RoleUtilisateur::Notaire);
        $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => now()->subYears(5)->toDateString(),
        ]);

        $this->artisan('ayelema:alerter-liquidations')->assertSuccessful();
        $envoyees = \Illuminate\Support\Facades\DB::table('notifications')->count();

        $this->artisan('ayelema:alerter-liquidations')->assertSuccessful();

        $this->assertSame($envoyees, \Illuminate\Support\Facades\DB::table('notifications')->count());
    }

    public function test_une_societe_active_ne_declenche_aucune_alerte(): void
    {
        Notification::fake();

        $this->utilisateur(RoleUtilisateur::Notaire);
        $this->societe();

        $this->artisan('ayelema:alerter-liquidations')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_lalerte_dit_que_le_delai_nest_pas_garanti(): void
    {
        // Une alerte fondée sur un chiffre inventé qui ne le dirait pas serait pire que pas
        // d'alerte : elle ferait agir l'étude sur une contrainte qui n'existe peut-être pas.
        $societe = $this->societe([
            'statut'         => StatutSociete::EnLiquidation,
            'dissolution_at' => now()->subYears(5)->toDateString(),
        ]);

        $notification = new EcheanceLiquidationNotification($societe, $societe->echeancesLiquidation()[0]);
        $utilisateur  = $this->utilisateur(RoleUtilisateur::Notaire);

        $lignes = collect($notification->toMail($utilisateur)->introLines)->implode(' ');

        $this->assertStringContainsString('n\'est pas confirmé', $lignes);
        $this->assertTrue($notification->toArray($utilisateur)['aVerifier']);
    }

    // ═══ Le registre ═════════════════════════════════════════════════════

    private function utilisateur(RoleUtilisateur $role): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh();
    }

    public function test_le_registre_liste_les_societes_avec_leur_statut(): void
    {
        $this->societe(['statut' => StatutSociete::EnLiquidation, 'dissolution_at' => '2026-03-15']);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->get('/societes')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Societes/Index')
                ->where('societes.data.0.statut', 'en_liquidation')
                ->where('societes.data.0.statut_label', 'En liquidation')
                ->where('stats.enLiquidation', 1));
    }

    public function test_une_liquidation_clôturee_peut_etre_radiee(): void
    {
        $societe = $this->societe([
            'statut'                 => StatutSociete::LiquidationCloturee,
            'cloture_liquidation_at' => now()->subMonths(3)->toDateString(),
        ]);
        $hier = now()->subDay();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Notaire))
            ->patch("/societes/{$societe->id}/radiation", ['radiation_at' => $hier->toDateString()])
            ->assertSessionHasNoErrors();

        $societe->refresh();

        $this->assertSame(StatutSociete::Radiee, $societe->statut);
        $this->assertSame($hier->format('d/m/Y'), $societe->radiation_at->format('d/m/Y'));
    }

    public function test_une_societe_active_ne_peut_pas_etre_radiee(): void
    {
        $societe = $this->societe();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Notaire))
            ->patch("/societes/{$societe->id}/radiation", ['radiation_at' => now()->subDay()->toDateString()])
            ->assertSessionHas('error');

        $this->assertSame(StatutSociete::Active, $societe->refresh()->statut);
    }

    public function test_la_radiation_refuse_une_date_future(): void
    {
        $societe = $this->societe(['statut' => StatutSociete::LiquidationCloturee]);

        $this->actingAs($this->utilisateur(RoleUtilisateur::Notaire))
            ->patch("/societes/{$societe->id}/radiation", ['radiation_at' => now()->addYear()->toDateString()])
            ->assertSessionHasErrors('radiation_at');
    }
    // ═══ Un blocage doit nommer sa sortie ════════════════════════════════

    /**
     * Le blocage de clôture nomme le dossier de dissolution en cours, au lieu d'en réclamer un.
     *
     * Constaté le 2026-09-30 sur AFG SARLU : la clôture était bloquée par « ouvrez d'abord un
     * dossier en phase Dissolution anticipée » alors que SOC-2026-0019 le portait déjà, à
     * l'étape Formalités. Le conseil envoyait créer un doublon — et sa seconde moitié,
     * « corrigez le statut de la fiche s'il est erroné », invitait à écrire au registre un état
     * que l'acte n'avait pas encore produit.
     *
     * Le blocage lui-même est juste et reste : on ne clôture pas une liquidation qui n'est pas
     * ouverte. C'est sa **sortie** qui était fausse.
     */
    public function test_le_blocage_de_cloture_nomme_le_dossier_de_dissolution_en_cours(): void
    {
        $societe = $this->societe();

        $dissolution = $this->dossier($this->donneesDissolution(), $societe);
        $dissolution->update(['etape' => EtapeDossier::Formalites]);

        $cloture = $this->dossier(['dissolution.phase' => 'Clôture de la liquidation', 'dissolution.date_cloture' => '15/09/2026'], $societe);

        $anomalies = app(DossierStepService::class)->anomaliesMetier($cloture);
        $blocage = collect($anomalies)->firstWhere('cle', 'dissolution_cycle_vie');

        $this->assertNotNull($blocage, 'La clôture doit rester bloquée : la société est encore active.');
        $this->assertStringContainsString(
            $dissolution->reference,
            $blocage['texte'],
            'Le message doit nommer le dossier qui porte déjà la dissolution.',
        );
        $this->assertStringContainsString('Formalités', $blocage['texte'], "Et dire où il en est.");
        $this->assertSame('/dossiers/' . $dissolution->reference, $blocage['lien'] ?? null,
            'Le blocage doit mener au dossier à faire avancer, pas au registre.');
    }

    /** Sans dossier de dissolution, l'ancien conseil reste le bon. */
    public function test_sans_dossier_de_dissolution_le_blocage_en_reclame_un(): void
    {
        $cloture = $this->dossier(['dissolution.phase' => 'Clôture de la liquidation', 'dissolution.date_cloture' => '15/09/2026'], $this->societe());

        $blocage = collect(app(DossierStepService::class)->anomaliesMetier($cloture))
            ->firstWhere('cle', 'dissolution_cycle_vie');

        $this->assertNotNull($blocage);
        $this->assertStringContainsString('Ouvrez d\'abord un dossier', $blocage['texte']);
        $this->assertArrayNotHasKey('lien', $blocage, "Il n'y a nulle part où mener.");
    }

    /**
     * Un message enrichi n'empêche pas les autres de rester de simples chaînes.
     *
     * L'extension du contrat de `anomaliesMetier()` est additive : c'est ce qui permet de ne
     * pas toucher les quarante autres règles.
     */
    public function test_les_autres_blocages_restent_de_simples_messages(): void
    {
        $sansPhase = $this->dossier(['soc.denomination' => 'Société sans phase'], $this->societe());

        foreach (app(DossierStepService::class)->anomaliesMetier($sansPhase) as $anomalie) {
            $this->assertArrayHasKey('cle', $anomalie);
            $this->assertIsString($anomalie['texte']);
        }
    }
}
