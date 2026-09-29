<?php

namespace App\Console\Commands;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutSociete;
use App\Models\Societe;
use App\Notifications\EcheanceLiquidationNotification;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Alerte sur les liquidations dont une échéance approche ou est dépassée.
 *
 * **La seule commande du projet qui ne parte pas d'un dossier.** Les deux autres
 * ({@see AlerterEcheances}, {@see AlerterFormalites}) balaient des dossiers et notifient leurs
 * `ayantsDroit()`. Ici il n'y a ni l'un ni l'autre : une liquidation vit sur la fiche société,
 * entre un dossier de dissolution clos depuis des mois et un dossier de clôture pas encore
 * ouvert. C'est justement l'angle mort que cette commande couvre.
 *
 * **Quotidienne, pas horaire.** Un délai de trois ans ne se surveille pas toutes les heures.
 *
 * ⚠️ Les délais qu'elle relaie **ne sont pas garantis** (voir {@see \App\Enums\JalonLiquidation}),
 * et la notification le dit explicitement. Ils n'ont aucun effet bloquant : aucun dossier n'est
 * empêché d'avancer par cette commande, ni par rien de ce qu'elle lit.
 */
class AlerterLiquidations extends Command
{
    protected $signature = 'ayelema:alerter-liquidations
                            {--jours=30 : Fenêtre d\'anticipation, en jours}';

    protected $description = "Alerte sur les liquidations dont une échéance approche ou est dépassée";

    /**
     * Fenêtre anti-doublon, en jours.
     *
     * Les deux autres commandes utilisent 12 heures, ce qui convient à une échéance de dossier
     * à 72 heures. Sur un délai de trois ans, renotifier tous les jours serait du harcèlement —
     * et une alerte qu'on apprend à ignorer ne sert plus à rien.
     */
    private const FENETRE_ANTI_DOUBLON_JOURS = 7;

    public function handle(NotificationService $notifications): int
    {
        $fenetre = (int) $this->option('jours');
        $envois  = 0;
        $alertes = 0;

        $societes = Societe::query()
            ->statut([StatutSociete::EnLiquidation, StatutSociete::LiquidationCloturee])
            ->get();

        foreach ($societes as $societe) {
            foreach ($societe->echeancesLiquidation() as $echeance) {
                if (! $echeance['enRetard'] && $echeance['joursRestants'] > $fenetre) {
                    continue;
                }

                $alertes++;
                $destinataires = $this->destinataires($societe, $notifications)
                    ->reject(fn ($u) => $this->dejaPrevenu($u, $societe, $echeance['jalon']));

                $notifications->envoyer(
                    $destinataires,
                    new EcheanceLiquidationNotification($societe, $echeance),
                );

                $envois += $destinataires->count();
            }
        }

        $this->info("{$societes->count()} liquidation(s) suivie(s), {$alertes} échéance(s) à signaler — {$envois} notification(s) envoyée(s).");

        return self::SUCCESS;
    }

    /**
     * Qui prévenir.
     *
     * Les ayants droit du **dernier dossier de dissolution** de cette société : ce sont les
     * personnes qui l'ont traitée, et elles existent toujours en base trois ans après. À
     * défaut — société entrée au registre à la main, ou dossier supprimé —, le notaire et les
     * clercs.
     *
     * ⚠️ **Personne n'a dit qui suit les liquidations en sommeil à l'étude.** Ce repli est un
     * défaut assumé, pas un arbitrage : la question est ouverte. Il est ici, à un seul endroit,
     * pour qu'une réponse le corrige en une ligne.
     */
    private function destinataires(Societe $societe, NotificationService $notifications)
    {
        $dernier = $societe->dossiers()
            ->whereHas('typeActe', fn ($q) => $q->where('code', 'SOC-DIS'))
            ->latest('id')
            ->first();

        $ayantsDroit = $dernier?->ayantsDroit() ?? collect();

        return $ayantsDroit->isNotEmpty()
            ? $ayantsDroit
            : $notifications->pool([RoleUtilisateur::Notaire, RoleUtilisateur::Clerc]);
    }

    /**
     * Clé anti-doublon **double** : société *et* jalon.
     *
     * Une seule des deux ne suffirait pas. Sur la société seule, l'alerte de fin de mandat
     * masquerait celle de clôture ; sur le jalon seul, une société couvrirait toutes les
     * autres.
     */
    private function dejaPrevenu($destinataire, Societe $societe, string $jalon): bool
    {
        return $destinataire->notifications()
            ->where('type', EcheanceLiquidationNotification::class)
            ->where('created_at', '>=', now()->subDays(self::FENETRE_ANTI_DOUBLON_JOURS))
            ->whereJsonContains('data->societe', $societe->id)
            ->whereJsonContains('data->jalon', $jalon)
            ->exists();
    }
}
