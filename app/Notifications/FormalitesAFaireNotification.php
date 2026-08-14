<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le dossier vient d'entrer à l'étape Formalités. Destinée au formaliste, qui
 * n'était averti par rien jusqu'ici : il devait découvrir le travail à faire en
 * consultant la liste des formalités.
 *
 * À ne pas confondre avec FormaliteUrgenteNotification, qui alerte sur le délai
 * d'une formalité déjà en cours (commande planifiée ayelema:alerter-formalites).
 */
class FormalitesAFaireNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Formalités à engager — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) est signé et passe aux formalités.")
            ->action('Ouvrir les formalités', url("/dossiers/{$this->dossier->reference}?tab=formalites"))
            ->line('Merci d\'engager les démarches auprès des organismes concernés.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'formalites_a_faire',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}?tab=formalites",
            'message' => "Formalités à engager : {$this->dossier->reference}",
        ];
    }
}
