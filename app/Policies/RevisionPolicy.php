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

    /**
     * Droit de valider une certification — **permission seule**.
     *
     * Les préconditions d'état (grille complète, aucun point non conforme) ont été
     * retirées d'ici : mêlées à l'autorisation, elles renvoyaient un « Accès refusé » 403
     * à un utilisateur qui avait pourtant tous les droits, sans dire ce qui manquait.
     * Constaté en usage réel : un administrateur bloqué sur SOC-2026-0010 sans explication.
     *
     * L'état est désormais vérifié par `RevisionController::valider()`, qui renvoie une
     * erreur de validation nommant la cause. Une permission répond « qui », pas « quand
     * l'objet est prêt ».
     */
    public function valider(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision);
    }

    public function renvoyer(User $user, Revision $revision): bool
    {
        return $this->update($user, $revision);
    }
}
