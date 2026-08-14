<?php

namespace App\Http\Controllers;

use App\Models\ClotureVerification;
use App\Models\Courrier;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Models\Recu;
use App\Services\InventaireClotureService;
use Illuminate\Http\Request;

/**
 * Vérification des pièces avant clôture d'un dossier.
 *
 * Remplace la logique « documents obligatoires configurés par type d'acte » : c'est
 * désormais un contrôle humain explicite sur l'inventaire réel du dossier — des pièces
 * d'origines hétérogènes (actes, CNI, justificatifs d'organismes, courriers, reçus)
 * qu'aucune règle automatique ne peut déclarer complètes.
 */
class ClotureController extends Controller
{
    public function __construct(private InventaireClotureService $inventaire) {}

    public function verifier(Request $request, Dossier $dossier)
    {
        $this->authorize('cloturerDocuments', $dossier);

        $verifiable = $this->resoudrePiece($request, $dossier);

        ClotureVerification::updateOrCreate(
            [
                'verifiable_type' => $verifiable::class,
                'verifiable_id'   => $verifiable->getKey(),
            ],
            [
                'dossier_id'     => $dossier->id,
                'verifie_par_id' => auth()->id(),
                'verifie_at'     => now(),
            ],
        );

        return back()->with('success', 'Pièce marquée comme vérifiée.');
    }

    public function retirerVerification(Request $request, Dossier $dossier)
    {
        $this->authorize('cloturerDocuments', $dossier);

        $verifiable = $this->resoudrePiece($request, $dossier);

        ClotureVerification::where('verifiable_type', $verifiable::class)
            ->where('verifiable_id', $verifiable->getKey())
            ->delete();

        return back()->with('success', 'Vérification retirée.');
    }

    /**
     * Marque d'un coup toutes les pièces d'une rubrique — sur un dossier de vingt
     * pièces, cocher une par une est punitif.
     */
    public function verifierRubrique(Request $request, Dossier $dossier)
    {
        $this->authorize('cloturerDocuments', $dossier);

        $rubrique = $request->validate([
            'rubrique' => ['required', 'string', \Illuminate\Validation\Rule::enum(\App\Enums\RubriqueCloture::class)],
        ])['rubrique'];

        $pieces = $this->inventaire->pour($dossier)
            ->firstWhere('rubrique', $rubrique)['pieces'] ?? [];

        foreach ($pieces as $piece) {
            $classe = InventaireClotureService::classePourType($piece['type']);
            ClotureVerification::updateOrCreate(
                ['verifiable_type' => $classe, 'verifiable_id' => $piece['id']],
                ['dossier_id' => $dossier->id, 'verifie_par_id' => auth()->id(), 'verifie_at' => now()],
            );
        }

        return back()->with('success', count($pieces) . ' pièce(s) marquée(s) comme vérifiée(s).');
    }

    /**
     * Résout la pièce visée et **vérifie qu'elle appartient au dossier de l'URL**.
     *
     * Sans ce contrôle, l'autorisation porterait sur un dossier alors que la coche
     * s'appliquerait à la pièce d'un autre : il suffirait d'avoir accès à un dossier
     * pour marquer vérifiées les pièces de n'importe quel autre.
     */
    private function resoudrePiece(Request $request, Dossier $dossier): DocumentFichier|Courrier|Recu
    {
        $data = $request->validate([
            // Type court plutôt qu'un nom de classe : on n'instancie jamais une classe
            // arbitraire fournie par le client.
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(array_keys(InventaireClotureService::classesParType()))],
            'id'   => ['required', 'integer'],
        ]);

        $piece = match ($data['type']) {
            'document' => DocumentFichier::find($data['id']),
            'courrier' => Courrier::find($data['id']),
            'recu'     => Recu::with('paiement.facture')->find($data['id']),
        };

        abort_if(!$piece, 404, 'Pièce introuvable.');

        $dossierDeLaPiece = match (true) {
            $piece instanceof DocumentFichier => $piece->dossierGouvernant(),
            $piece instanceof Courrier        => $piece->dossier,
            $piece instanceof Recu            => $piece->paiement?->facture?->dossier,
        };

        abort_if(
            $dossierDeLaPiece?->id !== $dossier->id,
            403,
            'Cette pièce n\'appartient pas à ce dossier.'
        );

        return $piece;
    }
}
