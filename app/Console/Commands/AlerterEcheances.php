<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Notifications\EcheanceDossierNotification;
use Illuminate\Console\Command;

class AlerterEcheances extends Command
{
    protected $signature   = 'ayelema:alerter-echeances';
    protected $description = 'Envoie des notifications pour les dossiers dont l\'échéance approche (< 72h)';

    public function handle(): void
    {
        $dossiers = Dossier::with(['redacteur', 'reviseur', 'notaire', 'formaliste'])
            ->echeanceUrgente()
            ->get();

        foreach ($dossiers as $dossier) {
            // Notifier tous les ayants droit du dossier
            foreach ($dossier->ayantsDroit() as $destinataire) {
                $dejaNotifie = $destinataire->notifications()
                    ->where('type', EcheanceDossierNotification::class)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->whereJsonContains('data->dossier', $dossier->reference)
                    ->exists();

                if (!$dejaNotifie) {
                    try {
                        $destinataire->notify(new EcheanceDossierNotification($dossier));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        $this->info("Alertes envoyées pour {$dossiers->count()} dossier(s).");
    }
}
