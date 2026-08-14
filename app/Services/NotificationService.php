<?php

namespace App\Services;

use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Point de routage unique des notifications métier.
 *
 * Avant ce service, chaque déclencheur choisissait ses destinataires à la main —
 * en pratique toujours `Dossier::ayantsDroit()` (les 4 assignés), ce qui notifiait
 * le formaliste d'une certification qui ne le concerne pas, et ne notifiait
 * personne quand le poste concerné n'était pas assigné sur le dossier.
 *
 * Ici : un événement cible le ou les rôles réellement concernés, avec repli sur
 * le pool du rôle (tous les utilisateurs actifs le portant) si le poste est vide.
 */
class NotificationService
{
    /**
     * Colonne du dossier portant l'assignation de chaque rôle.
     * Comptable et Administrateur n'ont pas de colonne : leur mission est
     * transversale (voir Dossier::scopeVisiblePar), ils ne sont donc joignables
     * que par leur pool.
     */
    private const COLONNE_PAR_ROLE = [
        RoleUtilisateur::Clerc->value      => 'redacteur_id',
        RoleUtilisateur::Reviseur->value   => 'reviseur_id',
        RoleUtilisateur::Notaire->value    => 'notaire_id',
        RoleUtilisateur::Formaliste->value => 'formaliste_id',
    ];

    /**
     * Résout les destinataires d'un événement sur un dossier pour un ou
     * plusieurs rôles.
     *
     * @param  RoleUtilisateur|RoleUtilisateur[]  $roles
     * @param  bool  $repli  Si le poste n'est pas assigné, retomber sur le pool
     *                       du rôle (utilisateurs actifs) + le notaire du dossier.
     *                       Mettre à false pour un événement qui ne concerne que
     *                       la personne nommément assignée (ex. assignation).
     * @return Collection<int, User>
     */
    public function destinataires(Dossier $dossier, RoleUtilisateur|array $roles, bool $repli = true): Collection
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $destinataires = collect();
        $manquants     = [];

        foreach ($roles as $role) {
            $colonne = self::COLONNE_PAR_ROLE[$role->value] ?? null;
            $assigne = $colonne ? $dossier->{str_replace('_id', '', $colonne)} : null;

            if ($colonne && $assigne) {
                $destinataires->push($assigne);
                continue;
            }

            $manquants[] = $role;
        }

        if ($repli && $manquants) {
            $destinataires = $destinataires->merge($this->pool($manquants));

            // Le notaire du dossier est le responsable par défaut : si le poste
            // concerné est vacant, il doit savoir qu'une action est en attente.
            if ($dossier->notaire) {
                $destinataires->push($dossier->notaire);
            }
        }

        return $this->normaliser($destinataires);
    }

    /**
     * Tous les utilisateurs actifs portant l'un des rôles donnés, quel que soit
     * le dossier — pour les événements sans assignation (nouvelle demande) ou
     * comme repli de destinataires().
     *
     * @param  RoleUtilisateur|RoleUtilisateur[]  $roles
     * @return Collection<int, User>
     */
    public function pool(RoleUtilisateur|array $roles): Collection
    {
        $roles = is_array($roles) ? $roles : [$roles];

        return User::query()
            ->where('actif', true)
            ->withRole($roles)
            ->get();
    }

    /**
     * Envoie une notification à chaque destinataire.
     *
     * Chaque envoi est isolé : une panne SMTP sur un destinataire ne doit ni
     * interrompre la boucle, ni faire échouer la requête HTTP qui a déclenché
     * l'événement (l'action métier, elle, a bien eu lieu). L'ordre des canaux
     * défini par CanauxNotification garantit que `database` et `broadcast` sont
     * déjà passés quand `mail` échoue.
     *
     * @param  iterable<User>  $destinataires
     * @param  User|null  $sauf  Acteur déclencheur, à ne pas notifier de sa
     *                           propre action.
     */
    public function envoyer(iterable $destinataires, Notification $notification, ?User $sauf = null): void
    {
        $destinataires = $this->normaliser(collect($destinataires));

        if ($sauf) {
            $destinataires = $destinataires->reject(fn (User $u) => $u->id === $sauf->id);
        }

        foreach ($destinataires as $destinataire) {
            try {
                $destinataire->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * Raccourci : résoudre puis envoyer, le cas d'usage le plus courant.
     *
     * @param  RoleUtilisateur|RoleUtilisateur[]  $roles
     */
    public function notifierRoles(
        Dossier $dossier,
        RoleUtilisateur|array $roles,
        Notification $notification,
        bool $repli = true,
        ?User $sauf = null,
    ): void {
        $this->envoyer($this->destinataires($dossier, $roles, $repli), $notification, $sauf);
    }

    /**
     * @param  Collection<int, User|null>  $users
     * @return Collection<int, User>
     */
    private function normaliser(Collection $users): Collection
    {
        return $users
            ->filter()
            ->filter(fn (User $u) => $u->actif)
            ->unique('id')
            ->values();
    }
}
