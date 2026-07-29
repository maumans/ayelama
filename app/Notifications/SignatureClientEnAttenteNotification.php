<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureClientEnAttenteNotification extends Notification
{
    public function __construct(public Dossier $dossier) {}

    public function via(object $notifiable): array
    {
        $channels = ['database', 'broadcast'];
        if ($notifiable->notifications_email) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Signature client — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) est prêt pour la signature du client.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"))
            ->line('Merci de planifier cette signature dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'signature_client',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Signature client en attente : {$this->dossier->reference}",
        ];
    }
}
