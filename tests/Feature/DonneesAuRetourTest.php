<?php

namespace Tests\Feature;

use App\Enums\DonneeAuRetour;
use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFormalite;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\EnregistrementRetourFormalite;
use App\Services\FormaliteGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Capture des données délivrées au retour d'une formalité — 2026-09-29.
 *
 * Le versant fichier fonctionnait (27 formalités sur 29 portaient leurs pièces) ; la **donnée**
 * se perdait. Mesuré avant la passe : 12 numéros RCCM ou NIF sur 14 n'atteignaient jamais le
 * registre, et 5 des 6 sociétés dont le dossier avait dépassé les formalités n'avaient ni l'un
 * ni l'autre.
 *
 * Le piège était structurel : les 7 questionnaires de constitution ne demandent ni `soc.rccm`
 * ni `soc.nif` — le numéro n'existe pas encore — mais `modification` et `dissolution` les
 * exigent. Le seul moment où l'application pouvait l'apprendre n'était pas capté.
 */
class DonneesAuRetourTest extends TestCase
{
    use RefreshDatabase;

    private function formaliste(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

        return $user->fresh();
    }

    private function societe(array $attributs = []): Societe
    {
        return Societe::create(array_merge(['denomination' => 'Faya Distribution SARLU', 'forme' => 'SARLU'], $attributs));
    }

    /** Dossier en Formalités, avec une formalité déclarant les données passées. */
    private function formalite(array $donnees, ?Societe $societe = null, array $attributs = []): Formalite
    {
        $typeActe = TypeActe::firstOrCreate(
            ['code' => 'SOC-TST'],
            ['label' => 'Constitution de test', 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );

        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'societe_id'   => $societe?->id,
            'etape'        => EtapeDossier::Formalites,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test de capture au retour de formalité',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => []]);

        // ⚠️ Le lien **dans les deux sens**, comme en production. `dossiers.societe_id` dit
        // « ce dossier porte sur cette société » ; `societes.dossier_id` dit « cette fiche est
        // née de ce dossier », et c'est lui que le garde-fou du RCCM interroge : un numéro
        // d'immatriculation ne peut venir que du dossier qui a immatriculé. Le fixture ne
        // posait que le premier, ce qui décrivait une société tombée du ciel.
        $societe?->update(['dossier_id' => $dossier->id]);

        return Formalite::create(array_merge([
            'dossier_id'        => $dossier->id,
            'organisme'         => 'apip',
            'libelle'           => 'Frais APIP et RCCM',
            'statut'            => StatutFormalite::Depose,
            'retour_attendu'    => 'Extrait RCCM définitif et attestation NIF',
            'donnees_au_retour' => $donnees,
        ], $attributs));
    }

    // ═══ Le test anti-mort ═══════════════════════════════════════════════

    /**
     * Un écrivain, un lecteur, un consommateur, un effet — les quatre en une passe.
     *
     * ⚠️ **C'est le test qui empêche `donnees_au_retour` de rejoindre les cinq colonnes mortes
     * du dépôt** : `formalites.reference_document_recu` (jamais écrite), `retour_attendu`
     * (jamais lue), `societes.jal_journal`, `types_actes.fiche_modification_obligatoire`, et
     * `types_actes.actes_requis` dont la colonne n'existe même pas. Une colonne sans lecteur,
     * sans écran et sans test meurt dans ce dépôt — celle-ci a les trois.
     */
    public function test_une_donnee_declaree_est_captee_exposee_et_portee_a_la_fiche(): void
    {
        $societe = $this->societe();
        $formalite = $this->formalite(['rccm_numero', 'rccm_date', 'nif'], $societe);

        // 1. Le payload l'expose — sinon la modale ne saurait quoi demander.
        $payload = $formalite->versArray();
        $this->assertSame('Extrait RCCM définitif et attestation NIF', $payload['retour_attendu']);
        $this->assertSame(
            ['rccm_numero', 'rccm_date', 'nif'],
            array_column($payload['donnees_au_retour'], 'valeur'),
        );

        // 2. La route la capte.
        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'       => 'recu',
                'date_retour'    => '2026-09-20',
                'donnees_recues' => [
                    'rccm_numero' => 'GN.TCC.2026.B01234',
                    'rccm_date'   => '2026-09-15',
                    'nif'         => '000987654',
                ],
            ])
            ->assertSessionHasNoErrors();

        // 3. La formalité en garde la preuve.
        $formalite->refresh();
        $this->assertSame(StatutFormalite::RetourRecu, $formalite->statut);
        $this->assertSame('GN.TCC.2026.B01234', $formalite->donnees_recues['rccm_numero']);

        // 4. La fiche société la porte — c'est le but de toute la passe.
        $societe->refresh();
        $this->assertSame('GN.TCC.2026.B01234', $societe->rccm_numero);
        $this->assertSame('000987654', $societe->nif);
        $this->assertSame('15/09/2026', $societe->date_constitution->format('d/m/Y'));
    }

    // ═══ Le catalogue tient ses promesses ════════════════════════════════

    public function test_toute_destination_est_une_colonne_reelle_et_assignable(): void
    {
        // Le défaut exact de `types_actes.actes_requis` : déclarée dans le modèle, absente de
        // la table. Un `create()` avec cette clé lèverait une erreur SQL.
        $colonnes = Schema::getColumnListing('societes');
        $fillable = (new Societe())->getFillable();

        foreach (DonneeAuRetour::cases() as $donnee) {
            $colonne = $donnee->colonne();

            if ($colonne === null) {
                continue;
            }

            $this->assertContains($colonne, $colonnes, "« {$donnee->label()} » vise une colonne inexistante.");
            $this->assertContains($colonne, $fillable, "« {$colonne} » n'est pas assignable : l'écriture serait ignorée.");
        }
    }

    public function test_aucune_donnee_nimpose_de_format_invente(): void
    {
        // Trois formes de RCCM coexistent dans le dépôt : `GN.TCC.2021.B09995` (le PV réel de
        // l'étude), `GN-CON-2020-B-XXXX` (le placeholder du questionnaire) et `TR45645` (en
        // base). Une expression régulière refuserait des numéros authentiques.
        foreach (DonneeAuRetour::cases() as $donnee) {
            foreach ($donnee->regles() as $regle) {
                $this->assertStringNotContainsString('regex', (string) $regle, "« {$donnee->label()} » impose un format.");
            }
        }
    }

    public function test_une_donnee_retiree_du_catalogue_est_ignoree_et_non_fatale(): void
    {
        // Une formalité générée hier peut porter une valeur retirée de l'enum depuis : la
        // refuser ferait échouer l'enregistrement d'un retour sur un dossier ancien.
        $this->assertSame([], DonneeAuRetour::depuis(['donnee_inexistante']));
        $this->assertSame(
            [DonneeAuRetour::RccmNumero],
            DonneeAuRetour::depuis(['donnee_inexistante', 'rccm_numero']),
        );
    }

    public function test_chaque_donnee_est_declaree_resolvable_comme_balise(): void
    {
        // Duplication PHP/JS inévitable — `generer-balises-resolvables.mjs` évalue du
        // JavaScript et ne peut pas lire un enum PHP — donc **gardée**. Sans ce test, une
        // donnée ajoutée au catalogue produirait une balise que `verifier-balises.php`
        // déclarerait non résolvable : l'étude en conclurait qu'elle n'existe pas, alors que
        // le moteur la remplit.
        $declarees = array_flip(array_filter(array_map(
            'trim',
            file(base_path('docs/kit-modeles/balises-resolvables.txt')),
        )));

        foreach (DonneeAuRetour::cases() as $donnee) {
            $this->assertArrayHasKey(
                $donnee->baliseCle(),
                $declarees,
                "« {$donnee->baliseCle()} » n'est pas résolvable. Ajoutez-la à "
                . 'tools/generer-balises-resolvables.mjs puis régénérez la liste.',
            );

            // Le moteur dérive `_jma` et `_lettres` d'une date : les deux doivent suivre.
            if ($donnee->type() === 'date') {
                foreach (['_jma', '_lettres'] as $variante) {
                    $this->assertArrayHasKey($donnee->baliseCle() . $variante, $declarees);
                }
            }
        }
    }

    public function test_la_liste_de_balises_nest_pas_plus_vieille_que_le_catalogue(): void
    {
        // Même garde que pour `questionnaires.js` : un catalogue enrichi sans régénération
        // laisserait le garde-fou vert sur une liste périmée.
        $this->assertGreaterThanOrEqual(
            filemtime(app_path('Enums/DonneeAuRetour.php')),
            filemtime(base_path('docs/kit-modeles/balises-resolvables.txt')),
            'balises-resolvables.txt est plus ancien que DonneeAuRetour.php — régénérez-le.',
        );
    }

    // ═══ L'application à la fiche : trois branches ═══════════════════════

    public function test_deux_enregistrements_identiques_ne_doublent_rien(): void
    {
        $societe   = $this->societe();
        $formalite = $this->formalite(['rccm_numero'], $societe);
        $service   = app(EnregistrementRetourFormalite::class);

        $this->assertNotEmpty($service->appliquer($formalite, ['rccm_numero' => 'GN.TCC.2026.B01234'])['appliquees']);

        $second = $service->appliquer($formalite->fresh(), ['rccm_numero' => 'GN.TCC.2026.B01234']);

        $this->assertSame([], $second['appliquees']);
        $this->assertSame([], $second['divergentes']);
    }

    public function test_une_valeur_divergente_nest_pas_ecrasee_mais_signalee(): void
    {
        // « Ne jamais écraser » suffit pour remplir une fiche vide, mais rendrait une faute de
        // frappe **définitive**. La troisième branche est celle qui permet la correction.
        $societe   = $this->societe(['rccm_numero' => 'GN.TCC.2026.B01234']);
        $formalite = $this->formalite(['rccm_numero'], $societe);

        $bilan = app(EnregistrementRetourFormalite::class)
            ->appliquer($formalite, ['rccm_numero' => 'GN.TCC.2026.B09999']);

        $this->assertSame([], $bilan['appliquees']);
        $this->assertArrayHasKey('rccm_numero', $bilan['divergentes']);
        $this->assertSame('GN.TCC.2026.B01234', $societe->fresh()->rccm_numero, "La fiche ne doit pas être écrasée.");

        $this->assertDatabaseHas('journal_activites', ['type' => 'societe']);
    }

    public function test_une_formalite_sans_societe_ne_leve_rien(): void
    {
        // Une vente ou une hypothèque n'a pas de fiche : 4 formalités sur 53 mesurées.
        $formalite = $this->formalite(['rccm_numero'], null);

        $bilan = app(EnregistrementRetourFormalite::class)
            ->appliquer($formalite, ['rccm_numero' => 'GN.TCC.2026.B01234']);

        $this->assertSame([], $bilan['appliquees']);
        $this->assertContains('rccm_numero', $bilan['sansDestination']);
    }

    public function test_une_donnee_sans_destination_reste_sur_la_formalite(): void
    {
        $societe   = $this->societe();
        $formalite = $this->formalite(['quittance_numero'], $societe);

        $bilan = app(EnregistrementRetourFormalite::class)
            ->appliquer($formalite, ['quittance_numero' => 'QT-2026-4567']);

        $this->assertSame([], $bilan['appliquees']);
        $this->assertContains('quittance_numero', $bilan['sansDestination']);
    }

    // ═══ Le formulaire ═══════════════════════════════════════════════════

    public function test_aucune_donnee_nest_obligatoire(): void
    {
        // L'APIP peut rendre un extrait sans le NIF. L'exiger reproduirait le piège de
        // `modification.soc.rccm required:true` : une étape bloquée par une donnée que
        // l'organisme n'a pas fournie.
        $societe   = $this->societe();
        $formalite = $this->formalite(['rccm_numero', 'nif'], $societe);

        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'    => 'recu',
                'date_retour' => '2026-09-20',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatutFormalite::RetourRecu, $formalite->fresh()->statut);
    }

    public function test_une_date_posterieure_au_retour_est_refusee(): void
    {
        $formalite = $this->formalite(['rccm_date'], $this->societe());

        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'       => 'recu',
                'date_retour'    => '2026-09-20',
                'donnees_recues' => ['rccm_date' => '2026-12-31'],
            ])
            ->assertSessionHasErrors('donnees_recues.rccm_date');
    }

    public function test_une_seconde_soumission_nefface_pas_une_donnee_deja_captee(): void
    {
        // Le cas banal : l'administrateur retire une donnée du barème, le formaliste rouvre le
        // retour. Sans la fusion, la soumission effacerait ce qui avait été saisi.
        $societe   = $this->societe();
        $formalite = $this->formalite(['rccm_numero', 'nif'], $societe);
        $user      = $this->formaliste();

        $this->actingAs($user)->post("/formalites/{$formalite->id}/retour", [
            'resultat'       => 'recu',
            'date_retour'    => '2026-09-20',
            'donnees_recues' => ['rccm_numero' => 'GN.TCC.2026.B01234', 'nif' => '000987654'],
        ]);

        // La déclaration change : le NIF n'est plus attendu.
        $formalite->update(['donnees_au_retour' => ['rccm_numero']]);

        $this->actingAs($user)->post("/formalites/{$formalite->id}/retour", [
            'resultat'       => 'recu',
            'date_retour'    => '2026-09-20',
            'donnees_recues' => ['rccm_numero' => 'GN.TCC.2026.B01234'],
        ]);

        $this->assertSame('000987654', $formalite->fresh()->donnees_recues['nif']);
    }

    // ═══ Le rejet, rétabli ═══════════════════════════════════════════════

    public function test_un_rejet_est_enregistrable_et_atteint_le_statut_rejete(): void
    {
        // `StatutFormalite::Rejete` était **inatteignable** depuis le 2026-08-14, alors que
        // `DossierStepService` gardait son message « À corriger et redéposer ».
        $formalite = $this->formalite(['rccm_numero'], $this->societe());

        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'    => 'rejete',
                'date_retour' => '2026-09-20',
                'motif_rejet' => 'Dénomination non conforme aux statuts déposés.',
            ])
            ->assertSessionHasNoErrors();

        $formalite->refresh();
        $this->assertSame(StatutFormalite::Rejete, $formalite->statut);
        $this->assertStringContainsString('Dénomination non conforme', $formalite->motif_rejet);
    }

    public function test_un_rejet_nexige_pas_les_pieces(): void
    {
        // Un rejet n'apporte pas les pièces attendues — c'est sa définition. Le contrôle
        // inconditionnel de la version précédente l'aurait rendu inenregistrable.
        $formalite = $this->formalite(['rccm_numero'], $this->societe());
        $formalite->pieces()->create([
            'nom' => 'Extrait RCCM définitif', 'categorie' => 'piece_justificative',
            'est_requis' => true, 'est_fourni' => false,
        ]);

        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'    => 'rejete',
                'date_retour' => '2026-09-20',
                'motif_rejet' => 'Dossier incomplet.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(StatutFormalite::Rejete, $formalite->fresh()->statut);
    }

    public function test_un_rejet_sans_motif_est_refuse(): void
    {
        $formalite = $this->formalite(['rccm_numero'], $this->societe());

        $this->actingAs($this->formaliste())
            ->post("/formalites/{$formalite->id}/retour", [
                'resultat'    => 'rejete',
                'date_retour' => '2026-09-20',
            ])
            ->assertSessionHasErrors('motif_rejet');
    }

    // ═══ La déclaration circule du barème à la formalité ═════════════════

    public function test_la_generation_recopie_la_declaration_une_seule_fois(): void
    {
        // Copie figée : un barème modifié plus tard ne doit pas changer ce qu'un dossier déjà
        // ouvert réclame au retour — même parti que `retour_attendu` et `pieces_requises`.
        $typeActe = TypeActe::firstOrCreate(
            ['code' => 'SOC-TST2'],
            ['label' => 'Constitution de test 2', 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );

        $bareme = Bareme::create([
            'type_acte_id'      => $typeActe->id,
            'organisme'         => 'APIP',
            'libelle'           => 'Frais APIP et RCCM',
            'montant_fixe'      => 490000,
            'base_calcul'       => 'montant_fixe',
            'genere_formalite'  => true,
            'retour_attendu'    => 'Extrait RCCM définitif',
            'donnees_au_retour' => ['rccm_numero'],
            'actif'             => true,
        ]);

        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test de génération de formalités',
        ]);
        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => []]);

        app(FormaliteGenerationService::class)->genererFormalites($dossier);

        $formalite = Formalite::where('dossier_id', $dossier->id)->first();
        $this->assertSame(['rccm_numero'], $formalite->donnees_au_retour);

        // Le barème change, le dossier déjà ouvert ne bouge pas.
        $bareme->update(['donnees_au_retour' => ['rccm_numero', 'nif']]);
        app(FormaliteGenerationService::class)->genererFormalites($dossier->fresh());

        $this->assertSame(
            ['rccm_numero'],
            $formalite->fresh()->donnees_au_retour,
            "Une régénération ne doit pas réécrire l'instantané de la formalité.",
        );
    }

    // ═══ Le RCCM ne vient que de l'immatriculation ═══════════════════════

    /**
     * Une formalité qui n'est pas celle du dossier constitutif ne délivre pas d'identité.
     *
     * Mesuré le 2026-09-30 : les barèmes « Immatriculation RCCM » et « Obtention NIF »
     * existaient aussi sur `SOC-MOD` et `SOC-DIS`, semés par libellé, et y déclaraient
     * `rccm_numero` / `rccm_date`. Tous inactifs, donc sans dégât — mais le jour où l'étude en
     * activait un, le numéro rendu par le greffe pour une **modification** entrait dans
     * `societes.rccm_numero` d'une fiche encore vide. C'est exactement l'écrasement d'identité
     * que `DonneeAuRetour::DeclarationModificativeNumero` existe pour éviter en n'ayant, elle,
     * aucune destination.
     *
     * La migration a nettoyé la donnée ; ce test empêche la règle de se reperdre.
     */
    public function test_un_retour_hors_dossier_constitutif_nentre_pas_didentite_au_registre(): void
    {
        // La société existe déjà, née d'un autre dossier : c'est le cas d'une modification.
        $societe = $this->societe(['dossier_id' => null]);
        $formalite = $this->formalite(['rccm_numero', 'rccm_date', 'nif'], $societe);

        // On défait le lien que le fixture pose : ce dossier n'est pas le dossier constitutif.
        $societe->update(['dossier_id' => null]);

        $resultat = app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00001',
            'rccm_date'   => '2026-09-30',
            'nif'         => '000987654',
        ]);

        $societe->refresh();

        $this->assertNull($societe->rccm_numero, "Le RCCM ne doit pas venir d'une formalité qui n'a pas immatriculé.");
        $this->assertNull($societe->date_constitution, "La date d'immatriculation non plus.");
        $this->assertContains('rccm_numero', $resultat['sansDestination'], 'Le refus doit être visible, pas silencieux.');
        $this->assertContains('rccm_date', $resultat['sansDestination']);

        // Le NIF, lui, passe : l'administration fiscale peut en attribuer un après coup, et
        // rien ne permet d'affirmer le contraire.
        $this->assertSame('000987654', $societe->nif);
    }

    public function test_le_dossier_constitutif_porte_bien_le_rccm(): void
    {
        $societe = $this->societe();
        $formalite = $this->formalite(['rccm_numero'], $societe);

        app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00002',
        ]);

        $this->assertSame('GN.TCC.2026.B00002', $societe->refresh()->rccm_numero);
    }

    // ═══ Combler les questionnaires déjà ouverts ═════════════════════════

    /**
     * Le numéro arrivé au registre rejoint les dossiers ouverts — sans rien corriger.
     *
     * Sans cela la boucle restait ouverte d'un cran : la fiche apprenait le RCCM, mais un
     * dossier de modification ouvert la veille continuait de le réclamer, parce que sa
     * projection est figée au rattachement et que rien ne la rejoue.
     */
    public function test_le_questionnaire_dun_dossier_ouvert_est_comble(): void
    {
        $societe = $this->societe();
        $formalite = $this->formalite(['rccm_numero'], $societe);

        // Un second dossier, ouvert, porte sur la même société et ne connaît pas le numéro.
        $autre = Dossier::create([
            'reference'    => 'SOC-2026-9001',
            'type_acte_id' => $formalite->dossier->type_acte_id,
            'societe_id'   => $societe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Modification ouverte avant le retour',
        ]);
        $qAutre = Questionnaire::create([
            'dossier_id' => $autre->id,
            'donnees'    => ['soc.denomination' => 'Faya Distribution SARLU', 'soc.rccm' => ''],
        ]);

        app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00003',
        ]);

        $this->assertSame(
            'GN.TCC.2026.B00003',
            $qAutre->fresh()->donnees['soc.rccm'] ?? null,
            'Une clé vide devait être comblée depuis le registre.',
        );
    }

    /** Une valeur déjà saisie n'est jamais réalignée : combler n'est pas corriger. */
    public function test_une_valeur_deja_saisie_dans_un_dossier_ouvert_nest_pas_ecrasee(): void
    {
        $societe = $this->societe();
        $formalite = $this->formalite(['rccm_numero'], $societe);

        $autre = Dossier::create([
            'reference'    => 'SOC-2026-9002',
            'type_acte_id' => $formalite->dossier->type_acte_id,
            'societe_id'   => $societe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier portant déjà un numéro',
        ]);
        $qAutre = Questionnaire::create([
            'dossier_id' => $autre->id,
            'donnees'    => ['soc.rccm' => 'SAISI-A-LA-MAIN'],
        ]);

        app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00004',
        ]);

        $this->assertSame(
            'SAISI-A-LA-MAIN',
            $qAutre->fresh()->donnees['soc.rccm'],
            "La décision du devbook tient : on ne réaligne pas en silence une valeur portée.",
        );
    }

    /** Un dossier clôturé est un instantané : il a produit des actes archivés. */
    public function test_un_dossier_cloture_nest_jamais_comble(): void
    {
        $societe = $this->societe();
        $formalite = $this->formalite(['rccm_numero'], $societe);

        $clos = Dossier::create([
            'reference'    => 'SOC-2026-9003',
            'type_acte_id' => $formalite->dossier->type_acte_id,
            'societe_id'   => $societe->id,
            'etape'        => EtapeDossier::Cloture,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier archivé',
        ]);
        $qClos = Questionnaire::create(['dossier_id' => $clos->id, 'donnees' => ['soc.rccm' => '']]);

        app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00005',
        ]);

        $this->assertSame('', $qClos->fresh()->donnees['soc.rccm'], "Un instantané archivé ne se réécrit pas.");
    }

    /**
     * Le comblement porte sur ce qui vient d'arriver, pas sur toute la fiche.
     *
     * Mesuré sur la base réelle avant de resserrer : projeter toute la fiche à l'occasion d'un
     * retour aurait comblé `soc.objet_social`, `soc.duree` et neuf autres clés — correct sur le
     * fond, mais un effet qu'on ne peut pas relier à sa cause n'est pas lisible dans un journal.
     */
    public function test_le_comblement_ne_deborde_pas_sur_les_autres_champs(): void
    {
        $societe = $this->societe([
            'objet_social' => 'Commerce général',
            'duree'        => 99,
        ]);
        $formalite = $this->formalite(['rccm_numero'], $societe);

        $autre = Dossier::create([
            'reference'    => 'SOC-2026-9004',
            'type_acte_id' => $formalite->dossier->type_acte_id,
            'societe_id'   => $societe->id,
            'etape'        => EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier ouvert aux champs vides',
        ]);
        $qAutre = Questionnaire::create(['dossier_id' => $autre->id, 'donnees' => []]);

        app(EnregistrementRetourFormalite::class)->appliquer($formalite->fresh(), [
            'rccm_numero' => 'GN.TCC.2026.B00006',
        ]);

        $donnees = $qAutre->fresh()->donnees;

        $this->assertSame('GN.TCC.2026.B00006', $donnees['soc.rccm'] ?? null, 'Le RCCM devait arriver.');
        $this->assertSame(
            ['soc.rccm'],
            array_keys($donnees),
            "Seule la donnée reçue voyage : un retour de RCCM n'a pas à remplir l'objet social.",
        );
    }
}
