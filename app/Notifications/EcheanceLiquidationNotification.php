<?php

namespace App\Notifications;

use App\Enums\JalonLiquidation;
use App\Models\Societe;
use App\Notifications\Concerns\CanauxNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Échéance d'une liquidation en cours — la seule alerte du projet qui ne parte pas d'un dossier.
 *
 * Une liquidation dure des mois ou des années. Pendant ce temps, le dossier de dissolution est
 * clos et celui de clôture n'est pas ouvert : il n'y a rien à surveiller côté dossiers, et
 * c'est précisément pourquoi ces échéances passaient inaperçues.
 *
 * ⚠️ **Le message dit que le délai n'est pas garanti.** C'est une obligation, pas une
 * politesse : ces délais sont des hypothèses de travail (voir {@see JalonLiquidation}). Une
 * alerte qui présenterait un chiffre inventé comme une règle serait pire que pas d'alerte —
 * elle ferait agir l'étude sur une contrainte qui n'existe peut-être pas.
 */
class EcheanceLiquidationNotification extends Notification
{
    use CanauxNotification;

    /** @param array{jalon: string, label: string, echeance: \Illuminate\Support\Carbon, joursRestants: int, enRetard: bool, aVerifier: bool, source: string} $echeance */
    public function __construct(
        public Societe $societe,
        public array $echeance,
    ) {}

    private function accroche(): string
    {
        return $this->echeance['enRetard']
            ? sprintf('%s : échéance dépassée depuis le %s', $this->echeance['label'], $this->echeance['echeance']->format('d/m/Y'))
            : sprintf('%s : échéance au %s', $this->echeance['label'], $this->echeance['echeance']->format('d/m/Y'));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Liquidation — {$this->societe->nomComplet()}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line(sprintf(
                'La société « %s » est %s depuis le %s.',
                $this->societe->nomComplet(),
                mb_strtolower($this->societe->statut->label()),
                $this->societe->dissolution_at?->format('d/m/Y') ?? 'une date inconnue',
            ))
            ->line($this->accroche());

        if ($this->echeance['aVerifier']) {
            // La mention est en toutes lettres, pas en petits caractères : c'est elle qui
            // distingue un rappel utile d'une fausse contrainte.
            $message->line('⚠️ Ce délai n\'est pas confirmé. ' . $this->echeance['source']);
        }

        return $message
            ->action('Ouvrir le registre des sociétés', url('/societes?statut=' . $this->societe->statut->value))
            ->line('Si cette liquidation est en réalité terminée, mettez la fiche à jour : l\'alerte cessera.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'echeance_liquidation',
            'societe'    => $this->societe->id,
            'jalon'      => $this->echeance['jalon'],
            'echeance'   => $this->echeance['echeance']->toDateString(),
            'enRetard'   => $this->echeance['enRetard'],
            'aVerifier'  => $this->echeance['aVerifier'],
            'href'       => '/societes?statut=' . $this->societe->statut->value,
            'message'    => sprintf('%s — %s', $this->societe->nomComplet(), $this->accroche())
                . ($this->echeance['aVerifier'] ? ' (délai à vérifier)' : ''),
        ];
    }
}
