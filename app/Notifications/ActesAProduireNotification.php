<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le dossier vient d'entrer en Édition des actes : les actes sont générés, le rédacteur doit les
 * relire et les corriger avant la certification (retour R22, 2026-11-16 — cette entrée ne notifiait
 * personne). Le renvoi en correction a sa propre notification (DossierRenvoyeNotification).
 */
class ActesAProduireNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Actes à relire — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) est constitué : ses actes ont été générés.")
            ->action('Ouvrir les actes', url("/dossiers/{$this->dossier->reference}?tab=documents"))
            ->line('Merci de les relire et de les corriger avant de les envoyer en certification.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'actes_a_produire',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}?tab=documents",
            'message' => "Actes à relire : {$this->dossier->reference}",
        ];
    }
}
