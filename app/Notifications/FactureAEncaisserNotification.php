<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le dossier arrive en Expédition avec une facture non soldée — or un dossier ne s'archive pas
 * impayé (DossierStepService::verifierExpedition). Destinée au comptable, qui n'est assigné à
 * aucun dossier et n'en apprenait rien (retour R22, 2026-11-16).
 */
class FactureAEncaisserNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Facture à encaisser — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) arrive en expédition avec une facture non soldée.")
            ->action('Ouvrir la facturation', url("/dossiers/{$this->dossier->reference}?tab=facturation"))
            ->line("Le dossier ne pourra être archivé qu'une fois la facture émise et soldée.");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'facture_a_encaisser',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}?tab=facturation",
            'message' => "Facture à encaisser : {$this->dossier->reference}",
        ];
    }
}
