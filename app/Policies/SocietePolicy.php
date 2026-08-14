<?php

namespace App\Policies;

use App\Enums\RoleUtilisateur;
use App\Models\Societe;
use App\Models\User;

/**
 * Fiches sociétés du registre.
 *
 * Même raisonnement que {@see ClientPolicy}, et pour la même raison : la fiche société est
 * la source de vérité des données de la personne morale, et `update()` peut se répercuter
 * sur les dossiers qui la référencent. La restriction porte sur le **rôle** et non sur le
 * dossier — une société est liée aux dossiers de plusieurs clercs (sa constitution, puis
 * chacune de ses modifications), exiger un droit sur chacun rendrait toute correction
 * impraticable.
 *
 * Pas de `delete` : supprimer une fiche référencée par des dossiers casserait leur
 * projection `soc.*`, donc leurs actes. Une société qui n'a plus lieu d'être proposée est
 * désactivée (`actif = false`), pas supprimée.
 */
class SocietePolicy
{
    /** Recherche/autocomplétion : nécessaire à tout utilisateur qui remplit un dossier. */
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    public function view(User $user, Societe $societe): bool
    {
        return $user->actif;
    }

    /**
     * Créer une fiche : les rôles qui ouvrent des dossiers (Clerc, Notaire, Administrateur).
     * C'est en constituant une société, ou en ouvrant une modification sur une société
     * absente du registre, qu'une fiche naît.
     */
    public function create(User $user): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }

    /** Modifier une fiche : mêmes rôles que la création. */
    public function update(User $user, Societe $societe): bool
    {
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }
}
