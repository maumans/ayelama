<?php

namespace App\Console\Commands;

use App\Models\DocumentVersion;
use FilesystemIterator;
use Illuminate\Console\Command;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Avant l'unification GED, ActesGeneratorService écrivait chaque régénération dans
 * storage/app/public/dossiers/{id}/ avec un nom horodaté, sans jamais nettoyer les
 * anciens fichiers ni les référencer en base — ce répertoire contient donc des
 * fichiers orphelins antérieurs à la migration document_fichiers/document_versions
 * (qui, elle, unifie tout sous documents/{reference}/ et ne perd plus jamais rien).
 * Cette commande liste ces orphelins ; elle ne supprime rien sans --supprimer.
 */
class GedListerOrphelins extends Command
{
    protected $signature   = 'ayelema:ged-lister-orphelins {--supprimer : Supprime réellement les fichiers listés}';
    protected $description = "Liste (et supprime en option) les fichiers orphelins de l'ancien répertoire storage/app/public/dossiers/*";

    public function handle(): int
    {
        $dir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'dossiers');

        if (!is_dir($dir)) {
            $this->info("Aucun répertoire 'dossiers' hérité trouvé — rien à faire.");
            return self::SUCCESS;
        }

        $referenced = DocumentVersion::query()
            ->whereNotNull('chemin_fichier')
            ->pluck('chemin_fichier')
            ->map(fn ($p) => realpath(storage_path('app/public/' . $p)))
            ->filter()
            ->all();

        $orphelins = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            $reel = realpath($file->getPathname());
            if ($reel !== false && !in_array($reel, $referenced, true)) {
                $orphelins[] = $reel;
            }
        }

        if (empty($orphelins)) {
            $this->info('Aucun fichier orphelin trouvé.');
            return self::SUCCESS;
        }

        $this->warn(count($orphelins) . " fichier(s) orphelin(s) trouvé(s) dans storage/app/public/dossiers/ :");
        foreach ($orphelins as $path) {
            $this->line(' - ' . $path);
        }

        if ($this->option('supprimer')) {
            foreach ($orphelins as $path) {
                @unlink($path);
            }
            $this->info(count($orphelins) . ' fichier(s) supprimé(s).');
        } else {
            $this->comment('Relancez avec --supprimer pour les supprimer réellement.');
        }

        return self::SUCCESS;
    }
}
