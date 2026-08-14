<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * La grille de certification a été validée, le dossier passe en Signature.
 * Destinée au notaire (action à mener) et au rédacteur (son dossier a franchi
 * l'étape bloquante) — aucun des deux n'était informé jusqu'ici.
 */
class CertificationValideeNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Certification validée — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("La certification du dossier « {$this->dossier->objet} » ({$this->dossier->reference}) a été validée. Le dossier passe à l'étape Signature.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'certification_validee',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Certification validée : {$this->dossier->reference}",
        ];
    }
}
