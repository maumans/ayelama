<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Notifications\EcheanceDossierNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class AlerterEcheances extends Command
{
    protected $signature   = 'ayelema:alerter-echeances';
    protected $description = 'Envoie des notifications pour les dossiers dont l\'échéance approche (< 72h)';

    public function handle(NotificationService $notifications): void
    {
        $dossiers = Dossier::with(['redacteur', 'reviseur', 'notaire', 'formaliste'])
            ->echeanceUrgente()
            ->get();

        $envois = 0;

        foreach ($dossiers as $dossier) {
            // Une échéance concerne tout le monde sur le dossier, pas un rôle en
            // particulier : ayantsDroit() reste le bon périmètre ici.
            $aNotifier = $dossier->ayantsDroit()->reject(fn ($destinataire) =>
                $destinataire->notifications()
                    ->where('type', EcheanceDossierNotification::class)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->whereJsonContains('data->dossier', $dossier->reference)
                    ->exists()
            );

            $notifications->envoyer($aNotifier, new EcheanceDossierNotification($dossier));
            $envois += $aNotifier->count();
        }

        $this->info("{$dossiers->count()} dossier(s) à échéance — {$envois} notification(s) envoyée(s).");
    }
}
