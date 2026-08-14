<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ActesGeneratorService;
use App\Services\ReglesSocieteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Production des actes d'une modification de statuts (2026-08-11).
 *
 * Signalé sur `SOC-2026-0013` : le panneau annonçait 4 actes attendus, l'onglet Actes affichait
 * « Aucun document ». Quatre causes distinctes, toutes couvertes ici.
 */
class ActesModificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function utilisateur(RoleUtilisateur $role = RoleUtilisateur::Administrateur): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh();
    }

    private function typeActe(string $code): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    private function dossier(string $code, array $donnees = [], ?Societe $societe = null, ?EtapeDossier $etape = null): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'societe_id'   => $societe?->id,
            'etape'        => $etape ?? EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour la production des actes',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    private function donneesModification(): array
    {
        return [
            'modif.types'                  => ['Transfert du siège social'],
            'soc.denomination'             => 'SOC TEST',
            'ag.date'                      => '01/08/2026',
            'modif.siege_nouveau_quartier' => 'Matam',
            'modif.siege_nouveau_commune'  => 'Matam',
            'modif.siege_nouveau_ville'    => 'Conakry',
        ];
    }

    /** Un vrai .docx : PhpWord dézippe l'OOXML, un fichier factice ne suffirait pas. */
    private function docx(string $contenu = 'Statuts de la société.'): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'gab') . '.docx';
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->addSection()->addText($contenu);
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($chemin);

        return new UploadedFile($chemin, 'gabarit.docx', null, null, true);
    }

    /** Attache un document `.docx` généré à un dossier. */
    private function documentDocx(Dossier $dossier, string $categorie, string $contenu): void
    {
        $doc = $dossier->documents()->create([
            'nom' => ucfirst($categorie), 'categorie' => $categorie, 'statut' => 'edite',
        ]);
        $doc->nouvelleVersion($this->docx($contenu), 'documents/' . $dossier->reference);
    }

    // ── Les statuts en vigueur, et leur ordre de préséance ───────────────────

    public function test_les_statuts_du_dossier_de_constitution_servent_de_gabarit(): void
    {
        $constitution = $this->dossier('SOC-SARLU');
        $societe = Societe::create(['denomination' => 'SOC TEST', 'dossier_id' => $constitution->id]);
        $this->documentDocx($constitution, 'acte_principal', 'Statuts d’origine de SOC TEST');

        $gabarit = $societe->fresh()->gabaritStatutsEnVigueur();

        $this->assertNotNull($gabarit);
        $this->assertStringContainsString($constitution->reference, $gabarit['origine']);
    }

    public function test_les_statuts_maj_dune_modification_effective_primeent_sur_ceux_dorigine(): void
    {
        // Après une première modification, les statuts en vigueur sont ceux qu'elle a produits.
        $constitution = $this->dossier('SOC-SARLU');
        $societe = Societe::create(['denomination' => 'SOC TEST', 'dossier_id' => $constitution->id]);
        $this->documentDocx($constitution, 'acte_principal', 'Statuts d’origine');

        $modif = $this->dossier('SOC-MOD', $this->donneesModification(), $societe, EtapeDossier::Expedition);
        $this->documentDocx($modif, 'statuts_maj', 'Statuts après première modification');

        $gabarit = $societe->fresh()->gabaritStatutsEnVigueur();

        $this->assertStringContainsString($modif->reference, $gabarit['origine']);
        $this->assertStringNotContainsString($constitution->reference, $gabarit['origine']);
    }

    public function test_une_modification_non_effective_nest_pas_retenue(): void
    {
        // Un dossier de modification abandonné ne doit pas devenir la référence des suivants :
        // seuil identique à celui de SocieteMutationService (Expédition).
        $constitution = $this->dossier('SOC-SARLU');
        $societe = Societe::create(['denomination' => 'SOC TEST', 'dossier_id' => $constitution->id]);
        $this->documentDocx($constitution, 'acte_principal', 'Statuts d’origine');

        $abandonne = $this->dossier('SOC-MOD', $this->donneesModification(), $societe, EtapeDossier::Initialisation);
        $this->documentDocx($abandonne, 'statuts_maj', 'Brouillon jamais abouti');

        $gabarit = $societe->fresh()->gabaritStatutsEnVigueur();

        $this->assertStringContainsString($constitution->reference, $gabarit['origine']);
    }

    public function test_les_statuts_deposes_au_registre_primeent_sur_tout(): void
    {
        $societe = Societe::create(['denomination' => 'Faya SARL']);
        $piece = $societe->piecesConstitutives()->create([
            'nom' => 'Statuts en vigueur', 'categorie' => 'statuts', 'est_requis' => true,
        ]);
        $piece->nouvelleVersion($this->docx('Statuts apportés par le client'), $societe->repertoirePieces());

        $this->assertStringContainsString('déposés au registre', $societe->fresh()->gabaritStatutsEnVigueur()['origine']);
    }

    public function test_lorigine_du_gabarit_est_journalisee(): void
    {
        $constitution = $this->dossier('SOC-SARLU');
        $societe = Societe::create(['denomination' => 'SOC TEST', 'dossier_id' => $constitution->id]);
        $this->documentDocx($constitution, 'acte_principal', 'Statuts sans balise');

        $modif = $this->dossier('SOC-MOD', $this->donneesModification(), $societe);
        app(ActesGeneratorService::class)->genererActesDepuisModeles($modif);

        $entree = $modif->journal()->where('type', 'generation')->first();

        $this->assertNotNull($entree, 'Le rédacteur doit savoir de quel texte il part.');
        $this->assertStringContainsString($constitution->reference, $entree->action);
    }

    // ── Un modèle pour plusieurs types d'actes ───────────────────────────────

    private function modele(string $code, string $typeDocument, array $autresTypes = []): ModeleActe
    {
        $modele = ModeleActe::create([
            'nom'            => 'Modèle ' . $typeDocument,
            'type_document'  => $typeDocument,
            'chemin_fichier' => 'modeles/inexistant.docx',
            'version'        => '1.0',
            'est_actif'      => true,
        ]);

        $modele->typesActes()->sync([
            $this->typeActe($code)->id,
            ...array_map(fn (string $c) => $this->typeActe($c)->id, $autresTypes),
        ]);

        return $modele->fresh();
    }

    public function test_un_modele_partage_sert_les_deux_types(): void
    {
        $modele = $this->modele('SOC-SARLU', 'dnsv', ['SOC-MOD']);

        $this->assertTrue($modele->applicablePour($this->typeActe('SOC-SARLU')));
        $this->assertTrue($modele->applicablePour($this->typeActe('SOC-MOD')));
    }

    public function test_un_modele_non_partage_ne_sert_que_son_type(): void
    {
        $modele = $this->modele('SOC-SARLU', 'dnsv');

        $this->assertTrue($modele->applicablePour($this->typeActe('SOC-SARLU')));
        $this->assertFalse($modele->applicablePour($this->typeActe('SOC-MOD')));
    }

    public function test_applicable_tous_couvre_nimporte_quel_type(): void
    {
        $modele = $this->modele('SOC-SARLU', 'page_garde');
        $modele->update(['applicable_tous' => true]);
        $modele->typesActes()->detach();

        $this->assertTrue($modele->fresh()->applicablePour($this->typeActe('SOC-MOD')));
    }

    public function test_le_scope_retient_les_modeles_partages(): void
    {
        $this->modele('SOC-SARLU', 'dnsv', ['SOC-MOD']);
        $this->modele('SOC-SARLU', 'attestation');

        $retenus = ModeleActe::pourTypeActe($this->typeActe('SOC-MOD')->id)->pluck('type_document');

        $this->assertContains('dnsv', $retenus);
        $this->assertNotContains('attestation', $retenus);
    }

    // ── Le chargement d'un modèle de modification ────────────────────────────

    public function test_un_modele_de_modification_peut_etre_cree(): void
    {
        // Les 4 slugs de la modification manquaient à la liste blanche : toute création partait
        // en 422, et l'étude ne pouvait pas fournir son gabarit de procès-verbal.
        $this->actingAs($this->utilisateur())
            ->post('/modeles', [
                'nom'           => "Procès-verbal d'AGE",
                'type_acte_ids' => [$this->typeActe('SOC-MOD')->id],
                'type_document' => 'pv_modification',
                'version'       => '1.0',
                'fichier'       => $this->docx('PV'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('modeles_actes', ['type_document' => 'pv_modification']);
    }

    public function test_les_quatre_slugs_de_modification_sont_autorises(): void
    {
        foreach (['acte_cession', 'pv_modification', 'statuts_maj', 'declaration_rccm'] as $slug) {
            $this->assertStringContainsString($slug, ModeleActe::reglesTypeDocument());
        }
    }

    public function test_creer_un_modele_sans_aucun_type_est_refuse(): void
    {
        // `type_acte_id` a disparu le 2026-08-11 : il n'y a plus de « type d'origine » sur lequel se
        // replier, et deviner un rattachement rendrait le gabarit applicable à un type que personne
        // n'a coché. Un modèle rattaché à rien ne serait généré nulle part, en silence — il est donc
        // refusé à la saisie.
        $this->actingAs($this->utilisateur())
            ->post('/modeles', [
                'nom'           => 'Statuts SARLU',
                'type_document' => 'acte_principal',
                'version'       => '1.0',
                'fichier'       => $this->docx(),
            ])
            ->assertSessionHasErrors('type_acte_ids');

        $this->assertDatabaseMissing('modeles_actes', ['nom' => 'Statuts SARLU']);
    }

    public function test_applicable_a_tous_dispense_de_cocher_un_type(): void
    {
        // Le pendant : « applicable à tous les types d'actes » est une déclaration explicite, elle
        // satisfait donc l'obligation.
        $this->actingAs($this->utilisateur())
            ->post('/modeles', [
                'nom'             => 'Page de garde universelle',
                'type_document'   => 'page_garde',
                'version'         => '1.0',
                'applicable_tous' => true,
                'fichier'         => $this->docx(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            ModeleActe::where('nom', 'Page de garde universelle')->first()
                ->applicablePour($this->typeActe('SOC-SARLU')),
        );
    }

    // ── Le panneau dit ce qui est produisible ────────────────────────────────

    private function documents(Dossier $dossier): array
    {
        return collect(app(ReglesSocieteService::class)->modificationStatutaire($dossier)['documents'])
            ->keyBy('slug')
            ->all();
    }

    public function test_un_acte_sans_modele_est_signale_indisponible(): void
    {
        $dossier = $this->dossier('SOC-MOD', $this->donneesModification());

        $documents = $this->documents($dossier);

        $this->assertFalse($documents['pv_modification']['disponible']);
    }

    public function test_un_acte_avec_modele_actif_est_disponible(): void
    {
        $this->modele('SOC-MOD', 'pv_modification');
        $dossier = $this->dossier('SOC-MOD', $this->donneesModification());

        $this->assertTrue($this->documents($dossier)['pv_modification']['disponible']);
    }

    public function test_les_statuts_maj_sont_disponibles_par_heritage_sans_modele(): void
    {
        $constitution = $this->dossier('SOC-SARLU');
        $societe = Societe::create(['denomination' => 'SOC TEST', 'dossier_id' => $constitution->id]);
        $this->documentDocx($constitution, 'acte_principal', 'Statuts');

        $dossier = $this->dossier('SOC-MOD', [
            ...$this->donneesModification(),
            'modif.types' => ['Augmentation de capital'],
            'modif.capital_augmentation_montant' => 10_000_000,
        ], $societe);

        $documents = $this->documents($dossier);

        $this->assertTrue($documents['statuts_maj']['disponible']);
        $this->assertStringContainsString($constitution->reference, $documents['statuts_maj']['source']);
    }

    // ── Le dépôt manuel débloque l'Édition ───────────────────────────────────

    public function test_un_acte_depose_a_la_main_satisfait_le_prerequis_de_ledition(): void
    {
        // Sans modèle actif, « Générer » ne produit rien et l'étape exige pourtant un acte.
        $dossier = $this->dossier('SOC-MOD', $this->donneesModification(), null, EtapeDossier::Edition);

        $this->actingAs($this->utilisateur())
            ->post("/dossiers/{$dossier->reference}/documents", [
                'nom'       => "Procès-verbal de l'assemblée",
                'categorie' => 'acte_principal',
                'fichier'   => $this->docx('PV signé'),
            ])
            ->assertRedirect();

        $documents = $dossier->fresh()->documents()->where('categorie', '!=', 'accord_client')->get();

        $this->assertCount(1, $documents);
        $this->assertNotNull($documents->first()->versionActuelle);
    }

    // ── Non-régression ───────────────────────────────────────────────────────

    public function test_une_constitution_produit_toujours_ses_actes(): void
    {
        // Le passage au pivot ne doit rien changer pour les dossiers existants.
        Storage::fake('local');
        $modele = $this->modele('SOC-SARLU', 'acte_principal');
        Storage::disk('local')->put($modele->chemin_fichier, 'x');

        $dossier = $this->dossier('SOC-SARLU', ['soc.denomination' => 'Faya SARLU']);

        $this->assertSame(1, app(ActesGeneratorService::class)->genererActesDepuisModeles($dossier));
    }
}
