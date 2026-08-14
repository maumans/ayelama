<?php

namespace App\Policies;

use App\Enums\RoleUtilisateur;
use App\Models\Client;
use App\Models\User;

/**
 * Fiches clients du répertoire.
 *
 * Aucune autorisation n'existait sur `ClientController::store/update/autocomplete` — et
 * aucune policy non plus. C'était le manque le plus discret et le plus lourd de
 * conséquences : depuis que la fiche client est la source de vérité de l'identité
 * (décision #33), `update()` déclenche `ClientProjectionService::reprojeterDossiersDuClient()`,
 * qui réécrit le questionnaire **et régénère les actes** de tous les dossiers liés non
 * clôturés. N'importe quel compte authentifié pouvait donc altérer les actes de dossiers
 * auxquels il n'a aucun accès.
 *
 * Restriction par **rôle** et non par dossier, volontairement : un client peut être lié
 * aux dossiers de plusieurs clercs, exiger un droit sur chacun rendrait toute correction
 * d'identité impraticable. Le bon niveau est « qui a vocation à tenir le répertoire ».
 */
class ClientPolicy
{
    /** Recherche/autocomplétion : tout utilisateur actif en a besoin pour remplir un dossier. */
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Client $client): bool
    {
        return $user->actif;
    }

    /**
     * Créer une fiche : les rôles qui ouvrent des dossiers (Clerc, Notaire,
     * Administrateur) — c'est au moment de la saisie d'un dossier qu'une fiche naît.
     */
    public function create(User $user): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }

    /**
     * Modifier une fiche : mêmes rôles que la création.
     *
     * Conséquence assumée : un Formaliste ou un Comptable ne corrige pas une identité —
     * il signale l'erreur au clerc ou au notaire. Vu l'effet de bord (régénération des
     * actes de tous les dossiers liés), c'est la bonne prudence.
     */
    public function update(User $user, Client $client): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }
}
