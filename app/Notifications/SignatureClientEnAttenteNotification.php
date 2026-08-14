<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureClientEnAttenteNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Signature — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) est prêt pour les signatures.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"))
            ->line('Merci de planifier cette étape dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'signature',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Signature en attente : {$this->dossier->reference}",
        ];
    }
}
