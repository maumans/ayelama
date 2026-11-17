<?php

namespace App\Notifications;

use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Le dossier vient d'entrer en Expédition : lettres de transmission à envoyer, inventaire de
 * clôture à vérifier (retour R22, 2026-11-16 — cette entrée ne notifiait personne). Destinée au
 * formaliste et au notaire, seuls habilités à ces gestes (DossierPolicy::genererCourriers et
 * cloturerDocuments).
 */
class ExpeditionAFaireNotification extends Notification
{
    use CanauxNotification;

    public function __construct(public Dossier $dossier) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Expédition à faire — Dossier {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Les formalités du dossier « {$this->dossier->objet} » ({$this->dossier->reference}) sont revenues : il passe à l'expédition.")
            ->action("Ouvrir l'expédition", url("/dossiers/{$this->dossier->reference}?tab=expedition"))
            ->line("Merci d'envoyer les lettres de transmission et de vérifier l'inventaire des pièces (onglet Archivage).");
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'expedition_a_faire',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'href'    => "/dossiers/{$this->dossier->reference}?tab=expedition",
            'message' => "Expédition à faire : {$this->dossier->reference}",
        ];
    }
}
