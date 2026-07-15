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
        $formalites = Formalite::with(['dossier.formaliste', 'dossier.notaire'])
            ->urgentes()
            ->get();

        foreach ($formalites as $formalite) {
            $dossier = $formalite->dossier;
            if (!$dossier) continue;

            $destinataires = $formalite->estDepassee()
                ? [$dossier->formaliste, $dossier->notaire]
                : [$dossier->formaliste];

            foreach ($destinataires as $destinataire) {
                if (!$destinataire) continue;

                $dejaNotifie = $destinataire->notifications()
                    ->where('type', FormaliteUrgenteNotification::class)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->whereJsonContains('data->formalite', $formalite->id)
                    ->exists();

                if (!$dejaNotifie) {
                    $destinataire->notify(new FormaliteUrgenteNotification($formalite));
                }
            }
        }

        $this->info("Alertes envoyées pour {$formalites->count()} formalité(s).");
    }
}
