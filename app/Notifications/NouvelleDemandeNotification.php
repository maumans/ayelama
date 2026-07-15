<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NouvelleDemandeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Demande $demande) {}

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
            ->subject('Nouvelle demande soumise')
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Une nouvelle demande a été soumise : {$this->demande->typeActe?->label}.")
            ->action('Consulter la demande', url("/demandes/{$this->demande->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'demande',
            'demande' => $this->demande->id,
            'href'    => "/demandes/{$this->demande->id}",
            'message' => "Nouvelle demande soumise : {$this->demande->typeActe?->label}",
        ];
    }
}
