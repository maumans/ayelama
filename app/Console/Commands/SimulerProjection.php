<?php

namespace App\Console\Commands;

use App\Enums\EtapeDossier;
use App\Models\Dossier;
use App\Services\ClientProjectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Ce que la projection d'identité changerait, **sans rien écrire**.
 *
 * À exécuter avant de mettre en service la validation de `donnees_prefixe` /
 * `donnees_bloc` / `donnees_index` (décision #49). Ces trois colonnes n'étaient jamais
 * persistées : `estProjetable()` était donc toujours faux et `ClientProjectionService`
 * n'a jamais rien écrit en production depuis le 2026-08-03.
 *
 * Les réparer rallume la projection. À la prochaine sauvegarde de questionnaire, sur tout
 * dossier non clôturé dont une personne est liée à une fiche client, l'identité saisie à la
 * main sera **remplacée** par celle de la fiche, puis les actes non verrouillés régénérés.
 * C'est le comportement voulu — mais il est irréversible, et l'étude doit voir la liste
 * avant, dossier par dossier, valeur par valeur.
 *
 * La commande ouvre une transaction et la **rejette systématiquement** : la projection ne
 * disposant d'aucun mode « à blanc », c'est le seul moyen d'observer son effet réel plutôt
 * qu'une réimplémentation de sa logique, qui pourrait en diverger et rassurer à tort.
 */
class SimulerProjection extends Command
{
    protected $signature = 'ayelema:projection
                            {--dossier= : Restreindre à une référence (ex. SOC-2026-0016)}
                            {--tout : Inclure les dossiers clôturés, que la projection ignore de toute façon}';

    protected $description = "Simule la projection des fiches clients dans les questionnaires, sans rien écrire";

    public function handle(ClientProjectionService $projection): int
    {
        $dossiers = Dossier::with(['questionnaire', 'parties.client'])
            ->when($this->option('dossier'), fn ($q, $ref) => $q->where('reference', $ref))
            ->when(! $this->option('tout'), fn ($q) => $q->where('etape', '!=', EtapeDossier::Cloture->value))
            ->orderBy('reference')
            ->get();

        if ($dossiers->isEmpty()) {
            $this->warn('Aucun dossier à examiner.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->line("Simulation sur {$dossiers->count()} dossier(s). <fg=yellow>Aucune écriture.</>");

        $impactes = 0;
        $cles     = 0;
        $muets    = [];

        foreach ($dossiers as $dossier) {
            $avant = $dossier->questionnaire?->donnees ?? [];

            // La projection écrit en base ; on la rejoue dans une transaction annulée pour
            // lire son résultat réel. Relire depuis `fresh()` à l'intérieur, sinon on
            // comparerait avec l'instance déjà modifiée en mémoire.
            $apres = $avant;
            DB::beginTransaction();
            try {
                $projection->reprojeter($dossier->fresh(['questionnaire', 'parties.client']));
                $apres = $dossier->fresh('questionnaire')->questionnaire?->donnees ?? [];
            } finally {
                // `finally` et non `catch` : même si la projection lève, la transaction doit
                // être annulée. Une simulation qui écrirait serait pire qu'aucune simulation.
                DB::rollBack();
            }

            $ecarts = $this->ecarts($avant, $apres);

            if ($ecarts === []) {
                $projetables = $dossier->parties->filter->estProjetable()->count();
                if ($projetables === 0 && $dossier->parties->isNotEmpty()) {
                    $muets[] = $dossier->reference;
                }
                continue;
            }

            $impactes++;
            $cles += count($ecarts);

            $this->line('');
            $this->line("<fg=cyan>{$dossier->reference}</> — {$dossier->objet}");
            $this->table(
                ['Clé', 'Valeur actuelle', 'Valeur projetée'],
                array_map(
                    fn (array $e) => [$e['cle'], $this->extrait($e['avant']), $this->extrait($e['apres'])],
                    $ecarts,
                ),
            );
        }

        $this->line('');
        $this->info("{$impactes} dossier(s) impacté(s), {$cles} clé(s) modifiée(s).");

        if ($muets !== []) {
            $this->line('');
            $this->warn(
                count($muets) . " dossier(s) ne projettent rien : aucune de leurs personnes n'est localisée "
                . 'dans le questionnaire. Leur localisation se remplira à leur prochaine sauvegarde depuis '
                . "l'écran — le serveur ne connaît pas le schéma et ne peut pas la deviner."
            );
            $this->line('  ' . implode(', ', array_slice($muets, 0, 20)) . (count($muets) > 20 ? '…' : ''));
        }

        return self::SUCCESS;
    }

    /**
     * Différences entre deux jeux de `donnees`, aplaties — un item de bloc répétable
     * produit une ligne par champ, sinon l'écart serait illisible.
     *
     * @return array<int, array{cle: string, avant: mixed, apres: mixed}>
     */
    private function ecarts(array $avant, array $apres): array
    {
        $plat = function (array $donnees) use (&$plat): array {
            $sortie = [];
            foreach ($donnees as $cle => $valeur) {
                if (is_array($valeur) && array_is_list($valeur)) {
                    foreach ($valeur as $i => $item) {
                        foreach (is_array($item) ? $item : ['' => $item] as $sousCle => $sousValeur) {
                            $sortie["{$cle}[{$i}]" . ($sousCle === '' ? '' : ".{$sousCle}")] = $sousValeur;
                        }
                    }
                    continue;
                }
                $sortie[$cle] = $valeur;
            }

            return $sortie;
        };

        $a = $plat($avant);
        $b = $plat($apres);

        $ecarts = [];
        foreach (array_keys($a + $b) as $cle) {
            $va = $a[$cle] ?? null;
            $vb = $b[$cle] ?? null;
            if ($va !== $vb) {
                $ecarts[] = ['cle' => $cle, 'avant' => $va, 'apres' => $vb];
            }
        }

        return $ecarts;
    }

    private function extrait(mixed $valeur): string
    {
        if ($valeur === null) {
            return '<fg=gray>(absent)</>';
        }
        if (is_array($valeur)) {
            return '<fg=gray>(tableau)</>';
        }

        $texte = (string) $valeur;

        return $texte === '' ? '<fg=gray>(vide)</>' : mb_strimwidth($texte, 0, 40, '…');
    }
}
