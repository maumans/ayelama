<?php

namespace App\Notifications;

use App\Enums\RoleUtilisateur;
use App\Models\Dossier;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un utilisateur vient d'être assigné à un dossier (à sa création ou lors d'une
 * réassignation). Jusqu'ici personne n'était averti : un certificateur pouvait
 * se voir confier un dossier sans jamais le savoir.
 */
class DossierAssigneNotification extends Notification
{
    use CanauxNotification;

    public function __construct(
        public Dossier $dossier,
        public RoleUtilisateur $role,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Dossier assigné — {$this->dossier->reference}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line("Le dossier « {$this->dossier->objet} » ({$this->dossier->reference}) vous a été assigné en tant que {$this->role->label()}.")
            ->line("Étape actuelle : {$this->dossier->etape->label()}.")
            ->action('Consulter le dossier', url("/dossiers/{$this->dossier->reference}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'    => 'assignation',
            'dossier' => $this->dossier->reference,
            'objet'   => $this->dossier->objet,
            'role'    => $this->role->value,
            'href'    => "/dossiers/{$this->dossier->reference}",
            'message' => "Dossier assigné ({$this->role->label()}) : {$this->dossier->reference}",
        ];
    }
}
