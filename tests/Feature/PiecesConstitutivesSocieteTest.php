<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\RubriqueCloture;
use App\Models\ClotureVerification;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\ModeleActe;
use App\Models\Questionnaire;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use App\Services\ActesGeneratorService;
use App\Services\InventaireClotureService;
use App\Services\ReglesSocieteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Dossier constitutif d'une société que l'étude n'a pas constituée (2026-08-11).
 *
 * Sans lui, un dossier de modification sur une telle société part de zéro documentaire — et
 * produirait des « statuts mis à jour » depuis un gabarit générique dont ni les articles ni la
 * numérotation ne correspondent aux statuts réels.
 *
 * Un test par règle, nommé d'après elle — convention posée par `ReglesSocieteTest`.
 */
class PiecesConstitutivesSocieteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function utilisateur(RoleUtilisateur ...$roles): User
    {
        $user = User::factory()->create(['actif' => true]);
        foreach ($roles as $role) {
            UserRole::create(['user_id' => $user->id, 'role' => $role->value]);
        }

        return $user->fresh();
    }

    private function typeActe(string $code = 'SOC-MOD'): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    /** Société **externe** : aucun dossier d'origine, donc dossier constitutif à fournir. */
    private function societeExterne(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination'     => 'Faya Distribution SARL',
            'forme'            => 'SARL',
            'rccm_numero'      => 'GN-CON-2015-B-0042',
            'capital_chiffres' => 50_000_000,
            'nombre_parts'     => 100,
            'siege_quartier'   => 'Almamya',
            'siege_commune'    => 'Kaloum',
            'siege_ville'      => 'Conakry',
        ], $attributs));
    }

    private function dossier(?Societe $societe, array $donnees = [], string $code = 'SOC-MOD'): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe($code)->id,
            'societe_id'   => $societe?->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Modification statutaire de test sur société externe',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    private function donneesValides(array $types = ['Transfert du siège social']): array
    {
        return [
            'modif.types'                  => $types,
            'soc.denomination'             => 'Faya Distribution SARL',
            'soc.forme'                    => 'SARL',
            'soc.capital_chiffres'         => 50_000_000,
            'ag.date'                      => '01/08/2026',
            'modif.siege_nouveau_quartier'  => 'Matam',
            'modif.siege_nouveau_commune'   => 'Matam',
            'modif.siege_nouveau_ville'     => 'Conakry',
        ];
    }

    /** Verse une pièce constitutive directement, sans passer par HTTP. */
    private function verser(Societe $societe, string $categorie, string $nomFichier = 'statuts.docx'): DocumentFichier
    {
        $definition = Societe::PIECES_CONSTITUTIVES[$categorie];

        $piece = $societe->piecesConstitutives()->create([
            'nom'        => $definition['label'],
            'categorie'  => $categorie,
            'est_requis' => $definition['requis'],
        ]);

        $piece->nouvelleVersion(
            UploadedFile::fake()->create($nomFichier, 20),
            $societe->repertoirePieces(),
        );

        return $piece->fresh();
    }

    // ── Le pivot : qui doit fournir quoi ─────────────────────────────────────

    public function test_une_societe_constituee_par_letude_nexige_aucune_piece(): void
    {
        // Son dossier d'origine contient déjà statuts, PV, RCCM et DNSV : `dossier_id` en est la
        // preuve, et redemander ces pièces serait absurde.
        $constitution = $this->dossier(null, [], 'SOC-SARLU');
        $societe = $this->societeExterne(['dossier_id' => $constitution->id]);

        $this->assertFalse($societe->exigePiecesConstitutives());
    }

    public function test_une_societe_externe_exige_son_dossier_constitutif(): void
    {
        $this->assertTrue($this->societeExterne()->exigePiecesConstitutives());
    }

    public function test_la_checklist_distingue_les_pieces_requises_des_facultatives(): void
    {
        $societe = $this->societeExterne();

        $checklist = collect($societe->piecesConstitutivesChecklist());

        $this->assertCount(7, $checklist);
        $this->assertSame(
            ['statuts', 'rccm'],
            $checklist->where('requis', true)->pluck('categorie')->all(),
        );
        $this->assertTrue($checklist->where('requis', false)->pluck('categorie')->contains('dnsv'));
    }

    public function test_verser_une_piece_la_marque_fournie(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts');

        $statuts = collect($societe->fresh()->piecesConstitutivesChecklist())->firstWhere('categorie', 'statuts');

        $this->assertTrue($statuts['aUnFichier']);
        $this->assertSame(1, $statuts['version']);
    }

    public function test_reverser_une_piece_cree_une_version_sans_dupliquer_la_ligne(): void
    {
        $societe = $this->societeExterne();
        $piece = $this->verser($societe, 'statuts');
        $piece->nouvelleVersion(UploadedFile::fake()->create('statuts-v2.docx', 20), $societe->repertoirePieces());

        $this->assertSame(1, $societe->fresh()->piecesConstitutives()->count(), 'Une seule ligne par catégorie.');
        $this->assertSame(2, $piece->fresh()->versionActuelle->numero);
    }

    // ── Règle bloquante ──────────────────────────────────────────────────────

    private function anomalies(Dossier $dossier): array
    {
        return app(ReglesSocieteService::class)->anomalies($dossier->fresh());
    }

    public function test_une_societe_externe_sans_statuts_bloque_lavancement(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'rccm', 'rccm.pdf');

        $anomalies = $this->anomalies($this->dossier($societe, $this->donneesValides()));

        $this->assertArrayHasKey('modif_pieces_societe', $anomalies);
        $this->assertStringContainsString('Statuts en vigueur', $anomalies['modif_pieces_societe'][0]);
    }

    public function test_une_societe_externe_sans_rccm_bloque_lavancement(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts');

        $anomalies = $this->anomalies($this->dossier($societe, $this->donneesValides()));

        $this->assertArrayHasKey('modif_pieces_societe', $anomalies);
        $this->assertStringContainsString('RCCM', $anomalies['modif_pieces_societe'][0]);
    }

    public function test_les_deux_pieces_requises_fournies_rendent_le_dossier_conforme(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts');
        $this->verser($societe, 'rccm', 'rccm.pdf');

        $this->assertSame([], $this->anomalies($this->dossier($societe, $this->donneesValides())));
    }

    public function test_une_piece_facultative_absente_ne_bloque_pas(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts');
        $this->verser($societe, 'rccm', 'rccm.pdf');

        // Ni NIF, ni actes antérieurs, ni DNSV : le dossier reste conforme.
        $this->assertArrayNotHasKey('modif_pieces_societe', $this->anomalies($this->dossier($societe, $this->donneesValides())));
    }

    public function test_une_societe_constituee_par_letude_nest_jamais_bloquee_sur_ces_pieces(): void
    {
        // Non-régression : les 11 fiches issues du backfill ont toutes un `dossier_id`, aucune ne
        // doit se voir réclamer de pièces.
        $constitution = $this->dossier(null, [], 'SOC-SARLU');
        $societe = $this->societeExterne(['dossier_id' => $constitution->id]);

        $this->assertArrayNotHasKey(
            'modif_pieces_societe',
            $this->anomalies($this->dossier($societe, $this->donneesValides())),
        );
    }

    // ── Autorisations ────────────────────────────────────────────────────────

    public function test_un_clerc_peut_verser_une_piece_constitutive(): void
    {
        $societe = $this->societeExterne();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->post("/societes/{$societe->id}/pieces/statuts", [
                'fichier' => UploadedFile::fake()->create('statuts.docx', 30),
            ])
            ->assertRedirect();

        $this->assertSame(1, $societe->fresh()->piecesConstitutives()->count());
    }

    public function test_un_formaliste_ne_peut_pas_verser_de_piece_constitutive(): void
    {
        // Ces pièces appartiennent au registre, dont la tenue est réservée aux rôles pouvant ouvrir
        // un dossier (SocietePolicy) — un formaliste ne verse pas les statuts d'une société.
        $societe = $this->societeExterne();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Formaliste))
            ->post("/societes/{$societe->id}/pieces/statuts", [
                'fichier' => UploadedFile::fake()->create('statuts.docx', 30),
            ])
            ->assertForbidden();

        $this->assertSame(0, $societe->fresh()->piecesConstitutives()->count());
    }

    public function test_une_categorie_inconnue_est_refusee(): void
    {
        $societe = $this->societeExterne();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Clerc))
            ->post("/societes/{$societe->id}/pieces/inventee", [
                'fichier' => UploadedFile::fake()->create('x.docx', 10),
            ])
            ->assertNotFound();
    }

    public function test_une_piece_de_registre_est_consultable_via_sujet_autorisation(): void
    {
        // `dossierGouvernant()` vaut null pour ces pièces : sans `sujetAutorisation()`, l'aperçu et
        // le téléchargement échouaient sur un `authorize(..., null)`.
        $piece = $this->verser($this->societeExterne(), 'statuts');
        $user = $this->utilisateur(RoleUtilisateur::Clerc);

        $this->assertNull($piece->dossierGouvernant());
        $this->assertInstanceOf(Societe::class, $piece->sujetAutorisation());

        $this->actingAs($user)->get("/documents/{$piece->id}/preview")->assertOk();
        $this->actingAs($user)->get("/documents/{$piece->id}/download")->assertOk();
        $this->actingAs($user)->getJson("/documents/{$piece->id}/versions")->assertOk();
    }

    public function test_les_actions_de_dossier_refusent_une_piece_de_registre(): void
    {
        // Ces actions autorisent sur `genererDocuments`, journalisent sur le dossier ou touchent au
        // circuit de signature : rien de tout cela n'a de sens pour une pièce du registre.
        $piece = $this->verser($this->societeExterne(), 'statuts');
        $user = $this->utilisateur(RoleUtilisateur::Administrateur);

        $this->actingAs($user)->delete("/documents/{$piece->id}")->assertForbidden();
        $this->actingAs($user)->post("/documents/{$piece->id}/regenerer")->assertForbidden();

        $this->assertDatabaseHas('document_fichiers', ['id' => $piece->id]);
    }

    // ── Les statuts déposés comme gabarit ────────────────────────────────────

    /** Un vrai .docx minimal : PhpWord dézippe l'OOXML, un fichier factice ne suffirait pas. */
    private function docxReel(string $contenu = 'Statuts de la société. Capital : ${soc.capital_chiffres} GNF.'): UploadedFile
    {
        $chemin = tempnam(sys_get_temp_dir(), 'statuts') . '.docx';

        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->addSection()->addText($contenu);
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($chemin);

        return new UploadedFile($chemin, 'statuts.docx', null, null, true);
    }

    private function versezStatutsDocx(Societe $societe, string $contenu = 'Statuts. Capital : ${soc.capital_chiffres} GNF.'): void
    {
        $piece = $societe->piecesConstitutives()->create([
            'nom' => 'Statuts en vigueur', 'categorie' => 'statuts', 'est_requis' => true,
        ]);
        $piece->nouvelleVersion($this->docxReel($contenu), $societe->repertoirePieces());
    }

    public function test_des_statuts_docx_deposes_servent_de_gabarit(): void
    {
        Storage::fake('public');

        $societe = $this->societeExterne();
        $this->versezStatutsDocx($societe);

        $this->assertNotNull($societe->fresh()->gabaritStatutsDocx());
    }

    public function test_des_statuts_pdf_ne_peuvent_pas_servir_de_gabarit(): void
    {
        // PhpWord\TemplateProcessor dézippe un OOXML : un PDF le ferait échouer. Le dépôt reste
        // ouvert au PDF — c'est souvent ce que le client apporte — mais on retombe sur le modèle.
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts', 'statuts.pdf');

        $this->assertNull($societe->fresh()->gabaritStatutsDocx());

        $checklist = collect($societe->fresh()->piecesConstitutivesChecklist())->firstWhere('categorie', 'statuts');
        $this->assertFalse($checklist['exploitableCommeGabarit']);
    }

    public function test_lacte_statuts_mis_a_jour_est_produit_meme_sans_modele_actif(): void
    {
        // Le modèle `statuts_maj` est seedé **inactif** faute de gabarit fourni à l'étude : sans la
        // seconde passe d'`actesAProduire()`, l'acte n'apparaîtrait jamais au dossier.
        Storage::fake('public');

        $societe = $this->societeExterne();
        $this->versezStatutsDocx($societe);

        ModeleActe::create([
            'type_acte_id'   => $this->typeActe()->id,
            'nom'            => 'Statuts mis à jour',
            'type_document'  => 'statuts_maj',
            'chemin_fichier' => 'modeles/societe/inexistant.docx',
            'version'        => '1.0',
            'est_actif'      => false,
        ]);

        $dossier = $this->dossier($societe, $this->donneesValides());

        $crees = app(ActesGeneratorService::class)->genererActesDepuisModeles($dossier);

        $this->assertSame(1, $crees);
        $document = $dossier->fresh()->documents()->firstWhere('categorie', 'statuts_maj');
        $this->assertNotNull($document, 'Les statuts mis à jour doivent être produits depuis le gabarit hérité.');
        $this->assertNotNull($document->versionActuelle);
    }

    public function test_le_gabarit_herite_est_journalise(): void
    {
        // Un gabarit sans balise produit une copie fidèle des statuts d'origine — c'est le résultat
        // voulu, mais le rédacteur doit le savoir, sans quoi il croirait les modifications déjà
        // reportées.
        Storage::fake('public');

        $societe = $this->societeExterne();
        $this->versezStatutsDocx($societe, 'Statuts sans aucune balise.');

        ModeleActe::create([
            'type_acte_id'   => $this->typeActe()->id,
            'nom'            => 'Statuts mis à jour',
            'type_document'  => 'statuts_maj',
            'chemin_fichier' => 'modeles/societe/inexistant.docx',
            'version'        => '1.0',
            'est_actif'      => false,
        ]);

        $dossier = $this->dossier($societe, $this->donneesValides());
        app(ActesGeneratorService::class)->genererActesDepuisModeles($dossier);

        $entree = $dossier->journal()->where('type', 'generation')->first();

        $this->assertNotNull($entree);
        $this->assertStringContainsString('statuts déposés', $entree->action);
        $this->assertStringContainsString('aucune balise', $entree->action);
        $this->assertTrue($entree->meta['gabarit_herite']);
    }

    public function test_regenerer_repart_du_meme_gabarit_herite(): void
    {
        // Sans le partage de `gabaritPour()`, « Régénérer » repartirait du modèle de l'étude et
        // écraserait des statuts reconstruits depuis ceux de la société.
        Storage::fake('public');

        $societe = $this->societeExterne();
        $this->versezStatutsDocx($societe);

        $dossier = $this->dossier($societe, $this->donneesValides());
        $document = $dossier->documents()->create([
            'nom' => 'Statuts mis à jour', 'categorie' => 'statuts_maj', 'statut' => 'a_editer',
        ]);

        // Aucun modèle actif : la régénération n'était possible qu'avec un modèle, elle l'est
        // désormais grâce au gabarit hérité.
        $this->assertTrue(app(ActesGeneratorService::class)->regenererDocument($document));
        $this->assertNotNull($document->fresh()->versionActuelle);
    }

    public function test_sans_statuts_deposes_le_modele_de_letude_est_utilise(): void
    {
        // Non-régression : le comportement d'origine reste celui par défaut.
        $societe = $this->societeExterne();
        $dossier = $this->dossier($societe, $this->donneesValides());

        $document = $dossier->documents()->create([
            'nom' => 'Statuts mis à jour', 'categorie' => 'statuts_maj', 'statut' => 'a_editer',
        ]);

        // Ni statuts déposés ni modèle actif → rien à faire, et surtout pas d'erreur.
        $this->assertFalse(app(ActesGeneratorService::class)->regenererDocument($document));
    }

    // ── Inventaire de clôture ────────────────────────────────────────────────

    public function test_les_pieces_de_societe_figurent_a_linventaire_avec_leur_origine(): void
    {
        $societe = $this->societeExterne();
        $this->verser($societe, 'statuts');
        $dossier = $this->dossier($societe, $this->donneesValides());

        $rubriques = app(InventaireClotureService::class)->pour($dossier->fresh());
        $rubrique = $rubriques->firstWhere('rubrique', RubriqueCloture::PiecesSociete->value);

        $this->assertNotNull($rubrique, 'La rubrique « Pièces de la société » doit exister.');
        $this->assertCount(1, $rubrique['pieces']);
        // Sans cette mention, une ligne « Statuts en vigueur » serait indistinguable d'un acte
        // produit par le dossier.
        $this->assertStringContainsString('Registre —', $rubrique['pieces'][0]['origine']);
    }

    public function test_la_rubrique_se_range_apres_les_pieces_des_parties(): void
    {
        $this->assertSame(4, RubriqueCloture::PiecesSociete->ordre());
        $this->assertLessThan(
            RubriqueCloture::PiecesSociete->ordre(),
            RubriqueCloture::PiecesParties->ordre(),
        );
    }

    public function test_une_meme_piece_est_verifiable_dans_deux_dossiers_de_la_societe(): void
    {
        // **Le bloqueur levé** : l'index unique portait sur `(verifiable_type, verifiable_id)`
        // seuls, prémisse valable tant qu'une pièce n'appartenait qu'à un dossier. Les pièces d'une
        // société étant partagées, vérifier les mêmes statuts dans une seconde modification
        // violait l'index — clôture impossible sur une erreur SQL.
        $societe = $this->societeExterne();
        $piece = $this->verser($societe, 'statuts');

        $premier = $this->dossier($societe, $this->donneesValides());
        $second  = $this->dossier($societe, $this->donneesValides());
        $user = $this->utilisateur(RoleUtilisateur::Clerc);

        foreach ([$premier, $second] as $dossier) {
            ClotureVerification::create([
                'dossier_id'      => $dossier->id,
                'verifiable_type' => DocumentFichier::class,
                'verifiable_id'   => $piece->id,
                'verifie_par_id'  => $user->id,
                'verifie_at'      => now(),
            ]);
        }

        $this->assertSame(2, ClotureVerification::where('verifiable_id', $piece->id)->count());
    }

    public function test_un_dossier_sans_societe_conserve_un_inventaire_inchange(): void
    {
        // Non-régression : la collecte des pièces de société ne doit rien changer ailleurs.
        $dossier = $this->dossier(null, [], 'SOC-SARLU');
        $dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);

        $rubriques = app(InventaireClotureService::class)->pour($dossier->fresh());

        $this->assertCount(0, $rubriques->firstWhere('rubrique', 'pieces_societe')['pieces']);
        $this->assertCount(1, $rubriques->firstWhere('rubrique', 'actes')['pieces']);
    }

    public function test_les_trois_rattachements_historiques_restent_classes_correctement(): void
    {
        // Non-régression du `match` exhaustif de RubriqueCloture, dont l'ajout d'un quatrième
        // documentable était précisément le garde-fou.
        $dossier = $this->dossier(null, [], 'SOC-SARLU');

        $acte = $dossier->documents()->create(['nom' => 'Statuts', 'categorie' => 'acte_principal']);
        $accord = $dossier->documents()->create(['nom' => 'Accord', 'categorie' => 'accord_client']);

        $this->assertSame(RubriqueCloture::Actes, RubriqueCloture::pourDocument($acte->fresh()));
        $this->assertSame(RubriqueCloture::AccordClient, RubriqueCloture::pourDocument($accord->fresh()));

        $piece = $this->verser($this->societeExterne(), 'statuts');
        $this->assertSame(RubriqueCloture::PiecesSociete, RubriqueCloture::pourDocument($piece->fresh()));
    }
}
