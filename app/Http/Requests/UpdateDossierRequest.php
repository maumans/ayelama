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
            // Questionnaire — champs scalaires et blocs répétables (associés, gérants…)
            'donnees'         => ['sometimes', 'nullable', 'array'],
            'donnees.*'       => ['nullable'],
            'donnees.*.*.* '  => ['nullable', 'string', 'max:1000'],
        ];
    }
}
