<?php

namespace App\Console\Commands;

use App\Models\DossierBrouillon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purge les brouillons de dossier abandonnés.
 *
 * Nécessaire parce qu'un brouillon détient de vrais fichiers (pièces d'identité
 * téléversées avant que le dossier n'existe) : sans purge, une saisie abandonnée
 * les laisse indéfiniment sur le disque. Le projet traîne déjà 117 orphelins
 * hérités d'avant la GED unifiée — on ne recommence pas.
 *
 * Dry-run par défaut, comme `ayelema:ged-lister-orphelins` : sur des pièces
 * d'identité, une suppression ne doit jamais être le comportement implicite.
 */
class PurgerBrouillons extends Command
{
    protected $signature = 'ayelema:brouillons-purger
                            {--jours=60 : Âge minimum, en jours depuis la dernière modification}
                            {--supprimer : Supprime réellement (sinon simple inventaire)}';

    protected $description = 'Liste (et supprime en option) les brouillons de dossier abandonnés et leurs fichiers';

    public function handle(): int
    {
        $jours = max(1, (int) $this->option('jours'));
        $seuil = now()->subDays($jours);

        $brouillons = DossierBrouillon::with('user:id,name')
            ->where('updated_at', '<', $seuil)
            ->orderBy('updated_at')
            ->get();

        if ($brouillons->isEmpty()) {
            $this->components->info("Aucun brouillon inactif depuis plus de {$jours} jours.");

            return self::SUCCESS;
        }

        $lignes = $brouillons->map(function (DossierBrouillon $b) {
            $fichiers = Storage::disk('local')->files($b->repertoire());

            return [
                $b->id,
                $b->user?->name ?? '(compte supprimé)',
                \Illuminate\Support\Str::limit($b->libelle ?: '—', 40),
                count($fichiers),
                $b->updated_at?->format('d/m/Y'),
            ];
        });

        $this->table(['ID', 'Auteur', 'Libellé', 'Pièces', 'Modifié le'], $lignes);

        $totalFichiers = $lignes->sum(3);

        if (!$this->option('supprimer')) {
            $this->components->warn(
                "{$brouillons->count()} brouillon(s) et {$totalFichiers} fichier(s) concernés. "
                . 'Relancez avec --supprimer pour les supprimer réellement.'
            );

            return self::SUCCESS;
        }

        foreach ($brouillons as $brouillon) {
            $brouillon->supprimerAvecFichiers();
        }

        $this->components->info("{$brouillons->count()} brouillon(s) et {$totalFichiers} fichier(s) supprimés.");

        return self::SUCCESS;
    }
}
