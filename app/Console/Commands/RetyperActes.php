<?php

namespace App\Console\Commands;

use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
use Illuminate\Console\Command;

/**
 * Recalcule la catégorie des actes produits avec un rôle erroné.
 *
 * `ActesGeneratorService::rolePourProcedure()` retenait, lorsque la procédure n'impose aucune liste
 * de documents attendus — donc pour toutes les constitutions, ventes, baux et hypothèques — le
 * **premier rôle** déclaré par le gabarit au lieu de son **rôle principal**. Un gabarit « RCCM »
 * déclarant aussi `declaration_rccm` produisait ainsi, sur une constitution, un document typé
 * `declaration_rccm`, au seul motif que ce rôle avait été enregistré en premier.
 *
 * Une commande et non une migration : cela touche des actes de dossiers en cours, et se lit avant de
 * s'appliquer. Dry-run par défaut, `--appliquer` pour écrire — même convention que
 * `ayelema:brouillons-purger` et `ayelema:societes-backfill`.
 */
class RetyperActes extends Command
{
    protected $signature = 'ayelema:retyper-actes {--appliquer : Écrit les corrections (sinon, simple rapport)}';

    protected $description = "Recale la catégorie des actes produits avec un rôle autre que le rôle principal de leur gabarit";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        // Seuls les gabarits à plusieurs rôles ont pu produire un document mal typé : avec un rôle
        // unique, le premier rôle **est** le rôle principal.
        $gabarits = ModeleActe::all()
            ->filter(fn (ModeleActe $m) => count($m->rolesRemplis()) > 1)
            ->keyBy('nom');

        if ($gabarits->isEmpty()) {
            $this->info('Aucun gabarit ne déclare plusieurs rôles — rien à recaler.');

            return self::SUCCESS;
        }

        $this->line("Gabarits à plusieurs rôles : {$gabarits->count()}");

        $aCorriger = [];

        foreach (Dossier::with('typeActe', 'documents')->get() as $dossier) {
            // Une modification attend un rôle précis : la génération l'a donc choisi correctement.
            // Le défaut ne concerne que les procédures sans liste de documents attendus.
            if ($dossier->typeActe?->code === 'SOC-MOD') {
                continue;
            }

            foreach ($dossier->documents as $document) {
                $gabarit = $gabarits->get($document->nom);

                if (! $gabarit || $document->categorie === $gabarit->type_document) {
                    continue;
                }

                // Ne corriger que si la catégorie actuelle est bien un des rôles du gabarit : sinon
                // elle vient d'ailleurs (dépôt manuel, reprise), et ce n'est pas à cette commande
                // d'en décider.
                if (! in_array($document->categorie, $gabarit->rolesRemplis(), true)) {
                    continue;
                }

                $aCorriger[] = [$dossier, $document, $gabarit->type_document];
            }
        }

        if ($aCorriger === []) {
            $this->info('Aucun acte mal typé.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['Dossier', 'Acte', 'Catégorie actuelle', 'Catégorie corrigée'],
            array_map(
                fn (array $l) => [$l[0]->reference, $l[1]->nom, $l[1]->categorie, $l[2]],
                $aCorriger,
            ),
        );

        if (! $appliquer) {
            $this->warn(count($aCorriger) . ' acte(s) à recaler. Relancez avec --appliquer pour écrire.');

            return self::SUCCESS;
        }

        foreach ($aCorriger as [$dossier, $document, $categorie]) {
            $ancienne = $document->categorie;
            $document->update(['categorie' => $categorie]);

            // Journalisé : la catégorie d'un acte décide de son classement au dossier, elle ne
            // change pas en silence.
            JournalActivite::enregistrer(
                $dossier,
                "Acte « {$document->nom} » reclassé : {$ancienne} → {$categorie}",
                'correction',
                ['document_id' => $document->id, 'ancienne_categorie' => $ancienne],
            );
        }

        $this->info(count($aCorriger) . ' acte(s) recalé(s).');

        return self::SUCCESS;
    }
}
