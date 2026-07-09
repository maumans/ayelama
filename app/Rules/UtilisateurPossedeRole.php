<?php

namespace App\Rules;

use App\Enums\RoleUtilisateur;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UtilisateurPossedeRole implements ValidationRule
{
    public function __construct(private readonly RoleUtilisateur $role) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return; // laisser 'required'/'nullable' gérer l'absence de valeur
        }

        $utilisateur = User::find($value);

        if (!$utilisateur || !$utilisateur->actif || !$utilisateur->hasRole($this->role)) {
            $fail("L'utilisateur sélectionné ne possède pas le rôle « {$this->role->label()} ».");
        }
    }
}
