<?php

namespace App\Policies;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Revision;
use App\Models\User;

class RevisionPolicy
{
    public function view(User $user, Revision $revision): bool
    {
        if (!$user->actif) return false;

        $dossier = $revision->dossier;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier?->redacteur_id === $user->id
            || $dossier?->reviseur_id === $user->id
            || $dossier?->notaire_id === $user->id;
    }

    public function update(User $user, Revision $revision): bool
    {
        if (!$user->actif) return false;
        if ($revision->dossier?->etape !== EtapeDossier::Revision) return false;

        $dossier = $revision->dossier;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier?->reviseur_id === $user->id
            || $dossier?->notaire_id === $user->id;
    }

    public function valider(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision) && $revision->estValidable();
    }

    public function renvoyer(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision) && $revision->nombreNonConformes() > 0;
    }
}
