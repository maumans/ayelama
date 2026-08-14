<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\TypeModificationStatutaire;
use App\Models\DocumentAttendu;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ActesGeneratorService;
use App\Support\VariantesTypeActe;
use Database\Seeders\ReglesGestionDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Configuration des actes à la granularité de la **procédure** (2026-08-11).
 *
 * Un gabarit ne se rattachait qu'à un type d'acte entier. Insuffisant pour la société, dont la
 * modification se décline en sept résolutions : un acte de cession ne sert qu'aux cessions, un
 * procès-verbal sert les sept. Et le bouton « Générer » était une réimplémentation dérivée du
 * service, qui ignorait les modèles partagés comme les filtres de variante.
 */
class ConfigurationActesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function utilisateur(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Administrateur->value]);

        return $user->fresh();
    }

    private function typeActe(string $code): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    /** Modèle actif dont le fichier existe réellement, sinon la génération produit un placeholder. */
    private function modele(string $code, string $typeDocument, ?string $variante = null, array $roles = []): ModeleActe
    {
        $chemin = 'modeles/' . $typeDocument . '-' . fake()->unique()->numerify('###') . '.docx';
        Storage::disk('local')->put($chemin, 'gabarit');

        $modele = ModeleActe::create([
            'nom'            => 'Gabarit ' . $typeDocument . ' ' . fake()->unique()->numerify('##'),
            'type_document'  => $typeDocument,
            'chemin_fichier' => $chemin,
            'version'        => '1.0',
            'est_actif'      => true,
        ]);

        $modele->rattachements()->create(['type_acte_id' => $this->typeActe($code)->id, 'variante' => $variante]);
        $modele->definirRoles($roles !== [] ? $roles : [$typeDocument]);

        return $modele->fresh();
    }

    private function dossier(string $code, array $donnees = [], ?EtapeDossier $etape = null): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'etape'        => $etape ?? EtapeDossier::Edition,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour la configuration des actes',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    private function modification(array $types): Dossier
    {
        return $this->dossier('SOC-MOD', [
            'modif.types'      => $types,
            'soc.denomination' => 'Faya SARL',
            'ag.date'          => '01/08/2026',
        ]);
    }

    // ── Variantes ────────────────────────────────────────────────────────────

    public function test_un_type_dacte_sans_variante_ne_se_decline_pas(): void
    {
        $this->assertFalse(VariantesTypeActe::existePour('SOC-SARLU'));
        $this->assertSame([], VariantesTypeActe::pour('VTE-IMM'));
    }

    public function test_soc_mod_declare_ses_sept_variantes(): void
    {
        $this->assertTrue(VariantesTypeActe::existePour('SOC-MOD'));
        $this->assertCount(7, VariantesTypeActe::pour('SOC-MOD'));
        $this->assertContains('capital_cession', VariantesTypeActe::valeurs('SOC-MOD'));
    }

    public function test_un_rattachement_sans_variante_couvre_toutes_les_variantes(): void
    {
        $modele = $this->modele('SOC-MOD', 'pv_modification');
        $type = $this->typeActe('SOC-MOD');

        foreach (VariantesTypeActe::valeurs('SOC-MOD') as $variante) {
            $this->assertTrue($modele->applicablePour($type, $variante), "Doit couvrir {$variante}.");
        }
    }

    public function test_un_rattachement_a_une_variante_ne_couvre_quelle(): void
    {
        $modele = $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');
        $type = $this->typeActe('SOC-MOD');

        $this->assertTrue($modele->applicablePour($type, 'capital_cession'));
        $this->assertFalse($modele->applicablePour($type, 'siege_social'));
    }

    public function test_applicable_tous_couvre_toute_variante(): void
    {
        $modele = $this->modele('SOC-MOD', 'page_garde');
        $modele->update(['applicable_tous' => true]);
        $modele->rattachements()->delete();

        $this->assertTrue($modele->fresh()->applicablePour($this->typeActe('VTE-IMM'), 'inexistante'));
    }

    public function test_un_acte_restreint_nest_pas_produit_hors_de_sa_variante(): void
    {
        // Le risque inverse du « aucun modèle actif » : produire un acte de cession sur un dossier
        // qui ne décide aucune cession. L'ancien bouton « Générer » le faisait.
        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');
        $this->modele('SOC-MOD', 'pv_modification');

        $dossier = $this->modification(['Transfert du siège social']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        $categories = $dossier->fresh()->documents->pluck('categorie');

        $this->assertContains('pv_modification', $categories);
        $this->assertNotContains('acte_cession', $categories);
    }

    public function test_un_acte_restreint_est_produit_dans_sa_variante(): void
    {
        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');

        $dossier = $this->modification(['Cession de parts sociales']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        $this->assertContains('acte_cession', $dossier->fresh()->documents->pluck('categorie'));
    }

    // ── Rôles ────────────────────────────────────────────────────────────────

    public function test_un_gabarit_a_deux_roles_sert_les_deux_procedures(): void
    {
        // Le vocabulaire diffère entre procédures pour un même document : c'est ce qui empêchait de
        // partager un gabarit de statuts entre création et modification.
        $statuts = $this->modele('SOC-SARLU', 'acte_principal', null, ['acte_principal', 'statuts_maj']);
        $statuts->rattachements()->create(['type_acte_id' => $this->typeActe('SOC-MOD')->id, 'variante' => null]);

        $this->assertTrue($statuts->fresh()->remplitRole('acte_principal'));
        $this->assertTrue($statuts->fresh()->remplitRole('statuts_maj'));

        $dossier = $this->modification(['Transfert du siège social']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        // Retenu sous le rôle attendu par la procédure, pas sous son rôle principal.
        $this->assertContains('statuts_maj', $dossier->fresh()->documents->pluck('categorie'));
    }

    public function test_un_role_non_declare_nest_jamais_retenu(): void
    {
        $this->modele('SOC-MOD', 'dnsv');

        // Un transfert de siège n'attend pas de DNSV.
        $dossier = $this->modification(['Transfert du siège social']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        $this->assertNotContains('dnsv', $dossier->fresh()->documents->pluck('categorie'));
    }

    public function test_un_modele_sans_roles_declares_replie_sur_son_type_document(): void
    {
        $modele = $this->modele('SOC-MOD', 'pv_modification');
        \Illuminate\Support\Facades\DB::table('modele_acte_roles')->where('modele_acte_id', $modele->id)->delete();

        $this->assertSame(['pv_modification'], $modele->fresh()->rolesRemplis());
    }

    // ── Documents attendus : configuration et référence ──────────────────────

    public function test_sans_configuration_la_regle_ecrite_sapplique(): void
    {
        // Garantie de zéro changement au déploiement : la table est vide, l'enum décide.
        $this->assertSame(0, DocumentAttendu::count());

        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');
        $dossier = $this->modification(['Cession de parts sociales']);

        app(ActesGeneratorService::class)->produireActes($dossier);

        $this->assertContains('acte_cession', $dossier->fresh()->documents->pluck('categorie'));
    }

    public function test_le_seeder_reproduit_exactement_la_reference(): void
    {
        $this->typeActe('SOC-MOD');
        (new ReglesGestionDocumentsSeeder())->run();

        foreach (TypeModificationStatutaire::cases() as $cas) {
            $configures = DocumentAttendu::where('variante', $cas->valeur())
                ->pluck('type_document')
                ->sort()
                ->values()
                ->all();
            $attendus = collect(array_keys($cas->documentsRequis()))->sort()->values()->all();

            $this->assertSame($attendus, $configures, "Variante {$cas->valeur()}.");
        }
    }

    public function test_le_seeder_est_idempotent(): void
    {
        $this->typeActe('SOC-MOD');
        (new ReglesGestionDocumentsSeeder())->run();
        $avant = DocumentAttendu::count();
        (new ReglesGestionDocumentsSeeder())->run();

        $this->assertSame($avant, DocumentAttendu::count());
    }

    public function test_la_configuration_prime_sur_la_regle_ecrite(): void
    {
        $type = $this->typeActe('SOC-MOD');
        (new ReglesGestionDocumentsSeeder())->run();

        // L'étude retire l'acte de cession de la procédure de cession.
        DocumentAttendu::where('type_acte_id', $type->id)
            ->where('variante', 'capital_cession')
            ->where('type_document', 'acte_cession')
            ->delete();

        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');
        $dossier = $this->modification(['Cession de parts sociales']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        $this->assertNotContains('acte_cession', $dossier->fresh()->documents->pluck('categorie'));
    }

    public function test_un_ecart_a_la_reference_est_detecte(): void
    {
        $this->assertFalse(DocumentAttendu::divergeDeLaReference(
            'SOC-MOD',
            'capital_cession',
            array_keys(TypeModificationStatutaire::CapitalCession->documentsRequis()),
        ));

        $this->assertTrue(DocumentAttendu::divergeDeLaReference('SOC-MOD', 'capital_cession', ['pv_modification']));

        // Un type sans règle écrite ne peut pas diverger.
        $this->assertFalse(DocumentAttendu::divergeDeLaReference('SOC-SARLU', null, ['n_importe_quoi']));
    }

    public function test_la_reinitialisation_restaure_la_reference(): void
    {
        $type = $this->typeActe('SOC-MOD');
        (new ReglesGestionDocumentsSeeder())->run();

        DocumentAttendu::where('type_acte_id', $type->id)->where('variante', 'capital_cession')->delete();
        $this->assertSame(0, DocumentAttendu::where('variante', 'capital_cession')->count());

        $this->actingAs($this->utilisateur())
            ->post("/parametres/types-actes/{$type->id}/documents-attendus/reinitialiser", ['variante' => 'capital_cession'])
            ->assertRedirect();

        $this->assertSame(
            array_keys(TypeModificationStatutaire::CapitalCession->documentsRequis()),
            DocumentAttendu::where('variante', 'capital_cession')->orderBy('ordre')->pluck('type_document')->all(),
        );
    }

    public function test_une_variante_inconnue_est_refusee(): void
    {
        $type = $this->typeActe('SOC-MOD');

        $this->actingAs($this->utilisateur())
            ->patch("/parametres/types-actes/{$type->id}/documents-attendus", [
                'variante'       => 'inventee',
                'type_documents' => ['pv_modification'],
            ])
            ->assertSessionHasErrors('variante');
    }

    // ── Fin du doublon de génération ─────────────────────────────────────────

    public function test_le_bouton_et_lavancement_produisent_le_meme_resultat(): void
    {
        // **Le test qui verrouille la fin du doublon.** Le bouton portait sa propre copie de la
        // génération, et les deux avaient dérivé.
        $this->modele('SOC-MOD', 'pv_modification');
        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');

        $parService = $this->modification(['Transfert du siège social']);
        app(ActesGeneratorService::class)->produireActes($parService);

        $parBouton = $this->modification(['Transfert du siège social']);
        $this->actingAs($this->utilisateur())
            ->post("/dossiers/{$parBouton->reference}/generer-documents")
            ->assertRedirect();

        $this->assertSame(
            $parService->fresh()->documents->pluck('categorie')->sort()->values()->all(),
            $parBouton->fresh()->documents->pluck('categorie')->sort()->values()->all(),
        );
    }

    public function test_le_bouton_distingue_labsence_de_gabarit(): void
    {
        $dossier = $this->modification(['Transfert du siège social']);

        $this->actingAs($this->utilisateur())
            ->post("/dossiers/{$dossier->reference}/generer-documents")
            ->assertSessionHas('error');
    }

    public function test_le_bouton_distingue_les_actes_deja_presents(): void
    {
        $this->modele('SOC-MOD', 'pv_modification');
        $dossier = $this->modification(['Transfert du siège social']);

        $user = $this->utilisateur();
        $this->actingAs($user)->post("/dossiers/{$dossier->reference}/generer-documents");
        $this->actingAs($user)
            ->post("/dossiers/{$dossier->reference}/generer-documents")
            ->assertSessionHas('success', 'Tous les actes attendus sont déjà présents au dossier.');
    }

    // ── Non-régression ───────────────────────────────────────────────────────

    public function test_une_creation_produit_ses_actes_sans_variante(): void
    {
        $this->modele('SOC-SARLU', 'acte_principal');
        $this->modele('SOC-SARLU', 'dnsv');

        $dossier = $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya SARLU']);

        $this->assertSame(2, app(ActesGeneratorService::class)->genererActesDepuisModeles($dossier));
    }

    public function test_un_dossier_sans_variante_decidee_ne_produit_pas_dactes_restreints(): void
    {
        // Un dossier dont le type de modification n'est pas encore précisé ne doit pas se remplir
        // d'actes hors sujet.
        $this->modele('SOC-MOD', 'acte_cession', 'capital_cession');

        $dossier = $this->dossier('SOC-MOD', ['soc.denomination' => 'Faya SARL']);
        app(ActesGeneratorService::class)->produireActes($dossier);

        $this->assertNotContains('acte_cession', $dossier->fresh()->documents->pluck('categorie'));
    }

    // ── Le rôle principal l'emporte quand rien ne l'impose ───────────────────

    public function test_le_role_principal_prime_quand_la_procedure_nimpose_rien(): void
    {
        // Régression du 2026-08-11 : `rolePourProcedure()` retenait le **premier** rôle déclaré au
        // lieu du rôle principal dès que la procédure n'exprimait aucune attente — le cas de toutes
        // les constitutions, ventes, baux et hypothèques. Un gabarit « RCCM » déclarant aussi
        // `declaration_rccm` produisait donc un document typé `declaration_rccm` sur une SARLU.
        //
        // Les rôles sont volontairement déclarés dans l'ordre **inverse** du rôle principal : sans
        // le correctif, ce test échoue.
        $modele = $this->modele('SOC-SARLU', 'rccm', null, ['declaration_rccm', 'rccm']);

        $prevus = app(ActesGeneratorService::class)->actesPrevus($this->typeActe('SOC-SARLU'));
        $acte   = collect($prevus)->firstWhere('nom', $modele->nom);

        $this->assertNotNull($acte);
        $this->assertSame('rccm', $acte['type_document'], 'Le rôle principal doit trancher.');
    }

    public function test_la_procedure_garde_la_main_quand_elle_attend_un_role_precis(): void
    {
        // Le pendant : sur une modification, c'est la procédure qui décide — le même gabarit y sert
        // son rôle de modification, pas son rôle principal.
        $modele = $this->modele('SOC-MOD', 'rccm', null, ['rccm', 'declaration_rccm']);

        $prevus = app(ActesGeneratorService::class)
            ->actesPrevus($this->typeActe('SOC-MOD'), ['capital_augmentation']);
        $acte = collect($prevus)->firstWhere('nom', $modele->nom);

        $this->assertNotNull($acte);
        $this->assertSame('declaration_rccm', $acte['type_document']);
    }

    // ── Rôles attendus par une procédure ─────────────────────────────────────

    public function test_une_procedure_sans_documents_attendus_accepte_tous_les_roles(): void
    {
        // `null` n'est pas « aucun » mais « tous » : c'est ce qui fait que la modale ne scinde pas
        // sa liste de rôles pour une constitution.
        $this->assertNull(
            app(ActesGeneratorService::class)->rolesAttendusPour($this->typeActe('SOC-SARLU')),
        );
    }

    public function test_les_roles_attendus_dune_modification_couvrent_toutes_les_variantes(): void
    {
        $roles = app(ActesGeneratorService::class)->rolesAttendusPour($this->typeActe('SOC-MOD'));

        $this->assertNotNull($roles);
        // Union sur les sept résolutions : l'acte de cession (cession seule) comme la DNSV
        // (augmentation seule) en font partie — un gabarit sert le type d'acte, pas une résolution.
        $this->assertContains('acte_cession', $roles);
        $this->assertContains('dnsv', $roles);
        $this->assertContains('declaration_rccm', $roles);
    }

    // ── L'écart signalé sur le dossier ───────────────────────────────────────

    public function test_un_acte_prevu_et_absent_est_signale_sur_le_dossier(): void
    {
        // Corriger un rattachement ne réveille pas les dossiers existants — voulu — mais l'écart
        // doit se voir, sinon l'acte manquant reste invisible tant qu'on ne le cherche pas.
        $modele  = $this->modele('SOC-SARLU', 'acte_principal');
        $dossier = $this->dossier('SOC-SARLU');

        $this->actingAs($this->utilisateur())
            ->get("/dossiers/{$dossier->reference}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('dossier.actesManquants.0.nom', $modele->nom));
    }

    public function test_un_dossier_complet_ne_signale_aucun_ecart(): void
    {
        $modele  = $this->modele('SOC-SARLU', 'acte_principal');
        $dossier = $this->dossier('SOC-SARLU');
        $dossier->documents()->create(['nom' => $modele->nom, 'categorie' => 'acte_principal', 'statut' => 'a_editer']);

        $reponse = $this->actingAs($this->utilisateur())
            ->get("/dossiers/{$dossier->reference}")
            ->assertOk();

        $manquants = collect($reponse->viewData('page')['props']['dossier']['actesManquants'])->pluck('nom');

        $this->assertNotContains($modele->nom, $manquants, "L'acte présent au dossier ne doit pas être signalé manquant.");
    }

    public function test_un_acte_sans_gabarit_nest_pas_signale_comme_manquant(): void
    {
        // Un rôle attendu sans gabarit relève de la configuration, pas du dossier : le bouton
        // « Produire les actes manquants » ne pourrait rien en faire, l'annoncer serait trompeur.
        $dossier = $this->dossier('SOC-MOD', ['modif.types' => ['Augmentation de capital']]);

        $reponse = $this->actingAs($this->utilisateur())
            ->get("/dossiers/{$dossier->reference}")
            ->assertOk();

        $manquants = collect($reponse->viewData('page')['props']['dossier']['actesManquants'])->pluck('type_document');

        // La procédure attend un PV, des statuts à jour et une déclaration RCCM — aucun gabarit ne
        // les remplit ici, et aucun ne doit donc être annoncé comme « à produire ».
        $this->assertNotContains('pv_modification', $manquants);
        $this->assertNotContains('declaration_rccm', $manquants);
    }

    public function test_un_role_secondaire_ne_sert_a_rien_tant_que_le_type_nest_pas_rattache(): void
    {
        // Le scénario signalé : un gabarit RCCM rattaché à **deux constitutions** portait aussi le
        // rôle `declaration_rccm`, et l'étude attendait qu'il soit produit sur les modifications.
        // Il ne l'est pas, et c'est correct — c'est le **rattachement** qui décide de l'applicabilité,
        // le rôle ne fait que nommer ce que le gabarit remplit une fois le type rattaché.
        //
        // Ce test fixe cette vérité, qui justifie l'avertissement de la modale : un rôle ni principal
        // ni attendu par un type rattaché est inerte.
        $modele = $this->modele('SOC-SARLU', 'rccm', null, ['declaration_rccm', 'rccm']);
        $modele->rattachements()->create(['type_acte_id' => $this->typeActe('SOC-SARL')->id, 'variante' => null]);

        $service = app(ActesGeneratorService::class);

        // Sur la constitution : produit, et sous son rôle principal.
        $surSarlu = collect($service->actesPrevus($this->typeActe('SOC-SARLU')))->firstWhere('nom', $modele->nom);
        $this->assertNotNull($surSarlu);
        $this->assertSame('rccm', $surSarlu['type_document']);

        // Sur la modification : absent, faute de rattachement. Le rôle seul n'y change rien.
        $surMod = collect($service->actesPrevus($this->typeActe('SOC-MOD'), ['capital_augmentation']))
            ->firstWhere('nom', $modele->nom);
        $this->assertNull($surMod, "Le rôle seul ne doit pas rendre un gabarit applicable à une autre procédure.");
    }

    public function test_le_rattachement_rend_le_gabarit_applicable_a_la_modification(): void
    {
        // Le pendant : une fois le type rattaché, le rôle prend effet et l'acte est produit.
        $modele = $this->modele('SOC-SARLU', 'rccm', null, ['declaration_rccm', 'rccm']);
        $modele->rattachements()->create(['type_acte_id' => $this->typeActe('SOC-MOD')->id, 'variante' => null]);

        $surMod = collect(app(ActesGeneratorService::class)
            ->actesPrevus($this->typeActe('SOC-MOD'), ['capital_augmentation']))
            ->firstWhere('nom', $modele->nom);

        $this->assertNotNull($surMod);
        $this->assertSame('declaration_rccm', $surMod['type_document']);
        $this->assertTrue($surMod['a_gabarit']);
    }
}
