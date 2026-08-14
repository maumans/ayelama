<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un dossier a été renvoyé à l'étape précédente (typiquement une certification
 * renvoyée en correction). Destinée au rédacteur : sans elle, il devait
 * découvrir le renvoi et son motif en rouvrant le dossier.
 */
class DossierRenvoyeNotification extends Notification
{
    use CanauxNotification;

    public function __construct(
        public Dossier $dossier,
        public string $depuis,
        public string $vers,
        public ?string $motif = null,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Dossier renvoyé — {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) a été renvoyé de « {$this->depuis} » à « {$this->vers} ».");

        if ($this->motif) {
            $mail->line("Motif : {$this->motif}");
        }

        return $mail
            ->action('Corriger le dossier', url("/dossiers/{$this->dossier->reference}"))
            ->line('Merci d\'apporter les corrections demandées puis de soumettre à nouveau.');
    }

    public function toArray(object $notifiable): array
    {
        $message = "Dossier renvoyé en « {$this->vers} » : {$this->dossier->reference}";
        if ($this->motif) {
            $message .= " — {$this->motif}";
        }

        return [
            'type'    => 'renvoi',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'depuis'  => $this->depuis,
            'vers'    => $this->vers,
            'motif'   => $this->motif,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => $message,
        ];
    }
}
