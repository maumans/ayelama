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
        return $user->actif && $this->estAssigne($user, $dossier);
    }

    public function create(User $user): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }

    public function update(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return match ($dossier->etape) {
            EtapeDossier::Initialisation,
            EtapeDossier::Edition        => $dossier->redacteur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::Revision       => $dossier->reviseur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::SignatureClient,
            EtapeDossier::SignatureNotaire => $dossier->redacteur_id === $user->id
                || $dossier->notaire_id === $user->id,
            EtapeDossier::Formalites,
            EtapeDossier::Expedition     => $dossier->formaliste_id === $user->id
                || $dossier->notaire_id === $user->id,
            default                      => false,
        };
    }

    public function reassigner(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->notaire_id === $user->id;
    }

    public function delete(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        return $dossier->notaire_id === $user->id
            && in_array($dossier->etape, [EtapeDossier::Initialisation, EtapeDossier::Edition], true);
    }

    public function avancer(User $user, Dossier $dossier): bool
    {
        return $this->update($user, $dossier);
    }

    public function reviser(User $user, Dossier $dossier): bool
    {
        if (!$user->actif || $dossier->etape !== EtapeDossier::Revision) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->reviseur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function genererDocuments(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;

        if (!in_array($dossier->etape, [EtapeDossier::Initialisation, EtapeDossier::Edition, EtapeDossier::Revision], true)) {
            return false;
        }

        return $dossier->redacteur_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function gererFormalites(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if (!in_array($dossier->etape, [EtapeDossier::Formalites, EtapeDossier::Expedition], true)) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->formaliste_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    public function gererFacturation(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;

        return $user->hasAnyRole(RoleUtilisateur::peuventGererFacturation());
    }

    /**
     * Dépôt de la version signée/cachetée d'un document (retour du circuit papier réel) —
     * verrouille définitivement le document. Restreint au Notaire (certifie l'acte) et au
     * Formaliste (gère matériellement l'aller-retour physique), pas au Rédacteur/Réviseur.
     */
    public function cloturerDocuments(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($user->hasRole(RoleUtilisateur::Administrateur)) return true;
        if (!in_array($dossier->etape, [EtapeDossier::Expedition, EtapeDossier::Cloture], true)) return false;

        return $dossier->notaire_id === $user->id || $dossier->formaliste_id === $user->id;
    }

    public function genererCourriers(User $user, Dossier $dossier): bool
    {
        if (!$user->actif) return false;
        if ($dossier->etape !== EtapeDossier::Expedition) return false;

        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $dossier->formaliste_id === $user->id
            || $dossier->notaire_id === $user->id;
    }

    private function estAssigne(User $user, Dossier $dossier): bool
    {
        return $user->hasRole(RoleUtilisateur::Administrateur)
            || $user->hasRole(RoleUtilisateur::Comptable)
            || $dossier->redacteur_id === $user->id
            || $dossier->reviseur_id === $user->id
            || $dossier->notaire_id === $user->id
            || $dossier->formaliste_id === $user->id;
    }
}
