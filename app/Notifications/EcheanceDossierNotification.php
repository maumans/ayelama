<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EcheanceDossierNotification extends Notification
{
    use Queueable;

    public function __construct(public Dossier $dossier) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
