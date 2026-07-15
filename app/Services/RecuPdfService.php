<?php

namespace App\Services;

use App\Models\Recu;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Génère le PDF d'un reçu de paiement (vue resources/views/recus/pdf.blade.php)
 * et le stocke sur le disque 'public', sous storage/app/public/recus/{reference}/{numero}.pdf.
 */
class RecuPdfService
{
    public function genererPdf(Recu $recu): string
    {
        $recu->loadMissing('paiement.facture.dossier.parties.client', 'paiement.enregistrePar');

        $paiement = $recu->paiement;
        $dossier  = $paiement->facture->dossier;

        $pdf = Pdf::loadView('recus.pdf', [
            'recu'     => $recu,
            'paiement' => $paiement,
            'dossier'  => $dossier,
            'montantEnLettres' => NombreEnLettres::convertir((float) $paiement->montant),
        ]);

        $chemin = 'recus/' . $dossier->reference . '/' . $recu->numero . '.pdf';
        Storage::disk('public')->put($chemin, $pdf->output());

        return $chemin;
    }
}
