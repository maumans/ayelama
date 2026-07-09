<?php

namespace App\Policies;

use App\Enums\RoleUtilisateur;
use App\Models\Courrier;
use App\Models\User;

class CourrierPolicy
{
    public function create(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Courrier $courrier): bool
    {
        if (!$user->actif) return false;

        return $this->peutGerer($user, $courrier);
    }

    public function update(User $user, Courrier $courrier): bool
    {
        if (!$user->actif) return false;

        return $this->peutGerer($user, $courrier);
    }

    public function delete(User $user, Courrier $courrier): bool
    {
        return $this->update($user, $courrier);
    }

    private function peutGerer(User $user, Courrier $courrier): bool
    {
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        $dossier = $courrier->dossier;

        if (!$dossier) {
            return $courrier->redacteur_id === $user->id;
        }

        return $dossier->redacteur_id === $user->id
            || $dossier->reviseur_id === $user->id
            || $dossier->notaire_id === $user->id
            || $dossier->formaliste_id === $user->id;
    }
}
