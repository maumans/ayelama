<?php

namespace App\Console\Commands;

use App\Models\Formalite;
use App\Notifications\FormaliteUrgenteNotification;
use Illuminate\Console\Command;

class AlerterFormalites extends Command
{
    protected $signature   = 'ayelema:alerter-formalites';
    protected $description = 'Envoie des notifications pour les formalités urgentes ou dépassées';

    public function handle(): void
    {
        $formalites = Formalite::with(['dossier.redacteur', 'dossier.reviseur', 'dossier.notaire', 'dossier.formaliste'])
            ->urgentes()
            ->get();

        foreach ($formalites as $formalite) {
            $dossier = $formalite->dossier;
            if (!$dossier) continue;

            foreach ($dossier->ayantsDroit() as $destinataire) {
                $dejaNotifie = $destinataire->notifications()
                    ->where('type', FormaliteUrgenteNotification::class)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->whereJsonContains('data->formalite', $formalite->id)
                    ->exists();

                if (!$dejaNotifie) {
                    try {
                        $destinataire->notify(new FormaliteUrgenteNotification($formalite));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        $this->info("Alertes envoyées pour {$formalites->count()} formalité(s).");
    }
}
