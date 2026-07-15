<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EcheanceDossierNotification extends Notification implements ShouldQueue
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
            ->subject("Échéance proche — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) arrive à échéance {$this->dossier->echeance?->diffForHumans()}.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"))
            ->line('Merci de traiter cette échéance dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'      => 'echeance',
            'dossier'   => $this->dossier->reference,
            'objet'     => $this->dossier->objet,
            'echeance'  => $this->dossier->echeance?->toDateString(),
            'href'      => "/dossiers/{$this->dossier->reference}",
            'message'   => "Échéance proche : {$this->dossier->reference} — {$this->dossier->echeance?->diffForHumans()}",
        ];
    }
}
