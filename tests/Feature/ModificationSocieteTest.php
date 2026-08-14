<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\TypeModificationStatutaire;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use App\Services\FacturationService;
use App\Services\FormaliteGenerationService;
use App\Services\ReglesSocieteService;
use App\Services\SocieteMutationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modification de statuts (`SOC-MOD`) — refonte du 2026-08-11.
 *
 * Un test par règle, nommé d'après elle, comme `ReglesSocieteTest` : quand une règle
 * changera, on saura lequel toucher.
 *
 * Les cas décisifs sont ceux d'**asymétrie** — ce qu'une modification produit et ce qu'une
 * autre ne produit pas. C'est là que le comportement d'origine était faux : il produisait
 * tout pour tout le monde.
 */
class ModificationSocieteTest extends TestCase
{
    use RefreshDatabase;

    private function typeModification(): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => 'SOC-MOD'],
            ['label' => 'Modification de statuts', 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    private function dossier(array $donnees = [], ?Societe $societe = null, string $code = 'SOC-MOD'): Dossier
    {
        $typeActe = $code === 'SOC-MOD'
            ? $this->typeModification()
            : TypeActe::firstOrCreate(
                ['code' => $code],
                ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
            );

        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'societe_id'   => $societe?->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test de modification statutaire',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    /** Jeu de données minimal valide, à compléter par type de modification. */
    private function donneesBase(array $types): array
    {
        return [
            'modif.types'          => $types,
            'soc.denomination'     => 'Faya Distribution SARL',
            'soc.forme'            => 'SARL',
            'soc.capital_chiffres' => 50_000_000,
            'soc.nombre_parts'     => 100,
            'ag.date'              => '01/08/2026',
        ];
    }

    private function societe(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination'     => 'Faya Distribution SARL',
            'forme'            => 'SARL',
            'rccm_numero'      => 'GN-CON-2020-B-0001',
            'capital_chiffres' => 50_000_000,
            'nombre_parts'     => 100,
            'siege_quartier'   => 'Almamya',
            'siege_commune'    => 'Kaloum',
            'siege_ville'      => 'Conakry',
            'objet_social'     => 'Commerce général',
        ], $attributs));
    }

    // ── Règles 8 et 9 : impact et documents, les sept types ───────────────────

    public function test_les_sept_types_de_modification_existent(): void
    {
        // La diminution de capital manquait : les notes de l'étude la traitent explicitement,
        // et son asymétrie avec l'augmentation (pas de DNSV) est le motif de son ajout.
        $this->assertCount(7, TypeModificationStatutaire::cases());
        $this->assertInstanceOf(
            TypeModificationStatutaire::class,
            TypeModificationStatutaire::from('capital_diminution'),
        );
    }

    public function test_regle8_toutes_les_modifications_impactent_le_rccm(): void
    {
        foreach (TypeModificationStatutaire::cases() as $type) {
            $this->assertTrue($type->impacteRccm(), "{$type->value} doit impacter le RCCM.");
            $this->assertArrayHasKey(
                'declaration_rccm',
                $type->documentsRequis(),
                "{$type->value} doit produire une déclaration de modification RCCM.",
            );
        }
    }

    public function test_une_diminution_de_capital_ne_produit_aucune_dnsv(): void
    {
        // « Diminution de capital : aucune DNSV n'est requise dans ce cas. »
        $type = TypeModificationStatutaire::CapitalDiminution;

        $this->assertFalse($type->exigeDnsv());
        $this->assertArrayNotHasKey('dnsv', $type->documentsRequis());
        // Mais elle modifie bien les statuts et passe au RCCM.
        $this->assertArrayHasKey('statuts_maj', $type->documentsRequis());
        $this->assertArrayHasKey('declaration_rccm', $type->documentsRequis());
    }

    public function test_une_augmentation_de_capital_produit_une_dnsv(): void
    {
        $type = TypeModificationStatutaire::CapitalAugmentation;

        $this->assertTrue($type->exigeDnsv());
        $this->assertArrayHasKey('dnsv', $type->documentsRequis());
    }

    // ── Incompatibilités entre modifications ─────────────────────────────────

    public function test_les_incompatibilites_sont_symetriques(): void
    {
        // Sans cet invariant, une déclaration unilatérale rendrait l'exclusion dépendante de
        // l'ordre des clics : cocher A puis B passerait, B puis A serait refusé.
        foreach (TypeModificationStatutaire::cases() as $type) {
            foreach ($type->incompatiblesAvec() as $autre) {
                $this->assertContains(
                    $type,
                    $autre->incompatiblesAvec(),
                    "{$autre->value} doit exclure {$type->value} en retour.",
                );
                $this->assertNotNull(
                    $type->motifIncompatibilite($autre),
                    "L'incompatibilité {$type->value} / {$autre->value} doit porter un motif affichable.",
                );
            }
        }
    }

    public function test_augmentation_et_diminution_de_capital_sont_incompatibles(): void
    {
        // Le défaut d'origine : les deux écrivent `capital_chiffres` au registre, et
        // SocieteMutationService retenait la dernière fusionnée — l'ordre de déclaration de l'enum
        // décidait donc du capital de la société, en silence.
        $this->assertSame(
            [TypeModificationStatutaire::CapitalDiminution],
            TypeModificationStatutaire::CapitalAugmentation->incompatiblesAvec(),
        );
    }

    public function test_les_deux_changements_de_gerant_sont_incompatibles(): void
    {
        // Un gérant est nommé aux statuts ou il ne l'est pas : cocher les deux faisait produire
        // des statuts mis à jour (impacteStatuts() étant une union) que le cas non statutaire ne
        // justifie pas.
        $this->assertSame(
            [TypeModificationStatutaire::GerantNonStatutaire],
            TypeModificationStatutaire::GerantStatutaire->incompatiblesAvec(),
        );
    }

    public function test_les_cinq_autres_types_nexcluent_rien(): void
    {
        foreach ([
            TypeModificationStatutaire::SiegeSocial,
            TypeModificationStatutaire::CapitalCession,
            TypeModificationStatutaire::ObjetSocial,
        ] as $type) {
            $this->assertSame([], $type->incompatiblesAvec(), "{$type->value} ne doit rien exclure.");
        }
    }

    public function test_les_conflits_sont_dedupliques(): void
    {
        // Une paire, pas deux fois la même dans les deux sens.
        $conflits = TypeModificationStatutaire::conflits([
            TypeModificationStatutaire::CapitalAugmentation,
            TypeModificationStatutaire::CapitalDiminution,
            TypeModificationStatutaire::GerantStatutaire,
            TypeModificationStatutaire::GerantNonStatutaire,
        ]);

        $this->assertCount(2, $conflits);
        foreach ($conflits as $paire) {
            $this->assertNotEmpty($paire['motif']);
        }
    }

    public function test_une_selection_saine_ne_produit_aucun_conflit(): void
    {
        $this->assertSame([], TypeModificationStatutaire::conflits([
            TypeModificationStatutaire::CapitalCession,
            TypeModificationStatutaire::SiegeSocial,
            TypeModificationStatutaire::GerantStatutaire,
        ]));
    }

    public function test_une_combinaison_incompatible_est_bloquante(): void
    {
        $dossier = $this->dossier($this->donneesBase([
            'Augmentation de capital',
            'Diminution de capital',
        ]));

        $anomalies = $this->anomalies($dossier);

        $this->assertArrayHasKey('modif_incompatibles', $anomalies);
        $this->assertStringContainsString('Augmentation de capital', $anomalies['modif_incompatibles'][0]);
        $this->assertStringContainsString('Diminution de capital', $anomalies['modif_incompatibles'][0]);
    }

    public function test_le_conflit_masque_les_controles_de_detail(): void
    {
        // Tant que la sélection se contredit, les contrôles de détail porteraient sur des blocs qui
        // n'ont pas à coexister et noieraient le vrai problème.
        $donnees = $this->donneesBase(['Changement de gérant statutaire', 'Changement de gérant non statutaire']);
        unset($donnees['ag.date']);

        $anomalies = $this->anomalies($this->dossier($donnees));

        $this->assertSame(['modif_incompatibles'], array_keys($anomalies));
    }

    public function test_non_regression_trois_modifications_compatibles_restent_conformes(): void
    {
        // On ne durcit que les deux paires contradictoires.
        $dossier = $this->dossier([
            ...$this->donneesBase([
                'Cession de parts sociales',
                'Transfert du siège social',
                'Changement de gérant statutaire',
            ]),
            'modif.valeur_parts_cedees'    => 25_000_000,
            'modif.cedants'                => [['nom' => 'Ibrahima DIALLO', 'parts_cedees' => 40]],
            'modif.cessionnaires'          => [['nom' => 'Mariama SOW', 'parts_acquises' => 40]],
            'modif.siege_nouveau_quartier' => 'Matam',
            'gerant_entrant.prenom_nom'    => 'Mariama SOW',
        ]);

        $this->assertSame([], $this->anomalies($dossier));
    }

    // ── Multi-types : union des exigences ─────────────────────────────────────

    public function test_trois_modifications_ne_produisent_quun_seul_proces_verbal(): void
    {
        $documents = TypeModificationStatutaire::documentsRequisPour([
            TypeModificationStatutaire::CapitalCession,
            TypeModificationStatutaire::GerantStatutaire,
            TypeModificationStatutaire::SiegeSocial,
        ]);

        // Une seule entrée `pv_modification` et un seul jeu de statuts, quelle que soit le
        // nombre de résolutions : c'est une union, pas une concaténation.
        $this->assertSame(
            ['acte_cession', 'pv_modification', 'statuts_maj', 'declaration_rccm'],
            array_keys($documents),
        );
        $this->assertArrayNotHasKey('dnsv', $documents, 'Aucune de ces trois modifications ne souscrit de capital.');
    }

    public function test_lordre_des_documents_ne_depend_pas_de_lordre_de_selection(): void
    {
        $attendu = ['pv_modification', 'dnsv', 'statuts_maj', 'declaration_rccm'];

        $this->assertSame($attendu, array_keys(TypeModificationStatutaire::documentsRequisPour([
            TypeModificationStatutaire::CapitalAugmentation,
            TypeModificationStatutaire::ObjetSocial,
        ])));

        $this->assertSame($attendu, array_keys(TypeModificationStatutaire::documentsRequisPour([
            TypeModificationStatutaire::ObjetSocial,
            TypeModificationStatutaire::CapitalAugmentation,
        ])));
    }

    public function test_depuis_libelles_accepte_lancien_champ_unique_et_le_nouveau_tableau(): void
    {
        // La migration convertit `modif.type` en `modif.types`, mais un brouillon en cours ou
        // un import peut encore porter l'ancienne forme : la refuser bloquerait sans recours.
        $this->assertSame(
            [TypeModificationStatutaire::CapitalCession],
            TypeModificationStatutaire::depuisLibelles('Cession de parts sociales'),
        );
        $this->assertSame(
            [TypeModificationStatutaire::CapitalCession],
            TypeModificationStatutaire::depuisLibelles(['Cession de parts sociales']),
        );
        // Valeur technique acceptée aussi (appel d'API, import).
        $this->assertSame(
            [TypeModificationStatutaire::SiegeSocial],
            TypeModificationStatutaire::depuisLibelles(['siege_social']),
        );
        // Libellé inconnu ignoré plutôt que fatal — remonte comme « type manquant ».
        $this->assertSame([], TypeModificationStatutaire::depuisLibelles(['Changement de couleur']));
    }

    public function test_les_types_sont_dedupliques_et_ordonnes_selon_lenum(): void
    {
        $types = TypeModificationStatutaire::depuisLibelles([
            "Modification de l'objet social",
            'Transfert du siège social',
            'Transfert du siège social',
        ]);

        $this->assertSame(
            [TypeModificationStatutaire::SiegeSocial, TypeModificationStatutaire::ObjetSocial],
            $types,
        );
    }

    // ── Génération des actes : seulement ceux qu'exigent les modifications ────

    /** Crée un modèle actif par `type_document`, sans fichier (on ne teste que la sélection). */
    private function modeles(array $typesDocuments): void
    {
        $typeActe = $this->typeModification();
        foreach ($typesDocuments as $typeDocument) {
            ModeleActe::create([
                'type_acte_id'   => $typeActe->id,
                'nom'            => "Modèle {$typeDocument}",
                'type_document'  => $typeDocument,
                'chemin_fichier' => "modeles/test/{$typeDocument}.docx",
                'version'        => '1.0',
                'est_actif'      => true,
            ]);
        }
    }

    public function test_un_transfert_de_siege_ne_produit_ni_acte_de_cession_ni_dnsv(): void
    {
        $this->modeles(['acte_cession', 'pv_modification', 'dnsv', 'statuts_maj', 'declaration_rccm', 'page_garde']);

        $dossier = $this->dossier($this->donneesBase(['Transfert du siège social']));

        // La génération réelle exige un fichier .docx : on ne vérifie ici que la sélection,
        // via la liste des `type_document` retenus. Exposée par réflexion plutôt que rendue
        // publique — c'est un détail d'implémentation, pas une API du service.
        $retenus = $this->typesDocumentsRetenus($dossier);

        $this->assertContains('pv_modification', $retenus);
        $this->assertContains('statuts_maj', $retenus);
        $this->assertContains('declaration_rccm', $retenus);
        $this->assertContains('page_garde', $retenus, 'La page de garde est inconditionnelle.');
        $this->assertNotContains('acte_cession', $retenus);
        $this->assertNotContains('dnsv', $retenus);
    }

    public function test_une_cession_produit_lacte_de_cession_mais_pas_de_dnsv(): void
    {
        $dossier = $this->dossier($this->donneesBase(['Cession de parts sociales']));
        $retenus = $this->typesDocumentsRetenus($dossier);

        $this->assertContains('acte_cession', $retenus);
        $this->assertNotContains('dnsv', $retenus);
    }

    public function test_une_diminution_de_capital_ne_retient_pas_le_modele_de_dnsv(): void
    {
        $dossier = $this->dossier($this->donneesBase(['Diminution de capital']));

        $this->assertNotContains('dnsv', $this->typesDocumentsRetenus($dossier));
    }

    public function test_non_regression_un_autre_type_dacte_nest_pas_filtre(): void
    {
        // Le filtre ne doit s'appliquer qu'à `SOC-MOD` : `null` signifie « tous les modèles ».
        $dossier = $this->dossier(['soc.denomination' => 'Test SARLU'], null, 'SOC-SARLU');

        $this->assertNull($this->typesDocumentsRetenus($dossier));
    }

    /**
     * La méthode ne prend plus un `Dossier` mais son type d'acte et ses variantes : c'est ce qui
     * permet à l'assistant d'annoncer les actes avant que le dossier existe. On lui repasse ici ce
     * qu'un dossier lui aurait fourni, pour que ces tests continuent de porter sur la règle et non
     * sur la signature.
     */
    private function typesDocumentsRetenus(Dossier $dossier): ?array
    {
        $service = app(ActesGeneratorService::class);

        $variantes = new \ReflectionMethod(ActesGeneratorService::class, 'variantesDuDossier');
        $variantes->setAccessible(true);

        $methode = new \ReflectionMethod(ActesGeneratorService::class, 'typesDocumentsRetenusPour');
        $methode->setAccessible(true);

        return $methode->invoke($service, $dossier->typeActe, $variantes->invoke($service, $dossier));
    }

    // ── Règle 10 : assiette du droit de cession ──────────────────────────────

    public function test_lassiette_du_droit_de_cession_est_la_valeur_des_parts_pas_le_capital(): void
    {
        // Le défaut corrigé : `deduireAssiette()` cherchait `capital_chiffres` (non préfixé),
        // introuvable, et retombait sur zéro. Une fois la clé réelle reconnue, il fallait
        // encore que la valeur des parts cédées la précède — sinon le droit de 2 % se
        // calculerait sur les 50 000 000 du capital au lieu des 25 000 000 cédés.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Cession de parts sociales']),
            'modif.valeur_parts_cedees' => 25_000_000,
            'modif.cedants'             => [['nom' => 'Ibrahima DIALLO', 'parts_cedees' => 40]],
            'modif.cessionnaires'       => [['nom' => 'Mariama SOW', 'parts_acquises' => 40]],
        ], $societe);

        Bareme::create([
            'type_acte_id'           => $dossier->type_acte_id,
            'organisme'              => 'Impots',
            'libelle'                => 'Droit de cession de parts sociales',
            'taux'                   => 2,
            'base_calcul'            => 'valeur_acte',
            'condition_modification' => 'capital_cession',
            'actif'                  => true,
        ]);

        $facture = app(FacturationService::class)->genererFacture($dossier);

        $this->assertEquals(25_000_000, $facture->assiette_chiffres);
        // 2 % de 25 000 000 = 500 000, et non 2 % de 50 000 000 = 1 000 000.
        $this->assertEquals(500_000, $facture->lignes->first()->montant);
    }

    public function test_une_valeur_de_parts_residuelle_ne_sert_pas_dassiette(): void
    {
        // Contrepartie serveur de `purgerChampsInvisibles()` : c'est ici que le défaut mordait.
        // Cocher une cession, saisir la valeur des parts, puis décocher laissait
        // `modif.valeur_parts_cedees` dans `donnees` — et `deduireAssiette()` la retient EN
        // PRIORITÉ, donc la facture portait 25 000 000 d'assiette sur un dossier sans cession.
        // Le frontend purge désormais à la soumission ; ce test documente ce dont il protège.
        $dossier = $this->dossier([
            ...$this->donneesBase(['Transfert du siège social']),
            'modif.valeur_parts_cedees' => 25_000_000, // résidu d'une cession décochée
        ]);

        $facture = app(FacturationService::class)->genererFacture($dossier);

        // Le comportement est celui documenté : la clé résiduelle EST prise pour assiette.
        // C'est précisément pourquoi elle ne doit jamais arriver jusqu'ici.
        $this->assertEquals(25_000_000, $facture->assiette_chiffres);

        // Sans le résidu, l'assiette retombe sur le capital social — le résultat attendu.
        $dossier->questionnaire->update(['donnees' => $this->donneesBase(['Transfert du siège social'])]);

        $facture = app(FacturationService::class)->genererFacture($dossier->fresh());

        $this->assertEquals(50_000_000, $facture->assiette_chiffres);
    }

    // ── Barèmes conditionnels ────────────────────────────────────────────────

    private function baremeDnsv(int $typeActeId, bool $genereFormalite = false): Bareme
    {
        return Bareme::create([
            'type_acte_id'           => $typeActeId,
            'organisme'              => 'Impots',
            'libelle'                => 'Enregistrement DNSV',
            'montant_fixe'           => 100_000,
            'base_calcul'            => 'montant_fixe',
            'condition_modification' => 'capital_augmentation',
            'genere_formalite'       => $genereFormalite,
            'actif'                  => true,
        ]);
    }

    public function test_un_bareme_conditionne_nest_pas_facture_hors_de_son_perimetre(): void
    {
        $dossier = $this->dossier($this->donneesBase(['Transfert du siège social']));
        $this->baremeDnsv($dossier->type_acte_id);

        $facture = app(FacturationService::class)->genererFacture($dossier);

        $this->assertCount(0, $facture->lignes, "Un transfert de siège ne doit pas être facturé d'une DNSV.");
    }

    public function test_un_bareme_conditionne_est_facture_dans_son_perimetre(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Augmentation de capital']),
            'modif.augmentation_montant' => 50_000_000,
        ]);
        $this->baremeDnsv($dossier->type_acte_id);

        $facture = app(FacturationService::class)->genererFacture($dossier);

        $this->assertCount(1, $facture->lignes);
        $this->assertEquals(100_000, $facture->lignes->first()->montant);
    }

    public function test_un_bareme_sans_condition_reste_applicable_partout(): void
    {
        // Non-régression : tous les barèmes existants sont dans ce cas, leur comportement ne
        // doit pas changer.
        $dossier = $this->dossier($this->donneesBase(['Transfert du siège social']));

        $bareme = Bareme::create([
            'type_acte_id' => $dossier->type_acte_id,
            'organisme'    => 'Greffe',
            'libelle'      => 'Enregistrement au Tribunal de Commerce (RCCM)',
            'montant_fixe' => 180_000,
            'base_calcul'  => 'montant_fixe',
            'actif'        => true,
        ]);

        $this->assertTrue($bareme->estApplicableA($dossier));
    }

    public function test_une_formalite_conditionnee_nest_pas_ouverte_hors_de_son_perimetre(): void
    {
        $dossier = $this->dossier($this->donneesBase(['Diminution de capital']));
        $this->baremeDnsv($dossier->type_acte_id, genereFormalite: true);

        app(FormaliteGenerationService::class)->genererFormalites($dossier);

        $this->assertCount(
            0,
            $dossier->fresh()->formalites,
            "Le formaliste ne doit pas se voir ouvrir une démarche DNSV sur une réduction de capital.",
        );
    }

    // ── Contrôles bloquants ──────────────────────────────────────────────────

    private function anomalies(Dossier $dossier): array
    {
        return app(ReglesSocieteService::class)->anomalies($dossier->fresh());
    }

    public function test_une_modification_sans_societe_designee_est_bloquee(): void
    {
        $donnees = $this->donneesBase(['Transfert du siège social']);
        unset($donnees['soc.denomination']);

        $dossier = $this->dossier([
            ...$donnees,
            'modif.siege_nouveau_quartier' => 'Matam',
            'modif.siege_nouveau_commune'  => 'Matam',
            'modif.siege_nouveau_ville'    => 'Conakry',
        ]);

        $this->assertArrayHasKey('modif_societe', $this->anomalies($dossier));
    }

    public function test_une_societe_du_registre_suffit_a_designer_la_societe(): void
    {
        $donnees = $this->donneesBase(['Transfert du siège social']);
        unset($donnees['soc.denomination']);

        $dossier = $this->dossier($donnees, $this->societe());

        $this->assertArrayNotHasKey('modif_societe', $this->anomalies($dossier));
    }

    public function test_une_modification_sans_date_dassemblee_est_bloquee(): void
    {
        $donnees = $this->donneesBase(['Transfert du siège social']);
        unset($donnees['ag.date']);

        $dossier = $this->dossier($donnees);

        $this->assertArrayHasKey('modif_assemblee', $this->anomalies($dossier));
    }

    public function test_une_cession_exige_un_cedant_et_un_cessionnaire(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Cession de parts sociales']),
            'modif.valeur_parts_cedees' => 25_000_000,
            'modif.cedants'             => [['nom' => 'Ibrahima DIALLO', 'parts_cedees' => 40]],
            // Cessionnaires manquants
        ]);

        $this->assertArrayHasKey('modif_parties_cession', $this->anomalies($dossier));
    }

    public function test_un_cedant_ne_peut_ceder_plus_de_parts_quil_nen_detient(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Cession de parts sociales']),
            'modif.valeur_parts_cedees' => 25_000_000,
            'modif.cedants'             => [['nom' => 'Ibrahima DIALLO', 'parts_detenues' => 30, 'parts_cedees' => 40]],
            'modif.cessionnaires'       => [['nom' => 'Mariama SOW', 'parts_acquises' => 40]],
        ]);

        $anomalies = $this->anomalies($dossier);

        $this->assertArrayHasKey('modif_parts_cedees', $anomalies);
        $this->assertStringContainsString('Ibrahima DIALLO', $anomalies['modif_parts_cedees'][0]);
    }

    public function test_une_reduction_de_capital_ne_peut_laisser_un_capital_nul(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Diminution de capital']),
            'modif.diminution_montant' => 50_000_000, // = le capital entier
        ]);

        $anomalies = $this->anomalies($dossier);

        $this->assertArrayHasKey('modif_diminution', $anomalies);
        $this->assertStringContainsString('dissolution', $anomalies['modif_diminution'][0]);
    }

    public function test_une_sa_ne_peut_reduire_son_capital_sous_le_minimum_legal(): void
    {
        // Règle 1 appliquée à la modification : ce garde-fou n'existait que pour la
        // constitution, si bien qu'on obtenait par réduction ce qu'elle interdisait.
        $dossier = $this->dossier([
            'modif.types'              => ['Diminution de capital'],
            'soc.denomination'         => 'Guinée Holdings SA',
            'soc.forme'                => 'SA',
            'soc.capital_chiffres'     => 200_000_000,
            'ag.date'                  => '01/08/2026',
            'modif.diminution_montant' => 100_000_000, // laisserait 100 M < 140 M
        ]);

        $anomalies = $this->anomalies($dossier);

        $this->assertArrayHasKey('modif_diminution', $anomalies);
        $this->assertStringContainsString('140 000 000', $anomalies['modif_diminution'][0]);
    }

    public function test_une_sarl_peut_reduire_son_capital_sans_minimum_legal(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Diminution de capital']),
            'modif.diminution_montant' => 49_000_000, // laisse 1 000 000, légal en SARL
        ]);

        $this->assertArrayNotHasKey('modif_diminution', $this->anomalies($dossier));
    }

    public function test_un_changement_de_gerant_exige_un_gerant_entrant(): void
    {
        $dossier = $this->dossier([
            ...$this->donneesBase(['Changement de gérant statutaire']),
            'gerant_sortant.prenom_nom' => 'Ibrahima DIALLO',
        ]);

        $this->assertArrayHasKey('modif_gerant', $this->anomalies($dossier));
    }

    public function test_toutes_les_anomalies_sont_remontees_ensemble(): void
    {
        // Corriger un dossier en six allers-retours n'est pas un service rendu.
        $dossier = $this->dossier([
            'modif.types'               => ['Cession de parts sociales', 'Changement de gérant statutaire'],
            'soc.forme'                 => 'SARL',
            'soc.capital_chiffres'      => 50_000_000,
        ]);

        $anomalies = $this->anomalies($dossier);

        $this->assertArrayHasKey('modif_societe', $anomalies);
        $this->assertArrayHasKey('modif_assemblee', $anomalies);
        $this->assertArrayHasKey('modif_valeur_parts', $anomalies);
        $this->assertArrayHasKey('modif_parties_cession', $anomalies);
        $this->assertArrayHasKey('modif_gerant', $anomalies);
    }

    public function test_une_modification_ne_fait_pas_apparaitre_sa_societe_en_doublon_de_denomination(): void
    {
        // Défaut constaté sur la base réelle le 2026-08-11 : `SOC-2026-0010` (constitution de
        // MICH SARL) était signalé en conflit de dénomination avec `SOC-2026-0012`, la
        // modification de cette même société. La règle 4 interdit deux **sociétés** homonymes,
        // pas qu'une société apparaisse dans plusieurs de ses propres dossiers.
        $constitution = $this->dossier([
            'soc.denomination'     => 'Faya Distribution SARLU',
            'soc.capital_chiffres' => 50_000_000,
        ], null, 'SOC-SARLU');

        // Sans le dossier de modification, la constitution est conforme.
        $this->assertArrayNotHasKey('soc_denomination', $this->anomalies($constitution));

        $this->dossier($this->donneesBase(['Transfert du siège social']) + [
            'soc.denomination' => 'Faya Distribution SARLU',
        ]);

        $this->assertArrayNotHasKey('soc_denomination', $this->anomalies($constitution));
    }

    public function test_deux_constitutions_homonymes_restent_bloquees(): void
    {
        // Non-régression de la règle 4 : le correctif ci-dessus ne doit pas la désarmer.
        $premier = $this->dossier(['soc.denomination' => 'Faya Distribution SARLU'], null, 'SOC-SARLU');
        $second  = $this->dossier(['soc.denomination' => 'faya  distribution sarlu'], null, 'SOC-SARL');

        $this->assertArrayHasKey('soc_denomination', $this->anomalies($premier));
        $this->assertArrayHasKey('soc_denomination', $this->anomalies($second));
    }

    // ── Restitution : impact et documents affichés au dossier ────────────────

    public function test_le_resume_de_la_modification_est_lunion_des_types(): void
    {
        $dossier = $this->dossier($this->donneesBase([
            'Cession de parts sociales',
            'Transfert du siège social',
        ]));

        $resume = app(ReglesSocieteService::class)->modificationStatutaire($dossier);

        $this->assertCount(2, $resume['types']);
        $this->assertTrue($resume['impacteStatuts']);
        $this->assertTrue($resume['impacteRccm']);
        $this->assertFalse($resume['exigeDnsv']);
        $this->assertArrayHasKey('acte_cession', $resume['documentsRequis']);
        $this->assertArrayNotHasKey('dnsv', $resume['documentsRequis']);
    }

    public function test_le_resume_est_nul_pour_un_autre_type_dacte(): void
    {
        $dossier = $this->dossier(['soc.denomination' => 'Test'], null, 'SOC-SARLU');

        $this->assertNull(app(ReglesSocieteService::class)->modificationStatutaire($dossier));
    }

    // ── Mise à jour de la fiche du registre ──────────────────────────────────

    private function appliquer(Dossier $dossier): array
    {
        return app(SocieteMutationService::class)->appliquer($dossier->fresh());
    }

    public function test_un_transfert_de_siege_met_a_jour_la_fiche_du_registre(): void
    {
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Transfert du siège social']),
            'modif.siege_nouveau_quartier' => 'Matam',
            'modif.siege_nouveau_commune'  => 'Matam',
            'modif.siege_nouveau_ville'    => 'Conakry',
        ], $societe);

        $modifications = $this->appliquer($dossier);

        $this->assertArrayHasKey('siege_quartier', $modifications);
        $societe->refresh();
        $this->assertSame('Matam', $societe->siege_quartier);
        $this->assertSame('Matam', $societe->siege_commune);
        // La ville n'a pas changé : elle ne doit pas figurer parmi les modifications.
        $this->assertArrayNotHasKey('siege_ville', $modifications);
        $this->assertNotNull($societe->derniere_modification_at);
    }

    public function test_une_augmentation_de_capital_met_a_jour_capital_et_parts(): void
    {
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Augmentation de capital']),
            'modif.augmentation_montant'        => 50_000_000,
            'modif.augmentation_capital_apres'  => 100_000_000,
            'modif.augmentation_parts_nouvelles' => 100,
        ], $societe);

        $this->appliquer($dossier);
        $societe->refresh();

        $this->assertEquals(100_000_000, $societe->capital_chiffres);
        $this->assertSame(200, $societe->nombre_parts);
    }

    public function test_une_diminution_de_capital_reduit_capital_et_parts(): void
    {
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Diminution de capital']),
            'modif.diminution_montant'       => 20_000_000,
            'modif.diminution_capital_apres' => 30_000_000,
            'modif.diminution_parts_annulees' => 40,
        ], $societe);

        $this->appliquer($dossier);
        $societe->refresh();

        $this->assertEquals(30_000_000, $societe->capital_chiffres);
        $this->assertSame(60, $societe->nombre_parts);
    }

    public function test_un_changement_de_gerant_met_a_jour_la_direction(): void
    {
        $societe = $this->societe(['direction' => ['president' => 'Ancien Président']]);
        $dossier = $this->dossier([
            ...$this->donneesBase(['Changement de gérant statutaire']),
            'gerant_entrant.prenom_nom' => 'Mariama SOW',
            'gerant_entrant.civilite'   => 'Mme',
        ], $societe);

        $this->appliquer($dossier);
        $societe->refresh();

        $this->assertSame('Mariama SOW', $societe->direction['gerant']);
        // Les autres dirigeants de la fiche ne sont pas emportés par ce changement.
        $this->assertSame('Ancien Président', $societe->direction['president']);
    }

    public function test_une_cession_de_parts_ne_modifie_pas_la_fiche_de_la_societe(): void
    {
        // Une cession change les associés, pas les caractéristiques de la société : ni son
        // capital, ni son siège, ni son objet.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Cession de parts sociales']),
            'modif.valeur_parts_cedees' => 25_000_000,
        ], $societe);

        $this->assertSame([], $this->appliquer($dossier));
        $this->assertNull($societe->fresh()->derniere_modification_at);
    }

    public function test_lapplication_est_journalisee_avec_le_detail_des_champs(): void
    {
        // Une fiche de référence notariale qui change en silence est inacceptable.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(["Modification de l'objet social"]),
            'modif.objet_nouveau' => 'Import-export et distribution',
        ], $societe);

        $this->appliquer($dossier);

        $entree = $dossier->journal()->where('type', 'modification')->first();

        $this->assertNotNull($entree);
        $this->assertStringContainsString('objet social', $entree->action);
        $this->assertArrayHasKey('objet_social', $entree->meta['champs']);
    }

    public function test_reappliquer_ne_signale_aucune_modification(): void
    {
        // Un dossier peut repasser en Expédition (renvoi, correction) : la seconde
        // application ne doit pas rejournaliser des changements déjà portés.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Transfert du siège social']),
            'modif.siege_nouveau_quartier' => 'Matam',
            'modif.siege_nouveau_commune'  => 'Matam',
            'modif.siege_nouveau_ville'    => 'Conakry',
        ], $societe);

        $this->assertNotEmpty($this->appliquer($dossier));
        $this->assertSame([], $this->appliquer($dossier));
    }

    public function test_une_colonne_reclamee_avec_deux_valeurs_contradictoires_est_ecartee(): void
    {
        // Défense en profondeur : un dossier ouvert avant les garde-fous peut porter les deux
        // opérations de capital. La version précédente écrivait « la dernière valeur fusionnée »,
        // donc l'ordre de déclaration de l'enum décidait du capital, en silence.
        $societe = $this->societe();
        $dossier = $this->dossier([
            // Le transfert de siège est décidé par la même assemblée : il ne doit PAS être empêché
            // par le conflit portant sur le capital.
            ...$this->donneesBase(['Augmentation de capital', 'Diminution de capital', 'Transfert du siège social']),
            'modif.augmentation_montant'       => 50_000_000,
            'modif.augmentation_capital_apres' => 100_000_000,
            'modif.diminution_montant'         => 20_000_000,
            'modif.diminution_capital_apres'   => 30_000_000,
            'modif.siege_nouveau_quartier'     => 'Matam',
            'modif.siege_nouveau_commune'      => 'Matam',
            'modif.siege_nouveau_ville'        => 'Conakry',
        ], $societe);

        $modifications = $this->appliquer($dossier);
        $societe->refresh();

        $this->assertArrayNotHasKey('capital_chiffres', $modifications, 'Le capital ne doit pas être arbitré.');
        $this->assertEquals(50_000_000, $societe->capital_chiffres, 'Le capital au registre reste inchangé.');
        // Le siège, lui, s'applique normalement.
        $this->assertSame('Matam', $societe->siege_quartier);
    }

    public function test_le_refus_dappliquer_une_colonne_est_journalise(): void
    {
        // Une fiche qui reste inchangée sans explication est aussi problématique qu'une fiche
        // modifiée en silence : l'équipe croirait le registre à jour.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Augmentation de capital', 'Diminution de capital']),
            'modif.augmentation_capital_apres' => 100_000_000,
            'modif.diminution_capital_apres'   => 30_000_000,
        ], $societe);

        $this->appliquer($dossier);

        $entree = $dossier->journal()->where('type', 'modification')->first();

        $this->assertNotNull($entree);
        $this->assertStringContainsString('contradictoires', $entree->action);
        $this->assertContains('capital_chiffres', $entree->meta['champs_ecartes']);
    }

    public function test_deux_types_proposant_la_meme_valeur_ne_sont_pas_un_conflit(): void
    {
        // Les deux changements de gérant écrivent le même bloc `direction`. Ils sont désormais
        // mutuellement exclusifs, mais la détection de collision ne doit pas pour autant traiter
        // une valeur identique comme un conflit — sinon toute paire écrivant la même colonne
        // deviendrait ingérable.
        $societe = $this->societe();
        $dossier = $this->dossier([
            ...$this->donneesBase(['Changement de gérant statutaire', 'Changement de gérant non statutaire']),
            'gerant_entrant.prenom_nom' => 'Mariama SOW',
        ], $societe);

        $this->appliquer($dossier);

        $this->assertSame('Mariama SOW', $societe->fresh()->direction['gerant']);
    }

    public function test_aucune_fiche_rattachee_nempeche_pas_lavancement(): void
    {
        // Sans société au dossier, il n'y a rien à mettre à jour — mais l'avancement ne doit
        // pas pour autant échouer : le contrôle bloquant est celui de ReglesSocieteService.
        $dossier = $this->dossier($this->donneesBase(['Transfert du siège social']));

        $this->assertSame([], $this->appliquer($dossier));
    }
}
