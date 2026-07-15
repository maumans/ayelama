<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RevisionEnAttenteNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject("Révision en attente — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) attend votre révision.")
            ->action('Réviser le dossier', url("/dossiers/{$this->dossier->reference}/revision"))
            ->line('Merci de traiter cette révision dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'revision',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}/revision",
            'message' => "Révision en attente : {$this->dossier->reference}",
        ];
    }
}
