<?php

namespace Tests\Feature;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\Partie;
use App\Models\TypeActe;
use App\Models\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La nature d'une partie atteint la base, et les pièces exigées la suivent (2026-09-30).
 *
 * **Le trou.** `partiesPayload.js` envoyait `type_personne` pour les blocs **répétables** et
 * l'oubliait pour les **sections scalaires**, et rien ne le dérivait côté serveur. Mesuré avant
 * correction : **39 parties sur 41 avaient `type_personne` à NULL**, dont les 12 `associe_unique`.
 *
 * La conséquence n'était pas cosmétique. `Partie::piecesRequisesPour()` retombe sur `'physique'`
 * quand la colonne est vide : l'écran affichait le jeu de pièces d'une personne morale — il le
 * calcule depuis `formValues`, où la valeur existe bien — pendant que le serveur en exigeait un
 * autre. Deux vérités sur la même partie, dont une invisible.
 *
 * ⚠️ Ces tests visent le **contrat serveur**, pas le formulaire : ils postent ce que le frontend
 * envoie désormais. Le versant JS est tenu par `tools/verifier-sections-questionnaire.mjs`.
 */
class NaturePersonnePartieTest extends TestCase
{
    use RefreshDatabase;

    private function clerc(): User
    {
        $user = User::factory()->create(['actif' => true]);
        $user->syncRoles([RoleUtilisateur::Administrateur]);

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

    /** @param array<string, mixed> $partie */
    private function creerDossierAvec(array $partie): void
    {
        $this->actingAs($this->clerc())->post('/dossiers', [
            'type_acte_id' => $this->typeActe()->id,
            'objet'        => 'Constitution SARLU pour vérifier la nature de la partie',
            'notaire_id'   => $this->notaire()->id,
            'donnees'      => ['soc.denomination' => 'Nature SARLU'],
            'parties'      => [$partie],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_une_section_scalaire_morale_persiste_sa_nature(): void
    {
        $this->creerDossierAvec([
            'nom'             => 'Cabinet TEDSOM SARLU',
            'role'            => 'associe_unique',
            'type_personne'   => 'morale',
            'donnees_prefixe' => 'pp',
        ]);

        $partie = Partie::where('role', 'associe_unique')->sole();

        $this->assertSame(
            'morale',
            $partie->type_personne,
            "La nature envoyée par une section scalaire doit être persistée : sans elle, le serveur "
            . "traite une société comme une personne physique et n'exige pas les bonnes pièces.",
        );
    }

    public function test_le_jeu_de_pieces_suit_la_nature(): void
    {
        $this->creerDossierAvec([
            'nom'             => 'Cabinet TEDSOM SARLU',
            'role'            => 'associe_unique',
            'type_personne'   => 'morale',
            'donnees_prefixe' => 'pp',
        ]);

        $pieces = array_keys(Partie::where('role', 'associe_unique')->sole()->piecesRequisesDefinition());

        $this->assertContains('statuts', $pieces, "Une société associée doit fournir ses statuts.");
        $this->assertContains('declaration_rccm', $pieces);
        $this->assertNotContains(
            'certificat_residence',
            $pieces,
            "Un certificat de résidence n'a aucun sens pour une personne morale — c'est le jeu "
            . 'physique qui s'."'".'appliquait tant que la colonne restait nulle.',
        );
    }

    public function test_une_section_scalaire_physique_reste_physique(): void
    {
        $this->creerDossierAvec([
            'nom'             => 'Mamadou BAH',
            'role'            => 'associe_unique',
            'type_personne'   => 'physique',
            'donnees_prefixe' => 'pp',
        ]);

        $pieces = array_keys(Partie::where('role', 'associe_unique')->sole()->piecesRequisesDefinition());

        $this->assertSame('physique', Partie::where('role', 'associe_unique')->sole()->type_personne);
        $this->assertContains('cni', $pieces);
    }

    /**
     * La fiche prime sur la saisie — décision #33, la fiche client est la source de vérité de
     * l'identité, et le questionnaire n'en est qu'une projection.
     */
    public function test_la_nature_dune_fiche_liee_fait_foi(): void
    {
        $societe = Client::create([
            'type'         => 'morale',
            'statut'       => 'prospect',
            'denomination' => 'Cabinet TEDSOM SARLU',
        ]);

        $this->creerDossierAvec([
            'nom'             => 'Cabinet TEDSOM SARLU',
            'role'            => 'associe_unique',
            'client_id'       => $societe->id,
            'type_personne'   => 'morale',
            'donnees_prefixe' => 'pp',
        ]);

        $this->assertSame('morale', Partie::where('role', 'associe_unique')->sole()->type_personne);
    }

    /** Non-régression : une partie sans nature déclarée reste acceptée, et vaut physique. */
    public function test_une_partie_sans_nature_reste_acceptee(): void
    {
        $this->creerDossierAvec([
            'nom'             => 'Mamadou BAH',
            'role'            => 'associe_unique',
            'donnees_prefixe' => 'pp',
        ]);

        $partie = Partie::where('role', 'associe_unique')->sole();

        $this->assertNull($partie->type_personne);
        $this->assertContains(
            'cni',
            array_keys($partie->piecesRequisesDefinition()),
            'Sans nature déclarée, le repli documenté est « physique ».',
        );
    }
}
