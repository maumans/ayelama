<?php

namespace App\Http\Requests;

use App\Enums\RoleUtilisateur;
use App\Rules\UtilisateurPossedeRole;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('dossier')) ?? false;
    }

    /**
     * Informations générales du dossier uniquement.
     *
     * Deux blocs en ont été retirés le 2026-08-04 :
     *
     * - `date_signature_client` / `date_signature_notaire` — acceptés en
     *   `sometimes|nullable` sans aucun contrôle d'étape, ils étaient renseignables dès
     *   l'Édition (avant toute certification) et **effaçables** par le formaliste une fois
     *   le dossier passé en Formalités. Ils relèvent désormais de
     *   `DossierController::enregistrerSignatures()`, restreint à l'étape Signature.
     *
     * - `donnees` — le questionnaire vit dans la table `questionnaires`, pas sur
     *   `dossiers`, et n'est pas dans `Dossier::$fillable` : ces règles n'écrivaient donc
     *   rien. Les laisser laissait croire que cette route pouvait modifier le
     *   questionnaire, alors que c'est le rôle de `updateQuestionnaire()`.
     */
    public function rules(): array
    {
        return [
            'objet'           => ['sometimes', 'string', 'min:10', 'max:500'],
            'valeur'          => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'echeance'        => ['sometimes', 'nullable', 'date'],
            'urgent'          => ['sometimes', 'boolean'],
            'notes'           => ['sometimes', 'nullable', 'string', 'max:2000'],
            'notaire_id'      => ['sometimes', 'nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Notaire)],
            'reviseur_id'     => ['sometimes', 'nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Reviseur)],
            'formaliste_id'   => ['sometimes', 'nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Formaliste)],
        ];
    }
}
