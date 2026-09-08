<?php

namespace App\Policies;

use App\Enums\RoleUtilisateur;
use App\Models\Lieu;
use App\Models\User;

/**
 * Le référentiel des lieux se lit largement et ne s'écrit que par ceux qui tiennent les
 * référentiels — même partage que {@see SocietePolicy} et {@see ClientPolicy}.
 *
 * ⚠️ L'écriture est **exclue du formulaire public d'intake** : ce formulaire n'est pas authentifié,
 * donc aucune de ces abilities n'y est atteignable. Un tiers ne doit pas pouvoir peupler le
 * référentiel de l'étude, et l'intake se contente d'une saisie libre signalée quand un lieu manque.
 */
class LieuPolicy
{
    /** La cascade est lue par tout utilisateur actif : elle alimente les formulaires de saisie. */
    public function viewAny(User $user): bool
    {
        return $user->actif;
    }

    /**
     * Créer un lieu manquant, depuis un formulaire de saisie.
     *
     * Ouvert aux rôles qui ouvrent des dossiers : c'est en saisissant un client réel qu'on découvre
     * un quartier absent, et bloquer là attendrait un administrateur pour rien. Le lieu créé est
     * marqué à vérifier, et l'écran d'administration le reprend.
     */
    public function create(User $user): bool
    {
        // Même ensemble que ClientPolicy et DossierPolicy : `peuventOuvrir()` le nomme une fois
        // pour toutes, plutôt que de le recopier policy par policy.
        return $user->actif && $user->hasAnyRole(RoleUtilisateur::peuventOuvrir());
    }

    /**
     * Corriger ou désactiver un lieu — **administrateur seul**.
     *
     * Asymétrie voulue : ajouter un lieu manquant est un geste de saisie, corriger le référentiel
     * est un acte d'administration. Renommer « Ratoma » depuis un formulaire de dossier changerait
     * la liste proposée à toute l'étude.
     */
    public function update(User $user, Lieu $lieu): bool
    {
        return $user->actif && $user->hasRole(RoleUtilisateur::Administrateur);
    }
}
