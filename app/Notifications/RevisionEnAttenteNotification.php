<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionEnAttenteNotification extends Notification
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
            ->subject("Certification en attente — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) attend votre certification.")
            ->action('Certifier le dossier', url("/dossiers/{$this->dossier->reference}?tab=revision"))
            ->line('Merci de traiter cette certification dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'revision',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}?tab=revision",
            'message' => "Certification en attente : {$this->dossier->reference}",
        ];
    }
}
