<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\FormeTitreRepresentation;
use App\Enums\MotifRepresentation;
use App\Models\Client;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Services\ClientProjectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La représentation projetée dans le questionnaire, et rédigée dans l'acte (2026-09-24).
 *
 * Le représenté reste la partie ; le représentant comparaît pour lui. Le lien est porté par le
 * **représenté** — un même mandataire sert couramment plusieurs mandants du même acte — et le
 * représentant n'occupe **aucun** emplacement du questionnaire : c'est ainsi que sa projection
 * ne peut pas écraser celle de la personne qu'il représente.
 */
class RepresentationProjectionTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(array $donnees = []): Dossier
    {
        $type = TypeActe::firstOrCreate(
            ['code' => 'SOC-SARL'],
            ['label' => 'Constitution SARL', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );

        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-9001',
            'type_acte_id' => $type->id,
            'redacteur_id' => \App\Models\User::factory()->create()->id,
            'objet'        => 'Dossier de test pour la représentation',
            'etape'        => 'initialisation',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => $donnees]);

        return $dossier->fresh();
    }

    private function client(array $attributs = []): Client
    {
        return Client::create(array_merge([
            'type'         => 'physique',
            'statut'       => 'prospect',
            'civilite'     => 'M.',
            'prenom_nom'   => 'Mamadou BAH',
            'piece_type'   => 'CNI CEDEAO',
            'piece_numero' => 'GN0987654',
            'quartier'     => 'Almamya',
            'commune'      => 'Kaloum',
            'demeurant_ville' => 'Conakry',
        ], $attributs));
    }

    private function projection(): ClientProjectionService
    {
        return app(ClientProjectionService::class);
    }

    /** Un mandataire, rattaché à un représenté, avec un titre. */
    private function representer(Partie $represente, Client $clientMandataire, array $mandat = []): Partie
    {
        $mandataire = Partie::create([
            'dossier_id' => $represente->dossier_id,
            'client_id'  => $clientMandataire->id,
            'nom'        => $clientMandataire->prenom_nom,
            'role'       => MotifRepresentation::ROLE,
            'cni'        => $clientMandataire->piece_numero,
            'adresse'    => 'Almamya, Kaloum, Conakry',
        ]);

        $represente->update(array_merge([
            'represente_par_partie_id'   => $mandataire->id,
            'representation_motif'       => MotifRepresentation::Procuration,
            'representation_titre_forme' => FormeTitreRepresentation::SousSeingPrive,
            'representation_titre_date'  => '2026-03-12',
        ], $mandat));

        return $mandataire;
    }

    // ── Section scalaire ─────────────────────────────────────────────────────

    public function test_une_section_scalaire_projette_le_representant_dans_son_sous_espace(): void
    {
        $dossier = $this->dossier(['soc.denomination' => 'Test SARL']);
        $mandant = Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $this->client(['prenom_nom' => 'Ibrahima DIALLO'])->id,
            'nom'             => 'Ibrahima DIALLO',
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $this->representer($mandant, $this->client());

        $this->projection()->reprojeter($dossier->fresh());
        $donnees = $dossier->fresh('questionnaire')->questionnaire->donnees;

        // Le mandant reste la partie : son identité n'a pas bougé.
        $this->assertSame('Ibrahima DIALLO', $donnees['pp.prenom_nom']);

        // Le représentant vit dans le sous-espace, sans collision possible.
        $this->assertSame('Mamadou BAH', $donnees['pp.repr_prenom_nom']);
        $this->assertSame('GN0987654', $donnees['pp.repr_piece_numero']);
        $this->assertSame('12/03/2026', $donnees['pp.repr_titre_date']);
        $this->assertSame('Procuration', $donnees['pp.repr_motif']);

        $this->assertStringContainsString('ici représenté par Monsieur Mamadou BAH', $donnees['pp.comparution']);
    }

    public function test_sans_representation_la_comparution_dit_la_presence(): void
    {
        $dossier = $this->dossier();
        Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $this->client()->id,
            'nom'             => 'Mamadou BAH',
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $this->projection()->reprojeter($dossier->fresh());
        $donnees = $dossier->fresh('questionnaire')->questionnaire->donnees;

        $this->assertSame('A ce, présent', $donnees['pp.comparution']);
        $this->assertSame('', $donnees['pp.repr_prenom_nom']);
    }

    public function test_retirer_la_representation_efface_le_sous_espace(): void
    {
        // Le défaut que l'effacement explicite prévient : un mandataire révoqué qui continue
        // d'être imprimé dans l'acte.
        $dossier = $this->dossier();
        $mandant = Partie::create([
            'dossier_id'      => $dossier->id,
            'client_id'       => $this->client(['prenom_nom' => 'Ibrahima DIALLO'])->id,
            'nom'             => 'Ibrahima DIALLO',
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);
        $this->representer($mandant, $this->client());

        $this->projection()->reprojeter($dossier->fresh());
        $this->assertSame('Mamadou BAH', $dossier->fresh('questionnaire')->questionnaire->donnees['pp.repr_prenom_nom']);

        $mandant->update([
            'represente_par_partie_id'  => null,
            'representation_motif'      => null,
            'representation_titre_date' => null,
        ]);

        $this->projection()->reprojeter($dossier->fresh());
        $donnees = $dossier->fresh('questionnaire')->questionnaire->donnees;

        $this->assertSame('', $donnees['pp.repr_prenom_nom'], 'Le mandataire révoqué reste imprimé dans l\'acte.');
        $this->assertSame('', $donnees['pp.repr_titre_date']);
        $this->assertSame('A ce, présent', $donnees['pp.comparution']);
    }

    // ── Bloc répétable ───────────────────────────────────────────────────────

    public function test_un_item_de_bloc_projette_a_plat_sans_perdre_les_donnees_dacte(): void
    {
        $dossier = $this->dossier(['associes' => [
            ['nom' => 'Premier', 'parts_chiffres' => '40'],
            ['nom' => 'Ibrahima DIALLO', 'parts_chiffres' => '60'],
        ]]);

        $mandant = Partie::create([
            'dossier_id'    => $dossier->id,
            'client_id'     => $this->client(['prenom_nom' => 'Ibrahima DIALLO'])->id,
            'nom'           => 'Ibrahima DIALLO',
            'role'          => 'associe',
            'donnees_bloc'  => 'associes',
            'donnees_index' => 1,
        ]);
        $this->representer($mandant, $this->client());

        $this->projection()->reprojeter($dossier->fresh());
        $item = $dossier->fresh('questionnaire')->questionnaire->donnees['associes'][1];

        $this->assertSame('Mamadou BAH', $item['repr_prenom_nom']);
        $this->assertStringContainsString('ici représenté par', $item['comparution']);
        // La donnée propre à l'acte survit — la garantie que `array_merge` doit préserver.
        $this->assertSame('60', $item['parts_chiffres']);
        // L'item voisin n'a pas été touché.
        $this->assertArrayNotHasKey('repr_prenom_nom', $dossier->fresh('questionnaire')->questionnaire->donnees['associes'][0]);
    }

    // ── Cardinalité : un mandataire, plusieurs mandants ──────────────────────

    public function test_un_seul_mandataire_peut_representer_plusieurs_parties(): void
    {
        // La raison d'être du sens de la clé étrangère : une ligne, une fiche, un jeu de pièces.
        $dossier = $this->dossier(['associes' => [['nom' => 'A'], ['nom' => 'B']]]);
        $clientMandataire = $this->client();

        $premier = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $this->client(['prenom_nom' => 'A'])->id,
            'nom' => 'A', 'role' => 'associe', 'donnees_bloc' => 'associes', 'donnees_index' => 0,
        ]);
        $second = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $this->client(['prenom_nom' => 'B'])->id,
            'nom' => 'B', 'role' => 'associe', 'donnees_bloc' => 'associes', 'donnees_index' => 1,
        ]);

        $mandataire = $this->representer($premier, $clientMandataire);
        $second->update([
            'represente_par_partie_id'   => $mandataire->id,
            'representation_motif'       => MotifRepresentation::Procuration,
            'representation_titre_forme' => FormeTitreRepresentation::Notariee,
            'representation_titre_date'  => '2026-04-02',
            'representation_titre_autorite' => 'Amadou SOW',
        ]);

        $this->assertSame(
            1,
            Partie::where('role', MotifRepresentation::ROLE)->count(),
            'Un mandataire commun doit rester une seule ligne — donc un seul jeu de pièces.',
        );

        $this->projection()->reprojeter($dossier->fresh());
        $donnees = $dossier->fresh('questionnaire')->questionnaire->donnees;

        // Même personne, deux mandats distincts : chacun vise son propre titre.
        $this->assertStringContainsString('procuration sous seing privé', $donnees['associes'][0]['comparution']);
        $this->assertStringContainsString('reçue par Maître Amadou SOW', $donnees['associes'][1]['comparution']);
    }

    // ── Suppression ──────────────────────────────────────────────────────────

    public function test_supprimer_le_mandataire_delie_sans_emporter_le_represente(): void
    {
        $dossier = $this->dossier();
        $mandant = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $this->client(['prenom_nom' => 'X'])->id,
            'nom' => 'X', 'role' => 'associe_unique', 'donnees_prefixe' => 'pp',
        ]);
        $mandataire = $this->representer($mandant, $this->client());

        $mandataire->supprimerAvecPieces();

        $this->assertNotNull($mandant->fresh(), "Le représenté ne doit jamais être emporté.");
        $this->assertNull($mandant->fresh()->represente_par_partie_id);
        // Le motif subsiste : c'est ce qui rend l'état bruyant plutôt que silencieux.
        $this->assertSame(MotifRepresentation::Procuration, $mandant->fresh()->representation_motif);
    }

    public function test_supprimer_le_dossier_avec_une_representation_ne_leve_pas_derreur(): void
    {
        // Clé étrangère auto-référente `SET NULL` sur une table par ailleurs détruite en
        // cascade : la combinaison que MySQL supporte mal, d'où le hook `deleting`.
        $dossier = $this->dossier();
        $mandant = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $this->client(['prenom_nom' => 'Y'])->id,
            'nom' => 'Y', 'role' => 'associe_unique', 'donnees_prefixe' => 'pp',
        ]);
        $this->representer($mandant, $this->client());

        $dossier->delete();

        // Ce qui est vérifié ici n'est pas la cascade — SQLite n'applique pas les mêmes
        // contraintes que MySQL en test — mais l'absence de lien pendant au moment où elle
        // part : c'est ce qui fait échouer MySQL sur une auto-référence.
        $this->assertSame(
            0,
            Partie::whereNotNull('represente_par_partie_id')->count(),
            'Les liens de représentation doivent être défaits avant la cascade.',
        );
    }

    // ── La fiche du mandataire est vivante ───────────────────────────────────

    public function test_corriger_la_fiche_du_mandataire_touche_le_dossier(): void
    {
        // Sans la branche ajoutée à reprojeterDossiersDuClient(), aucun dossier ne serait
        // retrouvé : un mandataire n'est jamais localisé pour lui-même.
        $dossier = $this->dossier();
        $mandant = Partie::create([
            'dossier_id' => $dossier->id, 'client_id' => $this->client(['prenom_nom' => 'Z'])->id,
            'nom' => 'Z', 'role' => 'associe_unique', 'donnees_prefixe' => 'pp',
        ]);
        $clientMandataire = $this->client();
        $this->representer($mandant, $clientMandataire);
        $this->projection()->reprojeter($dossier->fresh());

        $clientMandataire->update(['piece_numero' => 'GN1111111']);
        $touches = $this->projection()->reprojeterDossiersDuClient($clientMandataire->fresh());

        $this->assertSame([$dossier->reference], $touches);
        $this->assertSame(
            'GN1111111',
            $dossier->fresh('questionnaire')->questionnaire->donnees['pp.repr_piece_numero'],
        );
    }
}
