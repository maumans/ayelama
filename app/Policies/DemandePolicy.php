<?php

namespace App\Policies;

use App\Models\Demande;
use App\Models\Dossier;
use App\Models\User;

class DemandePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('create', Dossier::class);
    }

    public function view(User $user, Demande $demande): bool
    {
        return $user->can('create', Dossier::class);
    }

    public function create(User $user): bool
    {
        return $user->can('create', Dossier::class);
    }

    public function update(User $user, Demande $demande): bool
    {
        return $user->can('create', Dossier::class);
    }
}
