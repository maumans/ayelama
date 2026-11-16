<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\DocumentFichier;
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
 * Pièces des personnes à la création d'un dossier — retours R06 et R08 du 2026-11-16.
 *
 * R06, « les pièces des personnes ajoutées, parfois ça passe pas ». Trois causes établies :
 *   1. la règle servie à l'écran ignorait le motif de représentation — la procuration d'une
 *      personne représentée n'était jamais demandée, le serveur l'exigeait ensuite ;
 *   2. aucun écran ne proposait les pièces du représentant, que le payload lisait pourtant ;
 *   3. retirer une ligne de bloc décalait les personnes, pas leurs pièces.
 * Les deux premières sont tenues ici et par `PiecesRequisesParRoleTest` ; la troisième est du
 * JavaScript pur (`lib/piecesStagees.js`), vérifiée en rejouant le payload dans la page.
 *
 * R08, « n'oublie pas les fichiers liés aux clients existants » : la reprise d'office.
 */
class PiecesCreationDossierTest extends TestCase
{
    use RefreshDatabase;

    private User $clerc;
    private User $notaire;
    private TypeActe $typeActe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->clerc = User::factory()->create(['actif' => true]);
        $this->clerc->syncRoles([RoleUtilisateur::Administrateur]);
        $this->notaire = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $this->notaire->id, 'role' => RoleUtilisateur::Notaire->value]);

        $this->typeActe = TypeActe::firstOrCreate(
            ['code' => 'SOC-SARLU'],
            ['label' => 'Constitution SARLU', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );
    }

    private function client(string $nom = 'Mamadou BAH'): Client
    {
        return Client::create(['type' => 'physique', 'civilite' => 'M.', 'prenom_nom' => $nom]);
    }

    /** Un dossier antérieur où ce client a déjà déposé sa CNI. */
    private function cniDejaFournie(Client $client, string $reference = 'SOC-2026-0901'): DocumentFichier
    {
        $dossier = Dossier::create([
            'reference' => $reference, 'type_acte_id' => $this->typeActe->id, 'etape' => EtapeDossier::Edition,
            'redacteur_id' => $this->clerc->id, 'notaire_id' => $this->notaire->id, 'objet' => 'Dossier antérieur',
        ]);
        $partie = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $client->id, 'nom' => $client->prenom_nom,
            'role' => 'associe_unique', 'type_personne' => 'physique',
        ]);
        $piece = $partie->pieces()->create(['nom' => 'CNI', 'categorie' => 'cni', 'est_requis' => true]);
        $piece->nouvelleVersion(UploadedFile::fake()->create('cni-ancienne.pdf', 20), 'parties/' . $reference);

        return $piece->fresh();
    }

    /** @param array<int, array<string, mixed>> $parties */
    private function creer(array $parties): Dossier
    {
        $this->actingAs($this->clerc)->post('/dossiers', [
            'type_acte_id' => $this->typeActe->id,
            'objet'        => 'Constitution SARLU — pièces à la création',
            'notaire_id'   => $this->notaire->id,
            'donnees'      => ['soc.denomination' => 'Pièces SARLU'],
            'parties'      => $parties,
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Dossier::latest('id')->firstOrFail();
    }

    // ── R08 : reprise d'office ────────────────────────────────────────────────

    public function test_la_cni_deja_fournie_par_un_client_existant_est_reprise_a_la_creation(): void
    {
        $client = $this->client();
        $this->cniDejaFournie($client);

        $dossier = $this->creer([[
            'nom' => 'Mamadou BAH', 'role' => 'associe_unique', 'client_id' => $client->id,
            'type_personne' => 'physique', 'donnees_prefixe' => 'pp',
        ]]);

        $cni = $dossier->parties()->sole()->pieces()->where('categorie', 'cni')->sole();
        $this->assertSame('reprise', $cni->versionActuelle->source);
        // Copie physique, pas une référence partagée : supprimer l'un ne doit pas vider l'autre.
        Storage::disk('public')->assertExists($cni->versionActuelle->chemin_fichier);
        $this->assertStringContainsString($dossier->reference, $cni->versionActuelle->chemin_fichier);
    }

    public function test_un_fichier_choisi_a_lecran_lemporte_sur_la_reprise(): void
    {
        $client = $this->client();
        $this->cniDejaFournie($client);

        $dossier = $this->creer([[
            'nom' => 'Mamadou BAH', 'role' => 'associe_unique', 'client_id' => $client->id,
            'type_personne' => 'physique', 'donnees_prefixe' => 'pp',
            'pieces' => ['cni' => UploadedFile::fake()->create('cni-neuve.pdf', 20)],
        ]]);

        $cni = $dossier->parties()->sole()->pieces()->where('categorie', 'cni')->sole();
        $this->assertSame(1, $cni->versions()->count());
        $this->assertSame('upload', $cni->versionActuelle->source);
    }

    public function test_une_personne_sans_fiche_ne_reprend_rien(): void
    {
        // Rapprochement sur `client_id` exclusivement : deux homonymes ne sont pas la même personne.
        $this->cniDejaFournie($this->client());

        $dossier = $this->creer([[
            'nom' => 'Mamadou BAH', 'role' => 'associe_unique', 'type_personne' => 'physique', 'donnees_prefixe' => 'pp',
        ]]);

        $this->assertSame(0, $dossier->parties()->sole()->pieces()->count());
    }

    public function test_lecran_peut_annoncer_ce_qui_sera_repris(): void
    {
        $client = $this->client();
        $this->cniDejaFournie($client, 'SOC-2026-0902');

        $this->actingAs($this->clerc)
            ->getJson("/clients/{$client->id}/pieces-reprenables?role=associe_unique&type_personne=physique")
            ->assertOk()
            ->assertJsonPath('cni.dossier', 'SOC-2026-0902')
            ->assertJsonMissingPath('cni.chemin_fichier');
    }

    // ── R06 : la procuration d'une personne représentée ──────────────────────

    public function test_la_procuration_nest_jamais_reprise_dun_autre_dossier(): void
    {
        // Elle vaut pour un acte déterminé. L'écran la demande (motif servi), le serveur ne la
        // copie jamais depuis ailleurs.
        $client = $this->client();

        $this->actingAs($this->clerc)
            ->getJson("/clients/{$client->id}/pieces-reprenables?role=associe_unique&motif=procuration")
            ->assertOk()
            ->assertJsonMissingPath('procuration');
    }
}
