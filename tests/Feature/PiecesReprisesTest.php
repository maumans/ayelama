<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reprise d'une pièce déjà fournie par la même personne dans un autre dossier (2026-08-11).
 *
 * Signalé à l'usage sur `SOC-2026-0013` : le dossier réclamait CNI, certificat de résidence et
 * deuxième photo à un souscripteur qui les avait déjà toutes déposées lors de la constitution de la
 * même société. Une pièce d'identité est un attribut de la **personne**, pas du dossier.
 */
class PiecesReprisesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function utilisateur(RoleUtilisateur $role): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => $role->value]);

        return $user->fresh();
    }

    private function dossier(string $code = 'SOC-MOD'): Dossier
    {
        $type = TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => 'Type ' . $code, 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );

        return Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $type->id,
            'etape'        => EtapeDossier::Initialisation,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test pour la reprise de pièces',
        ]);
    }

    private function partie(Dossier $dossier, ?Client $client, string $role = 'associe_unique'): Partie
    {
        return $dossier->parties()->create([
            'nom'       => 'Thierno DIALLO',
            'role'      => $role,
            'client_id' => $client?->id,
        ]);
    }

    private function client(): Client
    {
        return Client::create([
            'type'        => 'physique',
            'nom_famille' => 'DIALLO',
            'prenoms'     => 'Thierno',
        ]);
    }

    /** Dépose une pièce requise sur une partie. */
    private function deposer(Partie $partie, string $categorie, string $contenu = 'scan'): void
    {
        $piece = $partie->pieces()->create([
            'nom'        => $partie->piecesRequisesDefinition()[$categorie],
            'categorie'  => $categorie,
            'est_requis' => true,
        ]);

        $piece->nouvelleVersion(
            UploadedFile::fake()->createWithContent($categorie . '.pdf', $contenu),
            'parties/' . $partie->dossier->reference,
        );
    }

    // ── Détection ────────────────────────────────────────────────────────────

    public function test_une_piece_du_meme_client_dans_un_autre_dossier_est_proposee(): void
    {
        $client = $this->client();

        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        $this->deposer($ancien, 'cni');

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');

        $reprenables = $nouveau->piecesReprenables();

        $this->assertArrayHasKey('cni', $reprenables);
        $this->assertSame($ancien->dossier->reference, $reprenables['cni']['dossier']);
        // La date accompagne la proposition : une pièce d'identité a une durée de validité, et
        // reprendre un scan ancien sans le voir serait pire que de le redemander.
        $this->assertNotNull($reprenables['cni']['date']);
    }

    public function test_sans_fiche_client_aucune_reprise_nest_proposee(): void
    {
        // Deux homonymes ne sont pas la même personne : `client_id` est le seul lien fiable.
        $ancien = $this->partie($this->dossier('SOC-SARLU'), $this->client());
        $this->deposer($ancien, 'cni');

        $sansFiche = $this->partie($this->dossier(), null, 'souscripteur');

        $this->assertSame([], $sansFiche->piecesReprenables());
    }

    public function test_une_piece_dun_autre_client_nest_jamais_proposee(): void
    {
        $ancien = $this->partie($this->dossier('SOC-SARLU'), $this->client());
        $this->deposer($ancien, 'cni');

        $autre = Client::create(['type' => 'physique', 'nom_famille' => 'CAMARA', 'prenoms' => 'Mariama']);
        $nouveau = $this->partie($this->dossier(), $autre, 'souscripteur');

        $this->assertSame([], $nouveau->piecesReprenables());
    }

    public function test_une_piece_deja_fournie_ici_nest_pas_proposee(): void
    {
        $client = $this->client();

        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        $this->deposer($ancien, 'cni');

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');
        $this->deposer($nouveau, 'cni');

        $this->assertArrayNotHasKey('cni', $nouveau->fresh()->load('pieces.versionActuelle')->piecesReprenables());
    }

    public function test_la_checklist_porte_la_reprise(): void
    {
        $client = $this->client();
        $this->deposer($this->partie($this->dossier('SOC-SARLU'), $client), 'cni');

        $checklist = collect($this->partie($this->dossier(), $client, 'souscripteur')->piecesChecklist());

        $this->assertNotNull($checklist->firstWhere('categorie', 'cni')['reprise']);
        $this->assertNull($checklist->firstWhere('categorie', 'certificat_residence')['reprise']);
    }

    // ── La reprise copie, et ne référence pas ────────────────────────────────

    public function test_la_reprise_copie_le_fichier_et_ne_le_partage_pas(): void
    {
        // **Le test qui compte.** Deux DocumentFichier pointant sur le même chemin seraient un
        // piège : `supprimerAvecFichiers()` effacerait le fichier sous les pieds de l'autre
        // dossier. Or `nouvelleVersion()` accepte une chaîne (chemin déjà stocké), ce qui rendrait
        // l'erreur facile à commettre.
        $client = $this->client();

        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        $this->deposer($ancien, 'cni', 'contenu-original');
        $source = $ancien->pieces()->where('categorie', 'cni')->first();

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');
        $copie   = $nouveau->reprendrePiece('cni', $source);

        $cheminSource = $source->versionActuelle->chemin_fichier;
        $cheminCopie  = $copie->versionActuelle->chemin_fichier;

        $this->assertNotSame($cheminSource, $cheminCopie, 'La reprise doit copier, jamais référencer.');
        $this->assertSame('contenu-original', Storage::disk('public')->get($cheminCopie));

        // Supprimer l'original ne doit pas emporter la copie.
        $source->supprimerAvecFichiers();

        $this->assertTrue(Storage::disk('public')->exists($cheminCopie));
        $this->assertSame('contenu-original', Storage::disk('public')->get($cheminCopie));
    }

    public function test_la_version_reprise_est_tracee_comme_telle(): void
    {
        $client = $this->client();
        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        $this->deposer($ancien, 'cni');

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');
        $copie = $nouveau->reprendrePiece('cni', $ancien->pieces()->first());

        // L'historique distingue déjà upload / genere / restauration / signe_cachete : il doit dire
        // d'où vient chaque fichier.
        $this->assertSame('reprise', $copie->versionActuelle->source);
        $this->assertTrue($copie->est_fourni);
    }

    // ── Routes ───────────────────────────────────────────────────────────────

    public function test_un_clerc_peut_reprendre_une_piece(): void
    {
        $client = $this->client();
        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        $this->deposer($ancien, 'cni');

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');
        $source  = $ancien->pieces()->first();

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->post("/parties/{$nouveau->id}/pieces/cni/reprendre", ['source_id' => $source->id])
            ->assertRedirect();

        $this->assertTrue($nouveau->fresh()->pieces()->where('categorie', 'cni')->exists());
    }

    public function test_un_source_id_etranger_est_refuse(): void
    {
        // Sans ce contrôle, la route permettrait de copier n'importe quelle pièce de n'importe
        // quel dossier vers le sien.
        $autre = Client::create(['type' => 'physique', 'nom_famille' => 'CAMARA', 'prenoms' => 'M']);
        $ancien = $this->partie($this->dossier('SOC-SARLU'), $autre);
        $this->deposer($ancien, 'cni');

        $nouveau = $this->partie($this->dossier(), $this->client(), 'souscripteur');

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->post("/parties/{$nouveau->id}/pieces/cni/reprendre", ['source_id' => $ancien->pieces()->first()->id])
            ->assertForbidden();

        $this->assertSame(0, $nouveau->fresh()->pieces()->count());
    }

    // ── Le blocage de l'Initialisation ───────────────────────────────────────

    public function test_les_pieces_sont_televersables_des_linitialisation(): void
    {
        // **Blocage complet constaté sur `SOC-2026-0013`** : le dépôt passait par
        // `genererDocuments`, qui exclut l'Initialisation — or c'est précisément l'étape qui exige
        // ces pièces pour être franchie. Le dossier réclamait donc des pièces que personne ne
        // pouvait y déposer, et l'interface n'affichait même pas le bouton.
        $dossier = $this->dossier();
        $partie  = $this->partie($dossier, $this->client(), 'souscripteur');
        $admin   = $this->utilisateur(RoleUtilisateur::Administrateur);

        $this->assertSame(EtapeDossier::Initialisation, $dossier->etape);
        $this->assertFalse($admin->can('genererDocuments', $dossier), 'Prémisse du bug.');
        $this->assertTrue($admin->can('gererPieces', $dossier));

        $this->actingAs($admin)
            ->post("/parties/{$partie->id}/pieces/cni/televerser", [
                'fichier' => UploadedFile::fake()->create('cni.pdf', 50),
            ])
            ->assertRedirect();

        $this->assertTrue($partie->fresh()->pieces()->where('categorie', 'cni')->exists());
    }

    public function test_les_pieces_restent_televersables_a_ledition(): void
    {
        // Une pièce peut manquer et être ajoutée après un renvoi en correction.
        $dossier = $this->dossier();
        $dossier->update(['etape' => EtapeDossier::Edition]);
        $partie = $this->partie($dossier, $this->client(), 'souscripteur');

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->post("/parties/{$partie->id}/pieces/cni/televerser", [
                'fichier' => UploadedFile::fake()->create('cni.pdf', 50),
            ])
            ->assertRedirect();

        $this->assertTrue($partie->fresh()->pieces()->where('categorie', 'cni')->exists());
    }

    public function test_les_pieces_ne_sont_plus_televersables_apres_la_certification(): void
    {
        // Non-régression : ajouter une pièce après la certification changerait le dossier validé.
        $dossier = $this->dossier();
        $dossier->update(['etape' => EtapeDossier::Formalites]);
        $partie = $this->partie($dossier, $this->client(), 'souscripteur');

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->post("/parties/{$partie->id}/pieces/cni/televerser", [
                'fichier' => UploadedFile::fake()->create('cni.pdf', 50),
            ])
            ->assertForbidden();
    }

    // ── L'accord attendu dépend du type de dossier ───────────────────────────

    public function test_une_modification_attend_la_decision_dassemblee(): void
    {
        $attendu = $this->dossier('SOC-MOD')->pieceAccordAttendue();

        $this->assertSame("Décision d'assemblée des associés", $attendu['nom']);
        // Rien à imprimer : c'est le client qui apporte la décision de son assemblée.
        $this->assertFalse($attendu['imprimable']);
        // La catégorie reste le créneau technique commun — voir Dossier::pieceAccordAttendue().
        $this->assertSame('accord_client', $attendu['categorie']);
    }

    public function test_une_constitution_garde_la_fiche_signee(): void
    {
        $attendu = $this->dossier('SOC-SARLU')->pieceAccordAttendue();

        $this->assertSame('Accord client — questionnaire signé', $attendu['nom']);
        $this->assertTrue($attendu['imprimable']);
    }

    public function test_le_blocage_dune_modification_nomme_la_decision(): void
    {
        $dossier = $this->dossier('SOC-MOD');
        $dossier->update([
            'notaire_id'  => $this->utilisateur(RoleUtilisateur::Notaire)->id,
            'reviseur_id' => $this->utilisateur(RoleUtilisateur::Reviseur)->id,
        ]);

        try {
            app(\App\Services\DossierStepService::class)->avancer($dossier->fresh(), $this->utilisateur(RoleUtilisateur::Administrateur));
            $this->fail("L'avancement aurait dû être refusé.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = $e->errors()['accord_client'][0] ?? '';
            $this->assertStringContainsString("Décision d'assemblée", $message);
            $this->assertStringNotContainsString('questionnaire signé', $message);
        }
    }

    public function test_reprendre_tout_ne_touche_que_les_categories_manquantes(): void
    {
        $client = $this->client();

        $ancien = $this->partie($this->dossier('SOC-SARLU'), $client);
        foreach (['cni', 'certificat_residence', 'photo_secondaire'] as $categorie) {
            $this->deposer($ancien, $categorie);
        }

        $nouveau = $this->partie($this->dossier(), $client, 'souscripteur');
        $this->deposer($nouveau, 'cni', 'deja-scanne-ici');

        $this->actingAs($this->utilisateur(RoleUtilisateur::Administrateur))
            ->post("/parties/{$nouveau->id}/pieces/reprendre-tout")
            ->assertRedirect();

        $nouveau = $nouveau->fresh()->load('pieces.versionActuelle');

        $this->assertSame(3, $nouveau->pieces()->count());
        // La pièce déjà présente ici n'a pas été écrasée par la reprise.
        $this->assertSame(
            'deja-scanne-ici',
            Storage::disk('public')->get($nouveau->pieces->firstWhere('categorie', 'cni')->versionActuelle->chemin_fichier),
        );
        $this->assertSame([], $nouveau->piecesReprenables());
    }
}
