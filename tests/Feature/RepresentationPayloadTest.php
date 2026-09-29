<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\MotifRepresentation;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le lien de représentation, de l'envoi du formulaire à la base (2026-09-24).
 *
 * Le représentant n'a pas d'identifiant au moment où le représenté est créé : la corrélation
 * passe donc par une **clé locale**, propre à la soumission, que le serveur résout en seconde
 * passe. Le même chemin sert à l'édition — deux chemins de résolution seraient deux branches de
 * validation, et une divergence garantie.
 */
class RepresentationPayloadTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Clerc->value]);

        return $user->fresh();
    }

    private function notaire(): User
    {
        $user = User::factory()->create(['actif' => true]);
        UserRole::create(['user_id' => $user->id, 'role' => RoleUtilisateur::Notaire->value]);

        return $user->fresh();
    }

    private function typeActe(): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => 'SOC-SARLU'],
            ['label' => 'Constitution SARLU', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );
    }

    private function client(string $nom): Client
    {
        return Client::create([
            'type' => 'physique', 'statut' => 'prospect', 'civilite' => 'M.', 'prenom_nom' => $nom,
        ]);
    }

    /** @param array<int, array<string, mixed>> $parties */
    private function creer(array $parties, ?User $clerc = null)
    {
        return $this->actingAs($clerc ?? $this->clerc())->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU avec une personne représentée',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Représentation SARLU'],
            'parties'      => $parties,
        ]);
    }

    private function payloadRepresente(array $surcharge = []): array
    {
        return array_merge([
            'cle_locale'                 => 'p1',
            'nom'                        => 'Ibrahima DIALLO',
            'role'                       => 'associe_unique',
            'client_id'                  => $this->client('Ibrahima DIALLO')->id,
            'donnees_prefixe'            => 'pp',
            'represente_par_cle'         => 'm1',
            'representation_motif'       => MotifRepresentation::Procuration->value,
            'representation_titre_forme' => 'sous_seing_prive',
            'representation_titre_date'  => '2026-03-12',
        ], $surcharge);
    }

    private function payloadMandataire(array $surcharge = []): array
    {
        return array_merge([
            'cle_locale' => 'm1',
            'nom'        => 'Mamadou BAH',
            'role'       => MotifRepresentation::ROLE,
            'client_id'  => $this->client('Mamadou BAH')->id,
        ], $surcharge);
    }

    // ── Création ─────────────────────────────────────────────────────────────

    public function test_le_lien_est_resolu_en_seconde_passe(): void
    {
        $this->creer([$this->payloadRepresente(), $this->payloadMandataire()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $represente = Partie::where('role', 'associe_unique')->sole();
        $mandataire = Partie::where('role', MotifRepresentation::ROLE)->sole();

        $this->assertSame($mandataire->id, $represente->represente_par_partie_id);
        $this->assertSame(MotifRepresentation::Procuration, $represente->representation_motif);
        $this->assertSame('12/03/2026', $represente->representation_titre_date->format('d/m/Y'));

        // Le mandataire ne porte aucune localisation : il n'occupe pas d'emplacement du
        // questionnaire, donc il ne peut pas écraser la projection du représenté.
        $this->assertNull($mandataire->donnees_prefixe);
        $this->assertNull($mandataire->donnees_bloc);
    }

    public function test_les_pieces_exigees_suivent_le_motif(): void
    {
        $this->creer([$this->payloadRepresente(), $this->payloadMandataire()])
            ->assertRedirect()->assertSessionHasNoErrors();

        $represente = Partie::where('role', 'associe_unique')->sole();
        $mandataire = Partie::where('role', MotifRepresentation::ROLE)->sole();

        // La procuration est le titre du mandant : c'est lui qui la fournit.
        $this->assertArrayHasKey('procuration', $represente->piecesRequisesDefinition());
        // Le mandataire ne doit que son identité — ni résidence ni seconde photo : il n'est
        // pas partie à l'acte.
        $this->assertSame(['cni'], array_keys($mandataire->piecesRequisesDefinition()));
    }

    // ── Refus bruyants ───────────────────────────────────────────────────────

    public function test_une_cle_inconnue_est_refusee(): void
    {
        // Jamais résolue en `null` silencieux : le dossier annoncerait « ici représenté par »
        // suivi de rien.
        $this->creer([$this->payloadRepresente(['represente_par_cle' => 'inexistant'])])
            ->assertSessionHasErrors('parties.0.represente_par_cle');

        $this->assertSame(0, Dossier::count());
    }

    public function test_une_auto_representation_est_refusee(): void
    {
        $this->creer([$this->payloadRepresente(['represente_par_cle' => 'p1'])])
            ->assertSessionHasErrors('parties.0.represente_par_cle');
    }

    public function test_une_chaine_de_representation_est_refusee(): void
    {
        // Un mandataire lui-même représenté produirait une comparution récursive.
        $this->creer([
            $this->payloadRepresente(),
            $this->payloadMandataire([
                'represente_par_cle'   => 'm2',
                'representation_motif' => MotifRepresentation::Procuration->value,
            ]),
            ['cle_locale' => 'm2', 'nom' => 'Troisième', 'role' => MotifRepresentation::ROLE],
        ])->assertSessionHasErrors('parties.0.represente_par_cle');
    }

    public function test_un_representant_sans_motif_est_refuse(): void
    {
        $this->creer([
            $this->payloadRepresente(['representation_motif' => null]),
            $this->payloadMandataire(),
        ])->assertSessionHasErrors('parties.0.representation_motif');
    }

    // ── Édition ──────────────────────────────────────────────────────────────

    public function test_retirer_la_representation_supprime_le_mandataire_devenu_orphelin(): void
    {
        $clerc = $this->clerc();
        $this->creer([$this->payloadRepresente(), $this->payloadMandataire()], $clerc)
            ->assertRedirect()->assertSessionHasNoErrors();

        $dossier    = Dossier::sole();
        $represente = Partie::where('role', 'associe_unique')->sole();

        $this->actingAs($clerc)->patch("/dossiers/{$dossier->reference}/questionnaire", [
            'donnees'      => ['soc.denomination' => 'Représentation SARLU'],
            'managedRoles' => ['associe_unique'],
            'parties'      => [[
                'partie_id'       => $represente->id,
                'cle_locale'      => 'p1',
                'nom'             => 'Ibrahima DIALLO',
                'role'            => 'associe_unique',
                'client_id'       => $represente->client_id,
                'donnees_prefixe' => 'pp',
                // Plus de `represente_par_cle` : la case a été décochée.
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($represente->fresh()->represente_par_partie_id);
        $this->assertNull($represente->fresh()->representation_motif);
        $this->assertSame(
            0,
            Partie::where('role', MotifRepresentation::ROLE)->count(),
            'Un mandataire que plus personne ne désigne doit disparaître, sinon sa checklist '
            . "continue de bloquer la sortie d'Initialisation.",
        );
    }

    public function test_un_mandataire_encore_designe_par_une_autre_partie_survit(): void
    {
        // La règle est dérivée du graphe, pas d'un appariement par rôle : c'est ce qui la rend
        // insensible à l'ordre et aux trous du payload.
        $clerc = $this->clerc();
        $this->creer([
            $this->payloadRepresente(),
            $this->payloadRepresente([
                'cle_locale'      => 'p2',
                'nom'             => 'Fatoumata BAH',
                'role'            => 'gerant',
                'client_id'       => $this->client('Fatoumata BAH')->id,
                'donnees_prefixe' => 'ger',
            ]),
            $this->payloadMandataire(),
        ], $clerc)->assertRedirect()->assertSessionHasNoErrors();

        $dossier    = Dossier::sole();
        $mandataire = Partie::where('role', MotifRepresentation::ROLE)->sole();
        $gerant     = Partie::where('role', 'gerant')->sole();

        // On ne renvoie que le gérant, qui garde son mandataire : l'associé unique n'est pas
        // dans `managedRoles`, donc pas resynchronisé.
        $this->actingAs($clerc)->patch("/dossiers/{$dossier->reference}/questionnaire", [
            'donnees'      => ['soc.denomination' => 'Représentation SARLU'],
            'managedRoles' => ['gerant'],
            'parties'      => [
                [
                    'partie_id'                  => $gerant->id,
                    'cle_locale'                 => 'p2',
                    'nom'                        => 'Fatoumata BAH',
                    'role'                       => 'gerant',
                    'client_id'                  => $gerant->client_id,
                    'donnees_prefixe'            => 'ger',
                    'represente_par_cle'         => 'm1',
                    'representation_motif'       => MotifRepresentation::Procuration->value,
                    'representation_titre_forme' => 'sous_seing_prive',
                    'representation_titre_date'  => '2026-03-12',
                ],
                ['partie_id' => $mandataire->id, 'cle_locale' => 'm1', 'nom' => 'Mamadou BAH', 'role' => MotifRepresentation::ROLE],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNotNull($mandataire->fresh(), 'Le mandataire est encore désigné : il doit survivre.');
    }

    public function test_le_role_mandataire_ne_peut_pas_etre_synchronise_par_role(): void
    {
        // Garde contre un payload forgé : la boucle par rôle supprimerait les représentants
        // en contournant le ramasse-miettes.
        $clerc = $this->clerc();
        $this->creer([$this->payloadRepresente(), $this->payloadMandataire()], $clerc)
            ->assertRedirect()->assertSessionHasNoErrors();

        $dossier = Dossier::sole();

        $this->actingAs($clerc)->patch("/dossiers/{$dossier->reference}/questionnaire", [
            'donnees'      => ['soc.denomination' => 'Représentation SARLU'],
            'managedRoles' => [MotifRepresentation::ROLE],
            'parties'      => [],
        ])->assertSessionHasErrors('managedRoles.0');
    }
}
