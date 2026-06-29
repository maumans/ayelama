<?php

namespace App\Policies;

use App\Enums\EtapeDossier;
use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\User;

class DossierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Dossier $dossier): bool
    {
        return $user->actif;
    }

    public function create(User $user): bool
    {
        return $user->actif && in_array($user->role, [
            RoleUtilisateur::Clerc,
            RoleUtilisateur::Notaire,
            RoleUtilisateur::Administrateur,
        ]);
    }

    public function update(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;

        return match ($dossier->etape) {
            EtapeDossier::Initialisation,
            EtapeDossier::Edition        => $user->role === RoleUtilisateur::Administrateur
                || $dossier->redacteur_id === $user->id
                || $user->role === RoleUtilisateur::Notaire,
            EtapeDossier::Revision       => $user->role === RoleUtilisateur::Reviseur
                || $user->role === RoleUtilisateur::Notaire
                || $user->role === RoleUtilisateur::Administrateur,
            EtapeDossier::SignatureClient,
            EtapeDossier::SignatureNotaire => $user->role === RoleUtilisateur::Notaire
                || $user->role === RoleUtilisateur::Administrateur,
            EtapeDossier::Formalites,
            EtapeDossier::Expedition     => $user->role === RoleUtilisateur::Formaliste
                || $user->role === RoleUtilisateur::Notaire
                || $user->role === RoleUtilisateur::Administrateur,
            default                      => $user->role === RoleUtilisateur::Administrateur,
        };
    }

    public function delete(User $user, Dossier $dossier): bool
    {
        return $user->actif && in_array($user->role, [
            RoleUtilisateur::Notaire,
            RoleUtilisateur::Administrateur,
        ]);
    }

    public function avancer(User $user, Dossier $dossier): bool
    {
        return $this->update($user, $dossier);
    }

    public function reviser(User $user, Dossier $dossier): bool
    {
        return $user->actif && in_array($user->role, [
            RoleUtilisateur::Reviseur,
            RoleUtilisateur::Notaire,
            RoleUtilisateur::Administrateur,
        ]) && $dossier->etape === EtapeDossier::Revision;
    }

    public function gererFormalites(User $user, Dossier $dossier): bool
    {
        return $user->actif && in_array($user->role, [
            RoleUtilisateur::Formaliste,
            RoleUtilisateur::Notaire,
            RoleUtilisateur::Administrateur,
        ]);
    }
}
