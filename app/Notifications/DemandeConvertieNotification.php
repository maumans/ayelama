<?php

namespace App\Notifications;

use App\Models\Demande;
use App\Models\Dossier;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DemandeConvertieNotification extends Notification
{
    public function __construct(public Demande $demande, public Dossier $dossier) {}

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
            ->subject("Demande convertie — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La demande « {$this->demande->objet} » que vous avez transmise a été convertie en dossier {$this->dossier->reference}.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'demande_convertie',
            'demande' => $this->demande->id,
            'dossier' => $this->dossier->reference,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Demande convertie en dossier : {$this->dossier->reference}",
        ];
    }
}
