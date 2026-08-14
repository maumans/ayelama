<?php

namespace App\Services;

use App\Models\Dossier;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Génère à la demande (jamais persisté en GED, toujours regénéré depuis les données
 * courantes) le PDF récapitulatif du questionnaire d'un dossier — destiné à être
 * imprimé et signé par le client, avant d'être re-téléversé comme preuve d'accord
 * via DossierController::televerserAccordClient(). Même pattern que RecuPdfService.
 */
class FicheRecueilPdfService
{
    public function genererPdf(Dossier $dossier): string
    {
        $dossier->loadMissing('typeActe', 'questionnaire', 'parties');

        $groupes = [];
        foreach ($dossier->questionnaire?->donnees ?? [] as $cle => $valeur) {
            if (is_array($valeur) || $valeur === null || $valeur === '') {
                continue;
            }

            $segments = explode('.', $cle, 2);
            $prefixe  = count($segments) > 1 ? $segments[0] : '';
            $suffixe  = count($segments) > 1 ? $segments[1] : $segments[0];

            $groupes[$prefixe][] = [
                'label'  => ucfirst(str_replace('_', ' ', $suffixe)),
                'valeur' => is_bool($valeur) ? ($valeur ? 'Oui' : 'Non') : (string) $valeur,
            ];
        }

        $pdf = Pdf::loadView('fiches_recueil.pdf', [
            'dossier' => $dossier,
            'groupes' => $groupes,
        ]);

        $chemin = 'fiches-recueil/' . $dossier->reference . '/fiche_' . time() . '.pdf';
        Storage::disk('public')->put($chemin, $pdf->output());

        return $chemin;
    }
}
