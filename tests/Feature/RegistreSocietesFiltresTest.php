<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Societe;
use App\Models\TypeActe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le registre des sociétés : filtres de lacunes, et parité scope ↔ prédicat (2026-10-05).
 *
 * L'en-tête annonçait « N immatriculées sans RCCM » en texte simple, sans moyen de voir
 * lesquelles : un chiffre sans sortie. Les rendre cliquables supposait de traduire en SQL un
 * prédicat jusque-là évalué en PHP — `Societe::immatriculeeSansRccm()` — parce que la liste est
 * **paginée** et qu'un filtre appliqué après pagination fausserait les pages.
 *
 * ⚠️ **Deux formulations d'une même règle, donc un test de parité.** Le prédicat sert à
 * l'affichage d'une ligne déjà chargée, le scope à filtrer et compter. Qu'ils divergent, et
 * l'en-tête annoncerait un nombre que le filtre ne retrouverait pas.
 */
class RegistreSocietesFiltresTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

        return $user->fresh();
    }

    private function typeActe(): TypeActe
    {
        return TypeActe::firstOrCreate(
            ['code' => 'SOC-SARLU'],
            ['label' => 'Constitution SARLU', 'categorie' => CategorieActe::Societe, 'delai_jours' => 30, 'actif' => true],
        );
    }

    private function societe(array $attributs = []): Societe
    {
        return Societe::create(array_merge([
            'denomination' => 'Société ' . fake()->unique()->numerify('###'),
            'forme'        => 'SARLU',
        ], $attributs));
    }

    private function dossier(Societe $societe, EtapeDossier $etape): Dossier
    {
        return Dossier::create([
            'reference'    => 'SOC-2026-' . fake()->unique()->numerify('####'),
            'type_acte_id' => $this->typeActe()->id,
            'societe_id'   => $societe->id,
            'etape'        => $etape,
            'redacteur_id' => User::factory()->create()->id,
            'objet'        => 'Dossier de test du registre des sociétés',
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function lignes($reponse): array
    {
        return $reponse->viewData('page')['props']['societes']['data'] ?? [];
    }

    private function stats($reponse): array
    {
        return $reponse->viewData('page')['props']['stats'] ?? [];
    }

    // ═══ Parité entre les deux formulations ══════════════════════════════

    /**
     * Le scope et le prédicat doivent s'accorder **fiche par fiche**, pas seulement en nombre :
     * deux erreurs qui se compensent donneraient le même total.
     */
    public function test_le_scope_dit_la_meme_chose_que_le_predicat(): void
    {
        // Immatriculée sans RCCM : un dossier au-delà des formalités, pas de numéro.
        $this->dossier($sansRccm = $this->societe(['rccm_numero' => null]), EtapeDossier::Expedition);
        // Même situation mais le numéro est là.
        $this->dossier($this->societe(['rccm_numero' => 'GN.TCC.2021.B09995']), EtapeDossier::Expedition);
        // Pas de numéro, mais le dossier n'a pas atteint les formalités : rien à reprocher.
        $this->dossier($this->societe(['rccm_numero' => null]), EtapeDossier::Edition);
        // Aucune société rattachée à un dossier.
        $this->societe(['rccm_numero' => null]);

        $parScope = Societe::manqueRccm()->pluck('id')->sort()->values()->all();

        $parPredicat = Societe::with('dossiers')->get()
            ->filter(fn (Societe $s) => $s->immatriculeeSansRccm())
            ->pluck('id')->sort()->values()->all();

        $this->assertSame($parPredicat, $parScope, 'Le scope et le prédicat ont divergé.');
        $this->assertSame([$sansRccm->id], $parScope);
    }

    /** Les étapes « au-delà des formalités » se dérivent de l'enum, elles ne sont pas recopiées. */
    public function test_les_etapes_apres_formalites_viennent_de_lenum(): void
    {
        $this->assertSame(['expedition', 'cloture'], Societe::etapesApresFormalites());
    }

    // ═══ Les filtres de lacunes ══════════════════════════════════════════

    public function test_le_filtre_sans_rccm_isole_les_fiches_concernees(): void
    {
        $this->dossier($cible = $this->societe(['denomination' => 'Sans numéro', 'rccm_numero' => null]), EtapeDossier::Expedition);
        $this->dossier($this->societe(['denomination' => 'Avec numéro', 'rccm_numero' => 'GN-CON-2020-B-1']), EtapeDossier::Expedition);

        $reponse = $this->actingAs($this->clerc())->get('/societes?manque=rccm')->assertOk();

        $lignes = $this->lignes($reponse);
        $this->assertCount(1, $lignes);
        $this->assertSame($cible->id, $lignes[0]['id']);
        $this->assertSame(1, $this->stats($reponse)['manqueRccm'], "L'en-tête doit annoncer ce que le filtre montre.");
    }

    public function test_le_filtre_sans_nif(): void
    {
        $this->societe(['denomination' => 'Sans NIF', 'nif' => null]);
        $this->societe(['denomination' => 'NIF vide', 'nif' => '']);
        $this->societe(['denomination' => 'Avec NIF', 'nif' => '000123456']);

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/societes?manque=nif'));

        $this->assertCount(2, $lignes, 'Une chaîne vide est une absence, comme NULL.');
    }

    public function test_le_filtre_sans_date_dimmatriculation(): void
    {
        $this->societe(['denomination' => 'Sans date']);
        $this->societe(['denomination' => 'Avec date', 'date_constitution' => '2021-05-10']);

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/societes?manque=date_constitution'));

        $this->assertCount(1, $lignes);
        $this->assertSame('Sans date', $lignes[0]['denomination']);
    }

    public function test_le_filtre_sans_dossier_constitutif(): void
    {
        $externe = $this->societe(['denomination' => 'Constituée ailleurs']);
        $interne = $this->societe(['denomination' => 'Constituée ici']);
        $interne->update(['dossier_id' => $this->dossier($interne, EtapeDossier::Cloture)->id]);

        $lignes = $this->lignes($this->actingAs($this->clerc())->get('/societes?manque=sans_dossier'));

        $this->assertCount(1, $lignes);
        $this->assertSame($externe->id, $lignes[0]['id']);
    }

    /** Une lacune inconnue ne filtre rien plutôt que de vider la liste en silence. */
    public function test_une_lacune_inconnue_est_ignoree(): void
    {
        $this->societe();
        $this->societe();

        $reponse = $this->actingAs($this->clerc())->get('/societes?manque=nimportequoi')->assertOk();

        $this->assertCount(2, $this->lignes($reponse));
        $this->assertSame('', $reponse->viewData('page')['props']['filters']['manque']);
    }

    // ═══ Ce que la ligne porte ═══════════════════════════════════════════

    public function test_la_ligne_porte_le_siege_le_capital_et_les_contacts(): void
    {
        $this->societe([
            'denomination'      => 'Agrobusiness Guinée',
            'siege_quartier'    => 'Madina',
            'siege_commune'     => 'Matam',
            'siege_ville'       => 'Conakry',
            'capital_chiffres'  => 50000000,
            'email_societe'     => 'contact@abg.gn',
            'telephone_societe' => '621 90 90 03',
        ]);

        $ligne = $this->lignes($this->actingAs($this->clerc())->get('/societes'))[0];

        $this->assertSame('Madina, Matam, Conakry', $ligne['siege']);
        $this->assertSame('contact@abg.gn', $ligne['email_societe']);
        $this->assertSame('621 90 90 03', $ligne['telephone_societe']);
        $this->assertEquals(50000000, $ligne['capital_chiffres']);
    }

    /**
     * Les colonnes de liquidation ne s'affichent **que si la page a de quoi les remplir**.
     *
     * Mesuré avant la passe : vides sur 14 fiches sur 15. Les lier au filtre plutôt qu'aux
     * données aurait caché l'unique société en liquidation dans la vue par défaut.
     */
    public function test_les_colonnes_de_liquidation_suivent_les_donnees(): void
    {
        $this->societe(['denomination' => 'Société active']);

        $reponse = $this->actingAs($this->clerc())->get('/societes')->assertOk();
        $this->assertFalse($reponse->viewData('page')['props']['afficherLiquidation']);

        $this->societe([
            'denomination'   => 'Société en liquidation',
            'statut'         => \App\Enums\StatutSociete::EnLiquidation,
            'dissolution_at' => now()->subMonth(),
        ]);

        $reponse = $this->actingAs($this->clerc())->get('/societes')->assertOk();
        $this->assertTrue(
            $reponse->viewData('page')['props']['afficherLiquidation'],
            'Une échéance de liquidation existe : la colonne doit réapparaître.',
        );
    }

    /** Le registre renvoie vers les dossiers de la société, et ce filtre doit exister. */
    public function test_la_liste_des_dossiers_filtre_par_societe(): void
    {
        $societe = $this->societe();
        $leSien = $this->dossier($societe, EtapeDossier::Edition);
        $this->dossier($this->societe(), EtapeDossier::Edition);

        $reponse = $this->actingAs($this->clerc())->get("/dossiers?societe={$societe->id}")->assertOk();

        $dossiers = $reponse->viewData('page')['props']['dossiers']['data'] ?? [];
        $this->assertCount(1, $dossiers);
        $this->assertSame($leSien->reference, $dossiers[0]['reference']);
    }
}
