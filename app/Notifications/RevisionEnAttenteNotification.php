<?php

namespace App\Notifications;

use App\Models\Dossier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RevisionEnAttenteNotification extends Notification
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
            'type'    => 'revision',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}/revision",
            'message' => "Révision en attente : {$this->dossier->reference}",
        ];
    }
}
