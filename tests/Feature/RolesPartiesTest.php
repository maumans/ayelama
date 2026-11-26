<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDossierRequest;
use App\Models\Partie;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Le rôle d'une partie est une **liste fermée** depuis le 2026-11-26.
 *
 * Il était une chaîne libre (`string|max:100`) : la carte « Clients du dossier » de l'assistant et
 * le modal « Ajouter une personne » de la fiche dossier en tiraient des parties « témoin » ou
 * « accompagnateur ». Sans emplacement dans `donnees`, elles n'atteignaient aucun acte ni aucune
 * checklist de pièces — 0 en base au moment du retrait. Les deux entrées ont disparu ; ce test
 * empêche qu'une troisième les fasse renaître en silence.
 */
class RolesPartiesTest extends TestCase
{
    private function valide(string $role): bool
    {
        return ! Validator::make(
            ['parties' => [['nom' => 'Ibrahima DIALLO', 'role' => $role]]],
            StoreDossierRequest::partiesRules(),
        )->errors()->has('parties.0.role');
    }

    public function test_un_role_libre_est_refuse(): void
    {
        foreach (['temoin', 'accompagnateur', 'Témoin', ''] as $role) {
            $this->assertFalse($this->valide($role), "« {$role} » ne doit pas être accepté comme rôle.");
        }
    }

    public function test_tout_role_connu_est_accepte(): void
    {
        foreach (Partie::rolesConnus() as $role) {
            $this->assertTrue($this->valide($role), "« {$role} » doit rester accepté.");
        }
    }

    /**
     * Le représentant n'est pas un `clientRole` du questionnaire, mais le payload l'émet
     * (`ROLE_REPRESENTANT` dans partiesPayload.js) : l'oublier ferait échouer toute procuration.
     */
    public function test_le_role_du_representant_est_connu(): void
    {
        $this->assertContains('mandataire', Partie::rolesConnus());
    }
}
