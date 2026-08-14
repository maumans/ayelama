<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\BroadcastMessage;

/**
 * Canaux communs à toutes les notifications métier — les 7 classes dupliquaient
 * le même via() à l'identique.
 *
 * La classe hôte doit exposer toArray(object $notifiable): array (déjà le cas
 * partout, c'est ce qui alimente la table `notifications` et le dropdown).
 */
trait CanauxNotification
{
    public function via(object $notifiable): array
    {
        // Ordre volontaire : `database` puis `broadcast`, `mail` en dernier.
        // Laravel envoie les canaux dans cet ordre — une panne SMTP ne doit donc
        // jamais empêcher l'historique en base ni le temps réel.
        $canaux = ['database', 'broadcast'];

        // filled($notifiable->email) : un compte sans email ferait lever une
        // exception au moment de l'envoi, qui aurait masqué les canaux suivants.
        if ($notifiable->notifications_email && filled($notifiable->email)) {
            $canaux[] = 'mail';
        }

        return $canaux;
    }

    /**
     * Broadcast synchrone.
     *
     * `BroadcastNotificationCreated` implémente ShouldBroadcast (donc queué) :
     * avec QUEUE_CONNECTION=database, aucune notification temps réel n'arrivait
     * si aucun `queue:work` ne tournait — la notification était bien en base mais
     * ni toast ni badge, d'où une impression d'intermittence.
     *
     * `onConnection('sync')` fait exécuter le BroadcastEvent immédiatement
     * (voir BroadcastManager::queue(), qui lit $event->connection), sans rien
     * changer au reste de la configuration de queue.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
