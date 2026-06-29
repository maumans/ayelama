<?php

namespace App\Policies;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\Revision;
use App\Models\User;

class RevisionPolicy
{
    public function view(User $user, Revision $revision): bool
    {
        return $user->actif;
    }

    public function update(User $user, Revision $revision): bool
    {
        if (!$user->actif) return false;

        return in_array($user->role, [
            RoleUtilisateur::Reviseur,
            RoleUtilisateur::Notaire,
            RoleUtilisateur::Administrateur,
        ]) && $revision->dossier?->etape === EtapeDossier::Revision;
    }

    public function valider(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision) && $revision->estValidable();
    }

    public function renvoyer(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision);
    }
}
