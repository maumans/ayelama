<?php

namespace App\Services;

use App\Models\Dossier;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ActesGeneratorService
{
    public function genererDocument(Dossier $dossier, string $templatePath, string $outputName): string
    {
        $outputAbsDir = storage_path('app' . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'dossiers' . DIRECTORY_SEPARATOR . $dossier->id);
        if (!is_dir($outputAbsDir) && !mkdir($outputAbsDir, 0755, true) && !is_dir($outputAbsDir)) {
            throw new \RuntimeException("Impossible de créer le répertoire : {$outputAbsDir}");
        }

        $ts                 = time();
        $filename           = $outputName . '_' . $ts . '.docx';
        $outputPath         = 'dossiers/' . $dossier->id . '/' . $filename;
        $outputAbsolutePath = $outputAbsDir . DIRECTORY_SEPARATOR . $filename;
        // Le disque 'local' (racine storage/app/private) est la source des modèles uploadés.
        // On normalise le chemin : si chemin_fichier = 'statuts.docx' → 'modeles/statuts.docx'
        //                          si chemin_fichier = 'modeles/soc/x.docx' → déjà préfixé
        $storagePath     = str_starts_with($templatePath, 'modeles/') ? $templatePath : 'modeles/' . $templatePath;
        $templateAbsPath = Storage::disk('local')->path($storagePath);

        if (file_exists($templateAbsPath)) {
            $this->genererDepuisModele($dossier, $templateAbsPath, $outputAbsolutePath);
        } else {
            $this->creerDocumentPlaceholder($dossier, $outputAbsolutePath, $outputName);
        }

        return $outputPath;
    }

    // ── Génération réelle ────────────────────────────────────────────────────

    private function genererDepuisModele(Dossier $dossier, string $templateAbsPath, string $outputAbsPath): void
    {
        $tp = new TemplateProcessor($templateAbsPath);

        $this->remplirConstantesOffice($tp);
        $this->remplirInfosDossier($tp, $dossier);
        $this->remplirQuestionnaire($tp, $dossier);
        $this->effacerMacrosResiduelles($tp);

        $tp->saveAs($outputAbsPath);
    }

    private function remplirConstantesOffice(TemplateProcessor $tp): void
    {
        $tp->setValue('office.notaire',    'Maître Ayelama BAH');
        $tp->setValue('office.titre',      'Notaire');
        $tp->setValue('office.charge',     'n°21');
        $tp->setValue('office.residence',  'Ratoma');
        $tp->setValue('office.adresse',    'Nongo, 3ᵉ étage, Immeuble VISTA BANK');
        $tp->setValue('office.bp',         'BP 2668/2868');
        $tp->setValue('office.commune',    'Commune de Ratoma/Lambanyi');
        $tp->setValue('office.ville',      'Conakry');
        $tp->setValue('office.telephones', '622 49 69 44 / 664 20 96 07 / 655 61 38 38');
        $tp->setValue('office.email',      'ayelama.bah@notaire-guinee.com');
    }

    private function remplirInfosDossier(TemplateProcessor $tp, Dossier $dossier): void
    {
        $now = now();

        $tp->setValue('dossier.reference',     $dossier->reference);
        $tp->setValue('dossier.objet',         $dossier->objet ?? '');
        $tp->setValue('date_acte_jma',         $now->format('d/m/Y'));
        $tp->setValue('annee_lettres',         NombreEnLettres::convertir((float) $now->year, ''));
        $tp->setValue('date_acte_lettres',     $this->datEnLettres($now));
        // Le nombre de pages ne peut être déterminé qu'après génération : laisser vide
        $tp->setValue('acte.nb_pages',         '');
        $tp->setValue('acte.nb_pages_lettres', '');
    }

    private function datEnLettres(\Illuminate\Support\Carbon $date): string
    {
        $mois = [
            1 => 'JANVIER',   2 => 'FÉVRIER',  3 => 'MARS',      4 => 'AVRIL',
            5 => 'MAI',       6 => 'JUIN',      7 => 'JUILLET',   8 => 'AOÛT',
            9 => 'SEPTEMBRE', 10 => 'OCTOBRE', 11 => 'NOVEMBRE', 12 => 'DÉCEMBRE',
        ];

        $jourLettres  = $date->day === 1
            ? 'PREMIER'
            : NombreEnLettres::convertir((float) $date->day, '');

        $anneeLettres = NombreEnLettres::convertir((float) $date->year, '');

        return $jourLettres . ' ' . $mois[$date->month] . ' ' . $anneeLettres;
    }

    private function remplirQuestionnaire(TemplateProcessor $tp, Dossier $dossier): void
    {
        $donnees = $dossier->questionnaire?->donnees ?? [];

        foreach ($donnees as $cle => $valeur) {
            if (is_array($valeur) && array_is_list($valeur)) {
                // Bloc répétable (associés, gérants, administrateurs…)
                $this->remplirBlocRepetable($tp, $cle, $valeur, $donnees);
                continue;
            }

            if (is_array($valeur)) {
                continue;
            }

            if (str_ends_with($cle, '_chiffres') && is_numeric($valeur)) {
                // _lettres : sans devise (le template apporte lui-même "FRANCS GUINÉENS")
                $cleLettre = str_replace('_chiffres', '_lettres', $cle);
                if (!isset($donnees[$cleLettre])) {
                    $tp->setValue($cleLettre, NombreEnLettres::convertir((float) $valeur, ''));
                }
                // _formate : 30000000 → "30 000 000" (espace fine insécable)
                $cleFormate = str_replace('_chiffres', '_formate', $cle);
                if (!isset($donnees[$cleFormate])) {
                    $tp->setValue($cleFormate, number_format((float) $valeur, 0, ',', "\u{202F}"));
                }
            }

            $tp->setValue($cle, htmlspecialchars((string) $valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
        }

        // Dériver pp.adresse / ger.adresse / … à partir des champs séparés si le questionnaire
        // utilise la forme décomposée (quartier + commune + demeurant_ville + pays).
        foreach (['pp', 'ger', 'acq', 'loc', 'liquidateur'] as $pfx) {
            $q = $donnees["{$pfx}.quartier"]        ?? null;
            $c = $donnees["{$pfx}.commune"]         ?? null;
            $v = $donnees["{$pfx}.demeurant_ville"] ?? null;

            if (($q !== null || $c !== null || $v !== null) && !isset($donnees["{$pfx}.adresse"])) {
                $adresse = implode(', ', array_filter([$q, $c, $v]));
                $tp->setValue("{$pfx}.adresse", htmlspecialchars($adresse, ENT_XML1 | ENT_COMPAT, 'UTF-8'));
            }
        }
    }

    /**
     * Remplit un bloc répétable dans le template .docx.
     *
     * Dans le modèle Word, le bloc doit être délimité par les balises :
     *   ${associes}  …  ${/associes}
     * Et les champs internes : ${associes.nom}, ${associes.parts_chiffres}, etc.
     *
     * PhpWord clonera le bloc autant de fois qu'il y a d'items, puis setValue
     * remplira chaque occurrence avec la notation indexée : ${associes#1.nom}, etc.
     */
    private function remplirBlocRepetable(TemplateProcessor $tp, string $bloc, array $items, array $allDonnees): void
    {
        if (empty($items)) {
            return;
        }

        try {
            $tp->cloneBlock($bloc, count($items), true, true);
        } catch (\Exception) {
            // Le bloc n'existe pas dans ce template — on ignore silencieusement.
            return;
        }

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $index = $i + 1; // PhpWord utilise #1, #2, …
            foreach ($item as $champ => $valeur) {
                $valeur = (string) ($valeur ?? '');

                // Auto-conversion montants en lettres
                if (str_ends_with($champ, '_chiffres') && is_numeric($valeur)) {
                    $champLettres = str_replace('_chiffres', '_lettres', $champ);
                    if (!isset($item[$champLettres])) {
                        $tp->setValue("{$bloc}#{$index}.{$champLettres}", NombreEnLettres::convertir((float) $valeur));
                    }
                }

                $tp->setValue(
                    "{$bloc}#{$index}.{$champ}",
                    htmlspecialchars($valeur, ENT_XML1 | ENT_COMPAT, 'UTF-8')
                );
            }
        }
    }

    // Remplace toutes les balises ${...} non résolues par une chaîne vide.
    // Appelé en dernier, après tous les setValue, pour ne jamais laisser de
    // marqueur brut dans le document final.
    private function effacerMacrosResiduelles(TemplateProcessor $tp): void
    {
        foreach ($tp->getVariables() as $var) {
            try {
                $tp->setValue($var, '');
            } catch (\Exception) {
                // Variable dans un bloc répétable déjà cloné — on ignore.
            }
        }
    }

    // ── Placeholder (aucun modèle uploadé) ──────────────────────────────────

    private function creerDocumentPlaceholder(Dossier $dossier, string $outputAbsPath, string $outputName): void
    {
        $phpWord  = new PhpWord();
        $section  = $phpWord->addSection();
        $donnees  = $dossier->questionnaire?->donnees ?? [];

        $section->addText(
            'DOSSIER : ' . $dossier->reference,
            ['bold' => true, 'size' => 14]
        );
        $section->addText('Objet : ' . ($dossier->objet ?? ''));
        $section->addText('Document : ' . $outputName);
        $section->addTextBreak();
        $section->addText('⚠ Aucun modèle .docx associé — document généré automatiquement.', ['italic' => true]);
        $section->addTextBreak();

        foreach ($donnees as $cle => $valeur) {
            if (!is_array($valeur) && $valeur !== null && $valeur !== '') {
                $section->addText($cle . ' : ' . $valeur);
            }
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($outputAbsPath);
    }
}
