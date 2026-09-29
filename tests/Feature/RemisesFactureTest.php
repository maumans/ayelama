<?php

namespace Tests\Feature;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Enums\TypeRemise;
use App\Models\Bareme;
use App\Models\Dossier;
use App\Models\Facture;
use App\Models\LigneFacture;
use App\Models\Paiement;
use App\Models\Questionnaire;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\FacturationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Remises sur les lignes de facture — 2026-09-29.
 *
 * Deux règles portent tout le dispositif :
 *
 *   - **Toutes les lignes ne se remisent pas.** Mesuré sur la grille réelle : 16 barèmes
 *     d'organisme « Notaire » — les honoraires de l'étude — contre 74 débours versés à des
 *     tiers. Remiser un débours n'entame pas une marge, cela fait perdre de l'argent réel :
 *     l'étude paie 180 000 GNF au greffe quoi qu'il arrive.
 *   - **Une remise se dit de deux façons, elle ne se stocke qu'une fois.** Montant ou
 *     pourcentage, l'un se calcule depuis l'autre — mais seul le choix d'origine est conservé,
 *     parce que les deux ne réagissent pas pareil à un tarif qui change.
 */
class RemisesFactureTest extends TestCase
{
    use RefreshDatabase;

    private function comptable(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

        return $user->fresh();
    }

    private function typeActe(): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => 'SOC-TSTF'],
            ['label' => 'Constitution de test', 'categorie' => 'societe', 'prefixe_reference' => 'SOC'],
        );
    }

    private function dossier(): Dossier
    {
        $dossier = Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe()->id,
            'etape'        => EtapeDossier::Formalites,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test des remises de facture',
        ]);

        Questionnaire::create(['dossier_id' => $dossier->id, 'donnees' => []]);

        return $dossier;
    }

    /** Une facture d'une ligne, remisable ou non. */
    private function facture(bool $remisable = true, float $montant = 4_500_000, int $quantite = 1): Facture
    {
        $facture = Facture::create([
            'dossier_id'   => $this->dossier()->id,
            'note_numero'  => Facture::genererNumero(),
            'note_date'    => now(),
            'objet'        => 'Note de test',
            'total_chiffres' => 0,
        ]);

        LigneFacture::create([
            'facture_id'       => $facture->id,
            'designation'      => $remisable ? 'Honoraires forfaitaires' : 'Timbres & rôles',
            'quantite'         => $quantite,
            'montant'          => $montant,
            'remise_autorisee' => $remisable,
        ]);

        return $facture->fresh()->load('lignes')->recalculerTotal();
    }

    // ═══ Les deux expressions d'une même remise ══════════════════════════

    public function test_un_pourcentage_et_le_montant_equivalent_donnent_le_meme_resultat(): void
    {
        // C'est la demande : « soit je mets 100 et ça le détermine en pourcentage, et vice
        // versa ». Les deux formes doivent être interchangeables au centime près.
        $parPourcentage = $this->facture();
        $parMontant     = $this->facture();

        $parPourcentage->lignes->first()->update(['remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10]);
        $parMontant->lignes->first()->update(['remise_type' => TypeRemise::Montant, 'remise_valeur' => 450_000]);

        $a = $parPourcentage->fresh()->load('lignes')->lignes->first();
        $b = $parMontant->fresh()->load('lignes')->lignes->first();

        $this->assertSame(450_000.0, $a->remiseMontant());
        $this->assertSame(450_000.0, $b->remiseMontant());
        $this->assertSame(10.0, $a->remisePourcentage());
        $this->assertSame(10.0, $b->remisePourcentage());
        $this->assertSame($a->total(), $b->total());
    }

    public function test_la_remise_porte_sur_le_montant_brut_quantite_comprise(): void
    {
        // Trois exemplaires à 100 000 : 10 % font 30 000, pas 10 000.
        $facture = $this->facture(montant: 100_000, quantite: 3);
        $facture->lignes->first()->update(['remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10]);

        $ligne = $facture->fresh()->load('lignes')->lignes->first();

        $this->assertSame(300_000.0, $ligne->montantBrut());
        $this->assertSame(30_000.0, $ligne->remiseMontant());
        $this->assertSame(270_000.0, $ligne->total());
    }

    public function test_le_total_de_la_facture_suit_la_remise(): void
    {
        $facture = $this->facture();

        $this->assertSame(4_500_000.0, (float) $facture->total_chiffres);

        $facture->lignes->first()->update(['remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 20]);
        $facture->fresh()->load('lignes')->recalculerTotal();

        $facture->refresh();
        $this->assertSame(3_600_000.0, (float) $facture->total_chiffres);
        $this->assertSame(4_500_000.0, $facture->load('lignes')->totalBrut());
        $this->assertSame(900_000.0, $facture->totalRemises());
    }

    // ═══ Ce qui ne se remise pas ═════════════════════════════════════════

    public function test_un_debours_refuse_la_remise(): void
    {
        // Contrôlé **côté serveur** et pas seulement à l'écran : une règle ne peut pas ne
        // vivre que dans le formulaire.
        $facture = $this->facture(remisable: false, montant: 180_000);
        $ligne   = $facture->lignes->first();

        $this->actingAs($this->comptable())
            ->patch("/lignes/{$ligne->id}", [
                'designation'   => $ligne->designation,
                'quantite'      => 1,
                'montant'       => 180_000,
                'remise_type'   => 'pourcentage',
                'remise_valeur' => 10,
            ])
            ->assertSessionHasErrors('remise_valeur');

        $this->assertSame(0.0, $ligne->fresh()->remiseMontant());
    }

    public function test_une_ligne_ajoutee_a_la_main_est_remisable(): void
    {
        // Elle n'a pas de barème dont hériter, et l'étude l'a créée délibérément : refuser par
        // défaut l'obligerait à passer par Paramètres pour une ligne qui n'y figure pas.
        $facture = $this->facture();

        $this->actingAs($this->comptable())
            ->post("/factures/{$facture->id}/lignes", [
                'designation' => 'Prestation exceptionnelle',
                'quantite'    => 1,
                'montant'     => 200_000,
            ])
            ->assertSessionHasNoErrors();

        $ajoutee = LigneFacture::where('designation', 'Prestation exceptionnelle')->first();
        $this->assertTrue($ajoutee->remiseAutorisee());
    }

    public function test_les_lignes_generees_heritent_du_reglage_de_leur_bareme(): void
    {
        $type = $this->typeActe();

        Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Notaire', 'libelle' => 'Honoraires forfaitaires',
            'montant_fixe' => 4_500_000, 'base_calcul' => 'montant_fixe', 'actif' => true,
            'remise_autorisee' => true, 'ordre' => 1,
        ]);
        Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Greffe', 'libelle' => 'Dépôt au greffe',
            'montant_fixe' => 180_000, 'base_calcul' => 'montant_fixe', 'actif' => true,
            'remise_autorisee' => false, 'ordre' => 2,
        ]);

        $facture = app(FacturationService::class)->genererFacture($this->dossier());
        $lignes  = $facture->load('lignes')->lignes->keyBy('designation');

        $this->assertTrue($lignes['Honoraires forfaitaires']->remiseAutorisee());
        $this->assertFalse($lignes['Dépôt au greffe']->remiseAutorisee());
        // La ligne sait d'où elle vient : c'est ce qui permet de retrouver sa remise plus tard.
        $this->assertNotNull($lignes['Honoraires forfaitaires']->bareme_id);
    }

    // ═══ Les plafonds ════════════════════════════════════════════════════

    public function test_une_remise_ne_depasse_pas_cent_pour_cent(): void
    {
        $facture = $this->facture();
        $ligne   = $facture->lignes->first();

        $this->actingAs($this->comptable())
            ->patch("/lignes/{$ligne->id}", [
                'designation' => $ligne->designation, 'quantite' => 1, 'montant' => 4_500_000,
                'remise_type' => 'pourcentage', 'remise_valeur' => 150,
            ])
            ->assertSessionHasErrors('remise_valeur');
    }

    public function test_une_remise_en_montant_ne_depasse_pas_la_ligne(): void
    {
        $facture = $this->facture();
        $ligne   = $facture->lignes->first();

        $this->actingAs($this->comptable())
            ->patch("/lignes/{$ligne->id}", [
                'designation' => $ligne->designation, 'quantite' => 1, 'montant' => 4_500_000,
                'remise_type' => 'montant', 'remise_valeur' => 5_000_000,
            ])
            ->assertSessionHasErrors('remise_valeur');
    }

    public function test_une_remise_devenue_trop_grande_est_plafonnee_et_signalee(): void
    {
        // Le cas naît d'un tarif revu à la baisse après coup. Le total ne doit jamais devenir
        // négatif, et l'écart doit se voir — corriger seul une décision commerciale serait pire.
        $facture = $this->facture();
        $ligne   = $facture->lignes->first();
        $ligne->update(['remise_type' => TypeRemise::Montant, 'remise_valeur' => 4_000_000]);

        $ligne->update(['montant' => 1_000_000]);
        $ligne->refresh();

        $this->assertSame(1_000_000.0, $ligne->remiseMontant(), 'La remise est plafonnée au brut.');
        $this->assertSame(0.0, $ligne->total());
        $this->assertTrue($ligne->remiseDepasseLaLigne());
    }

    public function test_une_ligne_sans_remise_se_comporte_exactement_comme_avant(): void
    {
        // Non-régression : 111 lignes existaient sans remise, elles ne doivent rien changer.
        $facture = $this->facture(montant: 70_000, quantite: 2);
        $ligne   = $facture->lignes->first();

        $this->assertSame(140_000.0, $ligne->total());
        $this->assertSame(0.0, $ligne->remiseMontant());
        $this->assertSame(0.0, $ligne->remisePourcentage());
        $this->assertSame(140_000.0, (float) $facture->total_chiffres);
    }

    // ═══ La survie à la régénération ═════════════════════════════════════

    public function test_une_remise_survit_a_la_regeneration_de_la_facture(): void
    {
        // ⚠️ `genererFacture()` supprime la facture et la reconstruit. Sans reprise, une remise
        // accordée à la main disparaîtrait au premier recalcul, en silence.
        $type    = $this->typeActe();
        $dossier = $this->dossier();

        Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Notaire', 'libelle' => 'Honoraires forfaitaires',
            'montant_fixe' => 4_500_000, 'base_calcul' => 'montant_fixe', 'actif' => true,
            'remise_autorisee' => true, 'ordre' => 1,
        ]);

        $facture = app(FacturationService::class)->genererFacture($dossier);
        $facture->load('lignes')->lignes->first()->update([
            'remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10,
        ]);

        $regeneree = app(FacturationService::class)->genererFacture($dossier->fresh());
        $ligne     = $regeneree->load('lignes')->lignes->first();

        $this->assertSame(TypeRemise::Pourcentage, $ligne->remise_type);
        $this->assertSame(450_000.0, $ligne->remiseMontant());
        $this->assertSame(4_050_000.0, (float) $regeneree->fresh()->total_chiffres);
    }

    public function test_une_remise_en_pourcentage_suit_un_tarif_qui_change(): void
    {
        // C'est la raison de stocker le **choix** et non le résultat : 10 % restent 10 %.
        $type    = $this->typeActe();
        $dossier = $this->dossier();

        $bareme = Bareme::create([
            'type_acte_id' => $type->id, 'organisme' => 'Notaire', 'libelle' => 'Honoraires forfaitaires',
            'montant_fixe' => 4_500_000, 'base_calcul' => 'montant_fixe', 'actif' => true,
            'remise_autorisee' => true, 'ordre' => 1,
        ]);

        $facture = app(FacturationService::class)->genererFacture($dossier);
        $facture->load('lignes')->lignes->first()->update([
            'remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10,
        ]);

        $bareme->update(['montant_fixe' => 2_000_000]);
        $ligne = app(FacturationService::class)->genererFacture($dossier->fresh())->load('lignes')->lignes->first();

        $this->assertSame(200_000.0, $ligne->remiseMontant(), '10 % du nouveau tarif, pas l\'ancien montant.');
        $this->assertSame(1_800_000.0, $ligne->total());
    }

    // ═══ L'invariant de paiement ═════════════════════════════════════════

    public function test_une_facture_deja_payee_refuse_toute_modification_de_ligne(): void
    {
        // Garde préexistante, qui protège aussi les remises : baisser un total sous les
        // paiements déjà encaissés rendrait la facture trop perçue.
        $facture = $this->facture();
        Paiement::create([
            'facture_id' => $facture->id, 'montant' => 1_000_000,
            'date_paiement' => now(), 'moyen_paiement' => 'especes',
        ]);

        $ligne = $facture->lignes->first();

        $this->actingAs($this->comptable())
            ->patch("/lignes/{$ligne->id}", [
                'designation' => $ligne->designation, 'quantite' => 1, 'montant' => 4_500_000,
                'remise_type' => 'pourcentage', 'remise_valeur' => 50,
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0.0, $ligne->fresh()->remiseMontant());
    }

    // ═══ La trace ════════════════════════════════════════════════════════

    public function test_une_remise_est_journalisee_avec_ses_deux_formes(): void
    {
        // Un geste commercial doit pouvoir s'expliquer a posteriori.
        $facture = $this->facture();
        $ligne   = $facture->lignes->first();

        $this->actingAs($this->comptable())->patch("/lignes/{$ligne->id}", [
            'designation' => $ligne->designation, 'quantite' => 1, 'montant' => 4_500_000,
            'remise_type' => 'pourcentage', 'remise_valeur' => 10,
        ]);

        $this->assertDatabaseHas('journal_activites', ['type' => 'facturation']);

        $entree = \App\Models\JournalActivite::where('type', 'facturation')->latest('id')->first();
        $this->assertStringContainsString('remise de 450 000 GNF', $entree->action);
        $this->assertStringContainsString('10 %', $entree->action);
    }

    public function test_retirer_une_remise_est_toujours_possible(): void
    {
        // Même sur une ligne devenue non remisable entre-temps : on ne piège pas l'étude avec
        // une remise qu'elle ne pourrait plus enlever.
        $facture = $this->facture();
        $ligne   = $facture->lignes->first();
        $ligne->update(['remise_type' => TypeRemise::Pourcentage, 'remise_valeur' => 10]);
        $ligne->update(['remise_autorisee' => false]);

        $this->actingAs($this->comptable())
            ->patch("/lignes/{$ligne->id}", [
                'designation' => $ligne->designation, 'quantite' => 1, 'montant' => 4_500_000,
                'remise_type' => null, 'remise_valeur' => null,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $ligne->fresh()->remiseMontant());
    }
}
