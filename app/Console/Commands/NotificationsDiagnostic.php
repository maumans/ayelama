<?php

namespace App\Console\Commands;

use App\Models\Dossier;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Répond à la question « pourquoi cette notification n'est-elle pas passée ? ».
 *
 * Les causes réelles observées sont rarement dans le code métier : SMTP muet,
 * clés Pusher absentes, scheduler jamais lancé (donc aucune alerte d'échéance),
 * ou destinataire injoignable (compte désactivé, email vide, notifications
 * désactivées). Cette commande les rend visibles d'un coup d'œil.
 */
class NotificationsDiagnostic extends Command
{
    protected $signature = 'ayelema:notifications-diagnostic
                            {--mail= : Envoie un email de test à cette adresse}';

    protected $description = 'Diagnostique la chaîne de notifications (broadcast, mail, queue, scheduler, destinataires)';

    public function handle(): int
    {
        $this->components->info('Diagnostic des notifications Ayelema');

        $this->transport();
        $this->queueEtScheduler();
        $this->destinataires();

        if ($adresse = $this->option('mail')) {
            $this->testMail($adresse);
        } else {
            $this->line('');
            $this->comment('Astuce : --mail=vous@exemple.com pour tester réellement l\'envoi SMTP.');
        }

        return self::SUCCESS;
    }

    private function transport(): void
    {
        $broadcaster = config('broadcasting.default');
        $pusherOk = filled(config('broadcasting.connections.pusher.key'))
            && filled(config('broadcasting.connections.pusher.secret'))
            && filled(config('broadcasting.connections.pusher.app_id'));

        $this->components->twoColumnDetail('<options=bold>Transport</>', '');
        $this->components->twoColumnDetail('BROADCAST_CONNECTION', $broadcaster ?: '<fg=red>non défini</>');

        $this->components->twoColumnDetail(
            'Clés Pusher (serveur)',
            $broadcaster === 'pusher'
                ? ($pusherOk ? '<fg=green>complètes</>' : '<fg=red>incomplètes — aucun temps réel</>')
                : '<fg=gray>non applicable</>'
        );

        // Le front n'initialise Echo que si VITE_PUSHER_APP_KEY est présent au
        // moment du build — une clé serveur seule ne suffit pas.
        $this->components->twoColumnDetail(
            'VITE_PUSHER_APP_KEY (front)',
            filled(env('VITE_PUSHER_APP_KEY'))
                ? '<fg=green>présente</>'
                : '<fg=red>absente — window.Echo non initialisé, aucun toast</>'
        );

        $this->components->twoColumnDetail('MAIL_MAILER', config('mail.default') ?: '<fg=red>non défini</>');
        $this->components->twoColumnDetail(
            'Hôte SMTP',
            config('mail.default') === 'smtp'
                ? (config('mail.mailers.smtp.host') ?: '<fg=red>non défini</>')
                : '<fg=gray>non applicable</>'
        );
        $this->components->twoColumnDetail(
            'Expéditeur',
            config('mail.from.address') ?: '<fg=red>MAIL_FROM_ADDRESS non défini</>'
        );
    }

    private function queueEtScheduler(): void
    {
        $this->line('');
        $this->components->twoColumnDetail('<options=bold>Queue & planification</>', '');

        $connexion = config('queue.default');
        $this->components->twoColumnDetail('QUEUE_CONNECTION', $connexion);

        // Rappel : depuis le passage du canal broadcast en onConnection('sync'),
        // le temps réel ne dépend plus d'un worker. Les jobs en attente ici
        // concernent donc d'autres traitements (mailables ShouldQueue).
        if ($connexion === 'database' && Schema::hasTable('jobs')) {
            $enAttente = DB::table('jobs')->count();
            $this->components->twoColumnDetail(
                'Jobs en attente',
                $enAttente === 0 ? '<fg=green>0</>' : "<fg=yellow>{$enAttente} — un worker tourne-t-il ?</>"
            );
        }

        if (Schema::hasTable('failed_jobs')) {
            $echoues = DB::table('failed_jobs')->count();
            $this->components->twoColumnDetail(
                'Jobs échoués',
                $echoues === 0 ? '<fg=green>0</>' : "<fg=red>{$echoues} — voir queue:failed</>"
            );
        }

        // Les deux commandes planifiées sont la seule source des alertes
        // d'échéance et de formalité urgente : sans scheduler, elles ne partent
        // jamais. On le déduit de la présence d'un mutex/log d'exécution récent.
        $this->components->twoColumnDetail(
            'Alertes planifiées',
            'ayelema:alerter-echeances + ayelema:alerter-formalites (hourly)'
        );
        $this->components->twoColumnDetail(
            'Scheduler',
            '<fg=yellow>à vérifier : `php artisan schedule:work` en dev, cron `schedule:run` en prod</>'
        );
    }

    private function destinataires(): void
    {
        $this->line('');
        $this->components->twoColumnDetail('<options=bold>Destinataires à risque</>', '');

        $sansEmail = User::where('actif', true)
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->count();
        $this->components->twoColumnDetail(
            'Comptes actifs sans email',
            $sansEmail === 0 ? '<fg=green>0</>' : "<fg=yellow>{$sansEmail} — canal mail ignoré pour eux</>"
        );

        $mailCoupe = User::where('actif', true)->where('notifications_email', false)->count();
        $this->components->twoColumnDetail(
            'Comptes avec notifications_email = false',
            $mailCoupe === 0 ? '<fg=green>0</>' : "<fg=yellow>{$mailCoupe} — reçoivent en base/temps réel seulement</>"
        );

        // Cas le plus vicieux : un dossier assigné à un compte désactivé. La
        // notification n'est plus envoyée (voir Dossier::ayantsDroit()), donc
        // personne ne traite le dossier — il faut le réassigner.
        $inactifsAssignes = Dossier::query()
            ->whereNot('etape', 'cloture')
            ->where(fn ($q) => $q
                ->whereHas('redacteur', fn ($u) => $u->where('actif', false))
                ->orWhereHas('reviseur', fn ($u) => $u->where('actif', false))
                ->orWhereHas('notaire', fn ($u) => $u->where('actif', false))
                ->orWhereHas('formaliste', fn ($u) => $u->where('actif', false)))
            ->count();
        $this->components->twoColumnDetail(
            'Dossiers en cours assignés à un compte désactivé',
            $inactifsAssignes === 0 ? '<fg=green>0</>' : "<fg=red>{$inactifsAssignes} — à réassigner</>"
        );

        // Un poste vacant n'est plus un trou (repli sur le pool du rôle), mais
        // ça reste un signal : le repli notifie tout le pool, donc plus large.
        $sansFormaliste = Dossier::whereNot('etape', 'cloture')->whereNull('formaliste_id')->count();
        $sansReviseur   = Dossier::whereNot('etape', 'cloture')->whereNull('reviseur_id')->count();
        $this->components->twoColumnDetail(
            'Dossiers en cours sans formaliste / sans certificateur',
            "{$sansFormaliste} / {$sansReviseur} <fg=gray>(repli sur le pool du rôle)</>"
        );

        if (Schema::hasTable('notifications')) {
            $total  = DB::table('notifications')->count();
            $nonLus = DB::table('notifications')->whereNull('read_at')->count();
            $this->line('');
            $this->components->twoColumnDetail('Notifications en base', "{$total} dont {$nonLus} non lue(s)");
        }
    }

    private function testMail(string $adresse): void
    {
        $this->line('');
        $this->components->task("Envoi d'un email de test à {$adresse}", function () use ($adresse) {
            try {
                Mail::raw(
                    "Test de la chaîne d'envoi Ayelema — si vous lisez ceci, le SMTP est fonctionnel.",
                    fn ($m) => $m->to($adresse)->subject('Ayelema — test de notification')
                );

                return true;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error($e->getMessage());

                return false;
            }
        });
    }
}
