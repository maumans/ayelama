<?php

namespace App\Notifications;

use App\Models\Demande;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NouvelleDemandeNotification extends Notification
{
    use Queueable;

    public function __construct(public Demande $demande) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
