<?php

namespace App\Services;

use App\Models\Dossier;
use App\Models\JournalActivite;
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
            $this->genererDepuisModele($dossier, $templateAbsPath, $outputAbsolutePath, $outputName);
        } else {
            $this->creerDocumentPlaceholder($dossier, $outputAbsolutePath, $outputName);
        }

        return $outputPath;
    }

    // ── Génération réelle ────────────────────────────────────────────────────

    private function genererDepuisModele(Dossier $dossier, string $templateAbsPath, string $outputAbsPath, string $outputName): void
    {
        $tp = new TemplateProcessor($templateAbsPath);

        $this->remplirConstantesOffice($tp);
        $this->remplirInfosDossier($tp, $dossier);
        $this->remplirQuestionnaire($tp, $dossier);
        $this->consignerChampsManquants($tp, $dossier, $outputName);
        $this->effacerMacrosResiduelles($tp);

        $tp->saveAs($outputAbsPath);
    }

    /**
     * Un champ du modèle sans valeur correspondante n'est PAS bloquant (le document est
     * quand même généré, avec ce passage laissé vide par `effacerMacrosResiduelles`) — mais
     * ça ne doit plus rester silencieux : on le consigne dans l'historique du dossier pour
     * que le rédacteur/notaire s'en aperçoive avant signature plutôt qu'à la relecture papier.
     */
    private function consignerChampsManquants(TemplateProcessor $tp, Dossier $dossier, string $outputName): void
    {
        $manquants = array_values(array_unique($tp->getVariables()));
        if (empty($manquants)) {
            return;
        }

        // La liste complète part dans `meta` (JSON, non contraint) ; le résumé texte doit lui
        // rester sous la limite de la colonne `action` (varchar 255) — un bloc répétable avec
        // plusieurs items peut facilement produire des dizaines de champs manquants.
        $resume = implode(', ', $manquants);
        if (mb_strlen($resume) > 150) {
            $resume = mb_substr($resume, 0, 150) . '…';
        }

        JournalActivite::enregistrer(
            $dossier,
            "Document « {$outputName} » généré avec " . count($manquants) . " champ(s) resté(s) vide(s) faute de donnée : {$resume}",
            'creation',
            ['document' => $outputName, 'champs_manquants' => $manquants]
        );
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

        $this->deriverPersonnesParDefaut($donnees, $dossier->typeActe?->code);

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

        // Terme du bail (baux habitation/commercial/construction) : les modèles écrivent
        // « … commence à courir le [date_prise_effet] pour se terminer le [date_fin] », mais
        // le questionnaire ne demande que la durée en années — la date de fin ne serait donc
        // jamais fournie. On la déduit ici plutôt que de l'ajouter comme un champ de plus à saisir.
        $dateEffet = $donnees['bail.date_prise_effet'] ?? null;
        $duree     = $donnees['bail.duree_chiffres'] ?? null;
        if ($dateEffet && $duree && !isset($donnees['bail.date_fin'])) {
            try {
                $dateFin = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $dateEffet)
                    ->addYears((int) $duree)
                    ->format('d/m/Y');
                $tp->setValue('bail.date_fin', $dateFin);
            } catch (\Exception) {
                // Date saisie dans un format inattendu — laisser le placeholder vide plutôt que planter.
            }
        }
    }

    /**
     * SARLU (`creation_sarlu`) et SASU (`creation_sasu`) proposent une case à cocher
     * « le gérant/président est une personne différente de l'associé unique », décochée
     * par défaut car c'est le cas le plus fréquent — l'associé unique se nomme lui-même
     * gérant/président. Une case à cocher jamais touchée par l'utilisateur n'existe même
     * pas dans `donnees` (React ne l'ajoute qu'au premier clic) : on ne peut donc PAS se
     * fier à sa présence pour savoir si la dérivation s'applique, seulement au code du
     * type d'acte. Tant qu'elle reste décochée (ou absente), les champs ger.xxx /
     * soc.president_xxx ne sont jamais collectés : sans cette dérivation, les paragraphes
     * du modèle qui les utilisent (ex. statuts-sarlu.docx : "Dès à présent,
     * ${ger.civilite} ${ger.prenom_nom}…") seraient générés vides.
     */
    private function deriverPersonnesParDefaut(array &$donnees, ?string $typeActeCode): void
    {
        if ($typeActeCode === 'SOC-SARLU' && empty($donnees['ger.est_different'])) {
            foreach ($donnees as $cle => $valeur) {
                if (!str_starts_with($cle, 'pp.')) {
                    continue;
                }
                $cleGer = 'ger.' . substr($cle, 3);
                if (empty($donnees[$cleGer])) {
                    $donnees[$cleGer] = $valeur;
                }
            }
        }

        if ($typeActeCode === 'SOC-SASU' && empty($donnees['soc.president_est_different'])) {
            $map = [
                'soc.president_civilite'     => 'pp.civilite',
                'soc.president_nom'          => 'pp.prenom_nom',
                'soc.president_piece_numero' => 'pp.piece_numero',
            ];
            foreach ($map as $cleDestination => $cleSource) {
                if (empty($donnees[$cleDestination]) && !empty($donnees[$cleSource])) {
                    $donnees[$cleDestination] = $donnees[$cleSource];
                }
            }
            if (empty($donnees['soc.president_adresse'])) {
                $adresse = implode(', ', array_filter([
                    $donnees['pp.quartier'] ?? null,
                    $donnees['pp.commune'] ?? null,
                    $donnees['pp.demeurant_ville'] ?? null,
                ]));
                if ($adresse !== '') {
                    $donnees['soc.president_adresse'] = $adresse;
                }
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
     * PhpWord clonera le bloc autant de fois qu'il y a d'items. Avec `cloneBlock(...,
     * $indexVariables: true)`, chaque variable ${associes.nom} du bloc N est renommée en
     * ${associes.nom#N} — l'index est ajouté à LA FIN du nom de variable complet, pas
     * inséré entre le nom du bloc et le champ (voir TemplateProcessor::indexClonedVariables
     * dans PhpWord, qui fait `preg_replace('/\$\{([^:]*?)(:.*?)?\}/', '${\1#N\2}', ...)`).
     * D'où la clé "{$bloc}.{$champ}#{$index}" ci-dessous, et surtout pas "{$bloc}#{$index}.{$champ}".
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
                        $tp->setValue("{$bloc}.{$champLettres}#{$index}", NombreEnLettres::convertir((float) $valeur));
                    }
                }

                $tp->setValue(
                    "{$bloc}.{$champ}#{$index}",
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
