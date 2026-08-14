<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\StatutFormalite;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\DossierStepService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Passage de l'étape Formalités à Expédition.
 *
 * Le statut `Cloture` par formalité a été supprimé — recevoir le retour de
 * l'organisme est désormais l'aboutissement de la démarche. La condition
 * d'avancement était restée sur `!== 'cloture'`, un statut que plus aucune
 * formalité ne pouvait porter : l'étape devenait un cul-de-sac, sans qu'aucune
 * erreur ne le signale. Ces tests verrouillent la règle.
 */
class AvancementFormalitesTest extends TestCase
{
    use RefreshDatabase;

    private User $formaliste;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formaliste = User::factory()->create();
        $this->formaliste->syncRoles([RoleUtilisateur::Formaliste]);
    }

    private function dossierEnFormalites(array $statuts): Dossier
    {
        $typeActe = TypeActe::create([
            'code'      => 'TST-' . fake()->unique()->numberBetween(1000, 9999),
            'label'     => 'Type de test',
            'categorie' => 'societe',
        ]);

        $dossier = Dossier::create([
            'reference'    => 'TST-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $typeActe->id,
            'etape'        => EtapeDossier::Formalites,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => "Dossier de test pour l'avancement des formalités",
        ]);

        foreach ($statuts as $i => $statut) {
            Formalite::create([
                'dossier_id' => $dossier->id,
                'organisme'  => ['APIP', 'Impôts', 'Conservation'][$i] ?? "Organisme {$i}",
                'statut'     => $statut,
            ]);
        }

        return $dossier->fresh();
    }

    private function avancer(Dossier $dossier): Dossier
    {
        return app(DossierStepService::class)->avancer($dossier, $this->formaliste);
    }

    public function test_toutes_les_formalites_avec_retour_recu_permettent_davancer(): void
    {
        // Le cas qui était devenu impossible : plus aucun statut `cloture` n'existe,
        // donc l'ancienne condition rejetait même un dossier entièrement traité.
        $dossier = $this->dossierEnFormalites([
            StatutFormalite::RetourRecu,
            StatutFormalite::RetourRecu,
        ]);

        $this->assertSame(EtapeDossier::Expedition, $this->avancer($dossier)->etape);
    }

    public function test_une_formalite_en_attente_bloque_en_le_disant(): void
    {
        $dossier = $this->dossierEnFormalites([
            StatutFormalite::RetourRecu,
            StatutFormalite::EnAttente,
        ]);

        try {
            $this->avancer($dossier);
            $this->fail('Le passage à l\'expédition aurait dû être refusé.');
        } catch (ValidationException $e) {
            $message = $e->errors()['formalites'][0];
            $this->assertStringContainsString('En attente de retour', $message);
            $this->assertStringContainsString('Impôts', $message);
            // L'organisme déjà traité n'a pas à être listé comme bloquant.
            $this->assertStringNotContainsString('APIP', $message);
        }

        $this->assertSame(EtapeDossier::Formalites, $dossier->fresh()->etape);
    }

    public function test_une_formalite_rejetee_bloque_avec_un_message_distinct(): void
    {
        // Un rejet demande une action du formaliste, une attente ne dépend que de
        // l'organisme : confondre les deux laisserait l'utilisateur sans savoir quoi
        // faire.
        $dossier = $this->dossierEnFormalites([StatutFormalite::Rejete]);

        try {
            $this->avancer($dossier);
            $this->fail('Le passage à l\'expédition aurait dû être refusé.');
        } catch (ValidationException $e) {
            $message = $e->errors()['formalites'][0];
            $this->assertStringContainsString('À corriger et redéposer', $message);
            $this->assertStringContainsString('APIP', $message);
        }
    }

    public function test_les_deux_causes_sont_annoncees_ensemble(): void
    {
        $dossier = $this->dossierEnFormalites([
            StatutFormalite::Rejete,
            StatutFormalite::ADeposer,
        ]);

        try {
            $this->avancer($dossier);
            $this->fail('Le passage à l\'expédition aurait dû être refusé.');
        } catch (ValidationException $e) {
            $message = $e->errors()['formalites'][0];
            $this->assertStringContainsString('À corriger et redéposer : APIP', $message);
            $this->assertStringContainsString('En attente de retour : Impôts', $message);
        }
    }

    public function test_un_dossier_sans_aucune_formalite_avance(): void
    {
        // Comportement d'origine conservé : un type d'acte sans barème générant de
        // formalité ne doit pas bloquer.
        $dossier = $this->dossierEnFormalites([]);

        $this->assertSame(EtapeDossier::Expedition, $this->avancer($dossier)->etape);
    }

    public function test_seul_retour_recu_est_un_etat_terminal(): void
    {
        // Garde-fou : si un statut est ajouté à l'enum, le `match` exhaustif de
        // estTerminee() lèvera une erreur tant qu'il n'aura pas été classé — c'est
        // exactement le filet qui manquait lors de la suppression de `Cloture`.
        $this->assertTrue(StatutFormalite::RetourRecu->estTerminee());

        foreach ([StatutFormalite::ADeposer, StatutFormalite::Depose,
                  StatutFormalite::EnAttente, StatutFormalite::Rejete] as $statut) {
            $this->assertFalse($statut->estTerminee(), "{$statut->value} ne doit pas être terminal.");
        }

        $this->assertSame(['retour_recu'], StatutFormalite::valeursTerminees());
    }

    public function test_le_scope_non_terminees_suit_la_meme_regle(): void
    {
        $dossier = $this->dossierEnFormalites([
            StatutFormalite::RetourRecu,
            StatutFormalite::EnAttente,
            StatutFormalite::Rejete,
        ]);

        $this->assertSame(2, $dossier->formalites()->nonTerminees()->count());
    }
}
