<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
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
 * Enregistrer le questionnaire ne doit **pas** détruire les personnes déjà au dossier.
 *
 * **Le défaut, signalé par l'étude le 2026-11-27.** Un dossier complet était parti jusqu'à la
 * certification ; renvoyé en correction, le questionnaire modifié, l'écran réclamait de nouveau
 * les trois pièces de l'associé unique — pourtant déjà déposées et déjà contrôlées au passage
 * Édition → Certification (`DossierStepService::verifierEdition()`).
 *
 * `updateQuestionnaire()` resynchronise les `Partie` rôle par rôle et apparie par `partie_id` :
 * un item qui n'en porte pas est **créé**, et toute Partie du rôle non référencée est ensuite
 * supprimée par `supprimerAvecPieces()` — qui emporte ses DocumentFichier, leurs versions et les
 * fichiers sur le disque. Or `buildPartiesPayload()` n'émettait `partie_id` que pour les **blocs
 * répétables** : toute **section scalaire** (associé unique, gérant, commissaire, vendeur…)
 * était donc détruite puis recréée à **chaque** enregistrement du questionnaire, avec ses pièces.
 *
 * Rien ne le signalait : le dossier restait cohérent, les personnes réapparaissaient — seules
 * les pièces manquaient, et le blocage ne reparle qu'en Initialisation et en Édition.
 *
 * Ce test rejoue exactement ce que la modale envoie (voir `buildPartiesPayload`) : le payload
 * **porte** désormais `partie_id`, et le serveur doit conserver l'identité de la Partie.
 */
class QuestionnairePreserveLesPartiesTest extends TestCase
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

    /** Un dossier dont l'associé unique a déposé ses trois pièces. */
    private function dossierComplet(): array
    {
        $client = Client::create([
            'type' => 'physique', 'civilite' => 'M.',
            'nom_famille' => 'DIALLO', 'prenoms' => 'Amadou Bailo',
        ]);

        $this->actingAs($this->clerc)->post('/dossiers', [
            'type_acte_id' => $this->typeActe->id,
            'objet'        => 'Constitution SARLU — les pièces doivent survivre',
            'notaire_id'   => $this->notaire->id,
            'donnees'      => ['soc.denomination' => 'Amadou et frères'],
            'parties'      => [[
                'nom'             => 'M. Amadou Bailo DIALLO',
                'role'            => 'associe_unique',
                'type_personne'   => 'physique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
                'pieces'          => [
                    'cni'                  => UploadedFile::fake()->create('cni.pdf', 20),
                    'certificat_residence' => UploadedFile::fake()->create('residence.pdf', 20),
                    'photo_secondaire'     => UploadedFile::fake()->create('photo.jpg', 20),
                ],
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $dossier = Dossier::latest('id')->firstOrFail();
        $partie  = $dossier->parties()->where('role', 'associe_unique')->sole();

        // Prérequis du test lui-même : sans pièces au départ, il ne prouverait rien.
        $this->assertSame(3, $partie->pieces()->count(), 'Les trois pièces doivent être déposées avant la modification.');
        $this->assertFalse($partie->aUnePieceBloquanteManquante());

        return [$dossier, $partie, $client];
    }

    /**
     * @param array<int, array<string, mixed>> $parties
     */
    private function enregistrerQuestionnaire(Dossier $dossier, array $parties, array $managedRoles = ['associe_unique']): void
    {
        $this->actingAs($this->clerc)
            ->patch("/dossiers/{$dossier->reference}/questionnaire", [
                'donnees'      => ['soc.denomination' => 'Amadou et frères', 'soc.capital' => '1000000'],
                'managedRoles' => $managedRoles,
                'parties'      => $parties,
            ])
            ->assertSessionHasNoErrors();
    }

    /** Le cœur du défaut : la Partie garde son identité, donc ses pièces. */
    public function test_enregistrer_le_questionnaire_ne_recree_pas_une_section_scalaire(): void
    {
        [$dossier, $partie, $client] = $this->dossierComplet();

        $this->enregistrerQuestionnaire($dossier, [[
            'partie_id'       => $partie->id,
            'nom'             => 'M. Amadou Bailo DIALLO',
            'role'            => 'associe_unique',
            'type_personne'   => 'physique',
            'client_id'       => $client->id,
            'donnees_prefixe' => 'pp',
        ]]);

        $apres = $dossier->fresh()->parties()->where('role', 'associe_unique')->sole();

        $this->assertSame($partie->id, $apres->id, 'La Partie a été détruite puis recréée.');
        $this->assertSame(3, $apres->pieces()->count(), 'Les pièces déjà déposées ont disparu.');
        $this->assertFalse(
            $apres->aUnePieceBloquanteManquante(),
            "L'écran réclamerait de nouveau des pièces pourtant fournies.",
        );
    }

    /**
     * Le même enregistrement, répété : une modale rouverte deux fois de suite ne doit pas
     * multiplier les personnes ni perdre leurs pièces à la seconde passe.
     */
    public function test_deux_enregistrements_de_suite_laissent_une_seule_personne(): void
    {
        [$dossier, $partie, $client] = $this->dossierComplet();

        $payload = [[
            'partie_id'       => $partie->id,
            'nom'             => 'M. Amadou Bailo DIALLO',
            'role'            => 'associe_unique',
            'type_personne'   => 'physique',
            'client_id'       => $client->id,
            'donnees_prefixe' => 'pp',
        ]];

        $this->enregistrerQuestionnaire($dossier, $payload);
        $this->enregistrerQuestionnaire($dossier, $payload);

        $this->assertSame(1, $dossier->fresh()->parties()->where('role', 'associe_unique')->count());
        $this->assertSame(3, Partie::find($partie->id)?->pieces()->count());
    }

    /**
     * Une personne **ajoutée** par la modale — ici le commissaire titulaire, le second rôle de
     * la capture d'écran — n'a pas de `partie_id` : elle doit bien être créée, sans toucher à
     * celle qui existait.
     */
    public function test_une_personne_ajoutee_est_creee_sans_detruire_les_autres(): void
    {
        [$dossier, $partie, $client] = $this->dossierComplet();

        $this->enregistrerQuestionnaire($dossier, [
            [
                'partie_id'       => $partie->id,
                'nom'             => 'M. Amadou Bailo DIALLO',
                'role'            => 'associe_unique',
                'type_personne'   => 'physique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ],
            [
                'nom'             => 'M. Amadou Bailo DIALLO',
                'role'            => 'commissaire_titulaire',
                'type_personne'   => 'physique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'cac',
            ],
        ], ['associe_unique', 'commissaire_titulaire']);

        $dossier = $dossier->fresh();

        $this->assertSame($partie->id, $dossier->parties()->where('role', 'associe_unique')->sole()->id);
        $this->assertSame(3, Partie::find($partie->id)?->pieces()->count());
        $this->assertSame(1, $dossier->parties()->where('role', 'commissaire_titulaire')->count());
    }

    /**
     * La reprise d'office des pièces déjà fournies ailleurs n'existait qu'à la **création**
     * (`DossierController::creerDossier`). Une personne ajoutée par la modale repartait donc
     * d'une liste vide, alors que sa fiche avait déjà sa CNI dans un autre dossier — c'est
     * l'autre moitié du « pourquoi il me redemande les pièces de la même personne ».
     */
    public function test_une_personne_ajoutee_reprend_les_pieces_que_sa_fiche_a_deja_fournies(): void
    {
        [$dossier, $partie, $client] = $this->dossierComplet();

        $this->enregistrerQuestionnaire($dossier, [
            [
                'partie_id'       => $partie->id,
                'nom'             => 'M. Amadou Bailo DIALLO',
                'role'            => 'associe_unique',
                'type_personne'   => 'physique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'pp',
            ],
            [
                'nom'             => 'M. Amadou Bailo DIALLO',
                'role'            => 'gerant',
                'type_personne'   => 'physique',
                'client_id'       => $client->id,
                'donnees_prefixe' => 'ger',
            ],
        ], ['associe_unique', 'gerant']);

        $gerant = $dossier->fresh()->parties()->where('role', 'gerant')->sole();

        $this->assertFalse(
            $gerant->aUnePieceBloquanteManquante(),
            "Les pièces de cette fiche, déjà au dossier pour un autre rôle, auraient dû être reprises.",
        );
    }
}
