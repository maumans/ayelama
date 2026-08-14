<?php

namespace App\Console\Commands;

use App\Models\Formalite;
use App\Notifications\FormaliteUrgenteNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class AlerterFormalites extends Command
{
    protected $signature   = 'ayelema:alerter-formalites';
    protected $description = 'Envoie des notifications pour les formalités urgentes ou dépassées';

    public function handle(NotificationService $notifications): void
    {
        $formalites = Formalite::with(['dossier.redacteur', 'dossier.reviseur', 'dossier.notaire', 'dossier.formaliste'])
            ->urgentes()
            ->get();

        $envois = 0;

        foreach ($formalites as $formalite) {
            $dossier = $formalite->dossier;
            if (!$dossier) continue;

            $aNotifier = $dossier->ayantsDroit()->reject(fn ($destinataire) =>
                $destinataire->notifications()
                    ->where('type', FormaliteUrgenteNotification::class)
                    ->where('created_at', '>=', now()->subHours(12))
                    ->whereJsonContains('data->formalite', $formalite->id)
                    ->exists()
            );

            $notifications->envoyer($aNotifier, new FormaliteUrgenteNotification($formalite));
            $envois += $aNotifier->count();
        }

        $this->info("{$formalites->count()} formalité(s) urgente(s) — {$envois} notification(s) envoyée(s).");
    }
}
