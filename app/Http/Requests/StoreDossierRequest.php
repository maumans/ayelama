<?php

namespace App\Http\Requests;

use App\Enums\CategorieActe;
use App\Enums\RoleUtilisateur;
use App\Rules\UtilisateurPossedeRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Dossier::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'type_acte_id'  => ['required', 'integer', 'exists:types_actes,id'],
            'objet'         => ['required', 'string', 'min:10', 'max:500'],
            'valeur'        => ['nullable', 'numeric', 'min:0'],
            'echeance'      => ['nullable', 'date', 'after:today'],
            'urgent'        => ['boolean'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'reviseur_id'   => ['nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Reviseur)],
            'notaire_id'    => ['required', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Notaire)],
            'formaliste_id' => ['nullable', 'integer', 'exists:users,id', new UtilisateurPossedeRole(RoleUtilisateur::Formaliste)],
            'donnees'       => ['nullable', 'array'],
            ...self::partiesRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'type_acte_id.required'  => 'Le type d\'acte est obligatoire.',
            'type_acte_id.exists'    => 'Le type d\'acte sélectionné n\'existe pas.',
            'objet.required'         => 'L\'objet du dossier est obligatoire.',
            'objet.min'              => 'L\'objet doit contenir au moins 10 caractères.',
            'notaire_id.required'    => 'Le notaire en charge est obligatoire.',
            'echeance.after'         => 'L\'échéance doit être une date future.',
        ];
    }

    /**
     * Règles de validation du tableau `parties`, réutilisées par
     * DossierController::updateQuestionnaire() pour la synchronisation des
     * Partie à l'édition du questionnaire.
     */
    public static function partiesRules(): array
    {
        return [
            'parties'              => ['nullable', 'array'],
            'parties.*.nom'        => ['required_with:parties', 'string', 'max:200'],
            'parties.*.role'       => ['required_with:parties', 'string', 'max:100'],
            'parties.*.client_id'  => ['nullable', 'integer', 'exists:clients,id'],
            'parties.*.cni'        => ['nullable', 'string', 'max:50'],
            'parties.*.telephone'  => ['nullable', 'string', 'max:20'],
            'parties.*.adresse'    => ['nullable', 'string', 'max:500'],
            'parties.*.email'      => ['nullable', 'email', 'max:200'],
        ];
    }
}
