<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDossierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->actif ?? false;
    }

    public function rules(): array
    {
        return [
            'objet'         => ['sometimes', 'string', 'min:10', 'max:500'],
            'valeur'        => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'echeance'      => ['sometimes', 'nullable', 'date'],
            'reviseur_id'   => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'formaliste_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
