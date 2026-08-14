<?php

namespace App\Console\Commands;

use App\Enums\CategorieActe;
use App\Enums\FormeSociete;
use App\Models\Dossier;
use App\Models\Societe;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Peuple le registre des sociétés depuis les dossiers de société déjà en base.
 *
 * La table `societes` existe depuis le 2026-06-29 mais n'a jamais été alimentée : les
 * dénominations, capitaux et sièges ne vivent que dans `questionnaires.donnees`. Sans ce
 * backfill, le sélecteur de société de l'assistant ne proposerait rien, et l'étude devrait
 * ressaisir les coordonnées de sociétés qu'elle a elle-même constituées.
 *
 * **Dry-run par défaut**, comme `ayelema:brouillons-purger` : cette commande crée des fiches
 * de référence et modifie des dossiers existants (`societe_id`) — ce ne doit pas être le
 * comportement implicite d'une commande lancée pour voir.
 *
 * ⚠️ **Les doublons de dénomination sont signalés, jamais fusionnés.** Deux dossiers portant
 * la même raison sociale peuvent être une saisie en double comme deux sociétés réellement
 * homonymes (la règle 4 les interdit à la création, mais des dossiers antérieurs à cette
 * règle existent). Fusionner deux fiches notariales est une décision de l'étude, pas d'un
 * script.
 */
class BackfillSocietes extends Command
{
    protected $signature = 'ayelema:societes-backfill
                            {--appliquer : Écrit réellement en base (sinon simple inventaire)}';

    protected $description = 'Crée les fiches du registre des sociétés depuis les questionnaires des dossiers existants';

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        $dossiers = Dossier::query()
            ->whereHas('typeActe', fn ($q) => $q->where('categorie', CategorieActe::Societe->value))
            ->with(['typeActe', 'questionnaire'])
            ->orderBy('id')
            ->get();

        if ($dossiers->isEmpty()) {
            $this->components->info('Aucun dossier de société en base — rien à reprendre.');

            return self::SUCCESS;
        }

        // Fiches déjà présentes, indexées par dénomination normalisée : une relance ne doit
        // pas recréer ce qui existe (idempotence).
        $registre = Societe::all()->keyBy(
            fn (Societe $s) => Societe::normaliserDenomination($s->denomination)
        );

        $creations   = [];
        $rattachages = [];
        $doublons    = [];
        $ignores     = [];

        foreach ($dossiers as $dossier) {
            $donnees      = $dossier->questionnaire?->donnees ?? [];
            $denomination = $donnees['soc.denomination'] ?? null;

            if (blank($denomination)) {
                // Un dossier de société sans dénomination au questionnaire : rien à ficher.
                // Cas attendu sur les dossiers de dissolution ou de modification saisis avant
                // le registre, dont la dénomination est parfois seulement dans l'objet.
                $ignores[] = [$dossier->reference, $dossier->typeActe?->code, 'dénomination absente'];
                continue;
            }

            $cle = Societe::normaliserDenomination($denomination);

            // La forme n'est presque jamais au questionnaire (constat du 2026-08-05) : elle
            // vient du code du type d'acte, source prioritaire.
            $forme = FormeSociete::depuisCodeTypeActe($dossier->typeActe?->code)?->value;

            // Le dossier de constitution est l'origine de la fiche ; une modification ou une
            // dissolution ne l'est pas — elle se rattache à une société préexistante.
            $estConstitution = $forme !== null;

            $existante = $registre->get($cle);

            if ($existante) {
                if ($estConstitution && $existante->dossier_id && $existante->dossier_id !== $dossier->id) {
                    $doublons[] = [
                        $denomination,
                        $existante->dossier?->reference ?? "societe #{$existante->id}",
                        $dossier->reference,
                    ];
                }

                $rattachages[] = [$dossier->reference, $dossier->typeActe?->code, $existante->denomination ?? $denomination];

                if ($appliquer) {
                    $dossier->update(['societe_id' => $existante->id]);
                    // Une fiche née d'une modification (sans origine connue) gagne son dossier
                    // de constitution si on le rencontre ensuite.
                    if ($estConstitution && !$existante->dossier_id) {
                        $existante->update([
                            'dossier_id' => $dossier->id,
                            ...Societe::depuisQuestionnaire($donnees, $forme),
                        ]);
                    }
                }

                continue;
            }

            $attributs = [
                ...Societe::depuisQuestionnaire($donnees, $forme),
                'dossier_id' => $estConstitution ? $dossier->id : null,
            ];

            $creations[] = [
                $denomination,
                $attributs['forme'] ?? '—',
                $attributs['rccm_numero'] ?? '—',
                $dossier->reference,
            ];

            if ($appliquer) {
                $societe = Societe::create($attributs);
                $dossier->update(['societe_id' => $societe->id]);
                $registre->put($cle, $societe);
            } else {
                // En dry-run, mémoriser la clé pour ne pas compter deux fois la même société
                // rencontrée sur deux dossiers.
                $registre->put($cle, new Societe($attributs));
            }
        }

        $this->afficher($creations, $rattachages, $doublons, $ignores);

        if (!$appliquer) {
            $this->components->warn(sprintf(
                '%d fiche(s) à créer et %d dossier(s) à rattacher. Relancez avec --appliquer pour écrire réellement.',
                count($creations),
                count($creations) + count($rattachages),
            ));

            return self::SUCCESS;
        }

        $this->components->info(sprintf(
            '%d fiche(s) créée(s), %d dossier(s) rattaché(s) à une fiche existante.',
            count($creations),
            count($rattachages),
        ));

        return self::SUCCESS;
    }

    private function afficher(array $creations, array $rattachages, array $doublons, array $ignores): void
    {
        if ($creations) {
            $this->newLine();
            $this->line('<info>Fiches à créer</info>');
            $this->table(['Dénomination', 'Forme', 'RCCM', 'Dossier d\'origine'], $creations);
        }

        if ($rattachages) {
            $this->newLine();
            $this->line('<info>Dossiers rattachés à une fiche existante</info>');
            $this->table(['Dossier', 'Type', 'Société'], $rattachages);
        }

        if ($ignores) {
            $this->newLine();
            $this->line('<comment>Dossiers ignorés</comment>');
            $this->table(['Dossier', 'Type', 'Motif'], $ignores);
        }

        if ($doublons) {
            $this->newLine();
            $this->components->warn(
                'Dénominations portées par plusieurs dossiers de constitution — à arbitrer '
                . 'manuellement : ces fiches ne sont PAS fusionnées.'
            );
            $this->table(['Dénomination', 'Fiche existante', 'Autre dossier'], array_map(
                fn ($ligne) => [Str::limit($ligne[0], 40), $ligne[1], $ligne[2]],
                $doublons,
            ));
        }
    }
}
