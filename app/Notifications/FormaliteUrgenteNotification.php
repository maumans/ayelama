<?php

namespace App\Notifications;

use App\Models\Formalite;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FormaliteUrgenteNotification extends Notification
{
    public function __construct(public Formalite $formalite) {}

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
        $dossier = $this->formalite->dossier;
        $etat = $this->formalite->estDepassee() ? 'est en retard' : 'arrive à échéance';

        return (new MailMessage)
            ->subject("Formalité urgente — Dossier {$dossier?->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La formalité « {$this->formalite->libelle} » ({$this->formalite->organisme}) du dossier {$dossier?->reference} {$etat}.")
            ->action('Consulter les formalités', $dossier ? url("/dossiers/{$dossier->reference}?tab=formalites") : url('/formalites'))
            ->line('Merci de traiter cette formalité dans les meilleurs délais.');
    }

    public function toArray(object $notifiable): array
    {
        $dossier = $this->formalite->dossier;

        return [
            'type'      => 'formalite',
            'dossier'   => $dossier?->reference,
            'formalite' => $this->formalite->id,
            'href'      => $dossier ? "/dossiers/{$dossier->reference}?tab=formalites" : '/formalites',
            'message'   => "Formalité urgente : {$this->formalite->libelle} — {$dossier?->reference}",
        ];
    }
}
