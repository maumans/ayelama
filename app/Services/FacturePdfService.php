<?php

namespace App\Services;

use App\Models\Facture;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Rend la note de frais en **PDF**, à la demande.
 *
 * ## Pourquoi ce service existe
 *
 * Le bouton « Télécharger » appelait une route nommée `telechargerPdf` qui produisait en réalité un
 * **`.docx`** : `FactureGeneratorService` rendait le gabarit Word dans un fichier temporaire, que le
 * contrôleur renvoyait avec `deleteFileAfterSend`. L'étude attendait un PDF — et recevait par
 * intermittence un `telecharger.htm`, c'est-à-dire la page HTML d'erreur enregistrée par l'attribut
 * `download` du lien, sous le nom du dernier segment de l'URL.
 *
 * ## Le parti retenu
 *
 * Même procédé que [`RecuPdfService`](app/Services/RecuPdfService.php), déjà en production : une vue
 * Blade rendue par dompdf, qui est **déjà une dépendance du projet**. Aucune installation nouvelle,
 * un comportement identique en local et sur le serveur.
 *
 * Alternative écartée : convertir le `.docx` officiel en PDF. Fidèle au pixel, mais elle exige
 * **LibreOffice installé sur le serveur** et appelé en ligne de commande — une dépendance système
 * lourde, lente, et une panne de plus en production. Le devbook la signalait déjà comme non résolue.
 * La mise en page de la vue suit donc le gabarit Word paragraphe par paragraphe.
 *
 * ## ⚠️ Rendu en mémoire, jamais persisté
 *
 * Le service rend **les octets**, il n'écrit aucun fichier — décision reprise du contrôleur
 * d'origine : le projet traîne déjà 117 fichiers orphelins hors GED, et un document régénérable à
 * volonté n'a pas à en produire un 118ᵉ à chaque clic. C'est aussi ce qui supprime le fichier
 * temporaire et son `deleteFileAfterSend`, d'où venait probablement le `.htm`.
 *
 * Le `.docx` reste produit par `FactureGeneratorService` pour qui doit retoucher le document.
 */
class FacturePdfService
{
    public function __construct(private FactureGeneratorService $gabarit) {}

    /** Le PDF de la note de frais, en mémoire. */
    public function rendre(Facture $facture): string
    {
        $facture->loadMissing(['lignes', 'dossier']);

        return Pdf::loadView('factures.pdf', [
            'facture' => $facture,
            // Les deux libellés dérivés viennent du service du gabarit : le .docx et le PDF disent
            // ainsi exactement la même chose, sans que la formule soit écrite deux fois.
            'detail'         => $this->gabarit->detailPrestations($facture->lignes),
            'totalEnLettres' => $this->gabarit->totalEnLettres((float) $facture->total_chiffres),
        ])->output();
    }

    /**
     * Nom de fichier proposé au navigateur.
     *
     * ⚠️ `note_numero` contient des « / » (« 001/MAB/26 »), invalides dans un nom de fichier — et
     * sur certains systèmes ils tronquent le nom au dernier segment.
     */
    public function nomFichier(Facture $facture): string
    {
        return 'facture-' . str_replace(['/', '\\'], '-', (string) $facture->note_numero) . '.pdf';
    }
}
