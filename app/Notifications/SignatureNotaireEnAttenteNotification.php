<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureNotaireEnAttenteNotification extends Notification
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
            ->subject("Signature notaire — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) est prêt pour votre signature.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"))
            ->line('Merci de traiter cette signature dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'signature_notaire',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Signature notaire en attente : {$this->dossier->reference}",
        ];
    }
}
