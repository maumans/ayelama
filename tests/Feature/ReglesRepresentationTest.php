<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\MotifRepresentation;
use App\Models\Dossier;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ReglesRepresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce qu'une représentation incomplète doit empêcher (2026-09-25).
 *
 * Chaque contrôle correspond à un état que l'acte ne saurait pas rédiger : « ici représenté
 * par » suivi de rien, une clause d'habilitation sans motif, une procuration que l'acte doit
 * viser « en date du… » mais qui n'a pas de date.
 *
 * Contrôlé **hors catégorie Société** à dessein : `ReglesSocieteService` renvoie `[]` dès que
 * le dossier n'en est pas, et une représentation se déclare sur tout type d'acte.
 */
class ReglesRepresentationTest extends TestCase
{
    use RefreshDatabase;

    private function dossier(string $code = 'VTE-IMM', string $categorie = 'vente'): Dossier
    {
        $type = TypeActe::firstOrCreate(
            ['code' => $code],
            ['label' => $code, 'categorie' => CategorieActe::from($categorie), 'delai_jours' => 20, 'actif' => true],
        );

        return Dossier::create([
            'reference'    => 'VTE-2026-9100',
            'type_acte_id' => $type->id,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Vente avec une partie représentée',
            'etape'        => 'initialisation',
        ]);
    }

    private function partie(Dossier $dossier, string $nom, string $role = 'vendeur'): Partie
    {
        return Partie::create(['dossier_id' => $dossier->id, 'nom' => $nom, 'role' => $role]);
    }

    private function service(): ReglesRepresentationService
    {
        return app(ReglesRepresentationService::class);
    }

    public function test_une_representation_complete_ne_bloque_pas(): void
    {
        $dossier = $this->dossier();
        $vendeur = $this->partie($dossier, 'Ibrahima DIALLO');
        $mandat  = $this->partie($dossier, 'Mamadou BAH', MotifRepresentation::ROLE);

        $vendeur->update([
            'represente_par_partie_id'  => $mandat->id,
            'representation_motif'      => MotifRepresentation::Procuration,
            'representation_titre_date' => '2026-03-12',
        ]);

        $this->assertSame([], $this->service()->anomalies($dossier->fresh()));
    }

    public function test_un_mandataire_supprime_laisse_un_etat_bruyant(): void
    {
        // `nullOnDelete` dégrade volontairement vers un état signalé plutôt que d'emporter la
        // partie. Sans ce contrôle, l'acte imprimerait « ici représenté par » suivi de rien.
        $dossier = $this->dossier();
        $vendeur = $this->partie($dossier, 'Ibrahima DIALLO');
        $mandat  = $this->partie($dossier, 'Mamadou BAH', MotifRepresentation::ROLE);

        $vendeur->update([
            'represente_par_partie_id'  => $mandat->id,
            'representation_motif'      => MotifRepresentation::Procuration,
            'representation_titre_date' => '2026-03-12',
        ]);
        $mandat->supprimerAvecPieces();

        $anomalies = $this->service()->anomalies($dossier->fresh());

        $this->assertArrayHasKey('representation_sans_representant', $anomalies);
        $this->assertStringContainsString('Ibrahima DIALLO', $anomalies['representation_sans_representant'][0]);
    }

    public function test_une_procuration_sans_date_bloque(): void
    {
        $dossier = $this->dossier();
        $vendeur = $this->partie($dossier, 'Ibrahima DIALLO');
        $mandat  = $this->partie($dossier, 'Mamadou BAH', MotifRepresentation::ROLE);

        $vendeur->update([
            'represente_par_partie_id' => $mandat->id,
            'representation_motif'     => MotifRepresentation::Procuration,
        ]);

        $this->assertArrayHasKey('representation_titre', $this->service()->anomalies($dossier->fresh()));
    }

    public function test_une_tutelle_sans_date_ne_bloque_pas(): void
    {
        // ⚠️ Volontaire, et c'est là que vit l'aveu d'ignorance : le document guinéen qui
        // établit une tutelle n'a pas été arbitré avec l'étude. Exiger une date de référence
        // qu'on ne sait pas nommer reviendrait à inventer une contrainte — pire qu'une
        // contrainte absente. Bascule en une ligne :
        // MotifRepresentation::Legale->exigeTitreDate().
        $dossier = $this->dossier();
        $mineur  = $this->partie($dossier, 'Enfant DIALLO');
        $tuteur  = $this->partie($dossier, 'Fatoumata DIALLO', MotifRepresentation::ROLE);

        $mineur->update([
            'represente_par_partie_id' => $tuteur->id,
            'representation_motif'     => MotifRepresentation::Legale,
            'representation_qualite'   => 'Tuteur légal',
        ]);

        $this->assertSame([], $this->service()->anomalies($dossier->fresh()));
    }

    public function test_une_chaine_de_representation_bloque(): void
    {
        // Refusée par la validation du formulaire, mais la conversion d'une demande externe et
        // un PATCH direct écrivent aussi : une règle ne peut pas ne vivre que dans le formulaire.
        $dossier = $this->dossier();
        $vendeur = $this->partie($dossier, 'Ibrahima DIALLO');
        $premier = $this->partie($dossier, 'Mamadou BAH', MotifRepresentation::ROLE);
        $second  = $this->partie($dossier, 'Alpha CAMARA', MotifRepresentation::ROLE);

        $premier->update([
            'represente_par_partie_id'  => $second->id,
            'representation_motif'      => MotifRepresentation::Procuration,
            'representation_titre_date' => '2026-03-12',
        ]);
        $vendeur->update([
            'represente_par_partie_id'  => $premier->id,
            'representation_motif'      => MotifRepresentation::Procuration,
            'representation_titre_date' => '2026-03-12',
        ]);

        $this->assertArrayHasKey('representation_chaine', $this->service()->anomalies($dossier->fresh()));
    }

    public function test_un_lien_sans_motif_bloque(): void
    {
        $dossier = $this->dossier();
        $vendeur = $this->partie($dossier, 'Ibrahima DIALLO');
        $mandat  = $this->partie($dossier, 'Mamadou BAH', MotifRepresentation::ROLE);

        $vendeur->update(['represente_par_partie_id' => $mandat->id]);

        $this->assertArrayHasKey('representation_sans_motif', $this->service()->anomalies($dossier->fresh()));
    }

    public function test_les_cles_derreur_ne_collisionnent_pas_avec_celles_des_regles_societe(): void
    {
        // `erreursDeConstitution()` fusionne les deux services par `array_merge`, qui écrase à
        // clé égale. La clé `representation` de ReglesSocieteService — « associé mineur non
        // représenté » — ne doit donc être réutilisée par aucun contrôle d'ici.
        $source = file_get_contents(app_path('Services/ReglesRepresentationService.php'));

        $this->assertStringNotContainsString(
            "\$erreurs['representation']",
            $source,
            "La clé `representation` appartient à ReglesSocieteService : array_merge écraserait "
            . "l'anomalie « associé mineur non représenté ».",
        );
    }
}
