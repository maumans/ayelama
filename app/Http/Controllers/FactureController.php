<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\Paiement;
use App\Models\Recu;
use App\Services\RecuPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class FactureController extends Controller
{
    /**
     * Requête filtrée partagée — même esprit que FormaliteController::baseQuery().
     */
    private function baseQuery(Request $request, $user)
    {
        return Facture::with(['dossier.typeActe', 'paiements'])
            ->whereHas('dossier', fn ($d) => $d->visiblePar($user))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->whereHas('dossier', fn ($d) =>
                    $d->where('reference', 'like', "%{$s}%")
                      ->orWhere('objet', 'like', "%{$s}%"))
                   ->orWhere('note_numero', 'like', "%{$s}%")))
            ->when($request->sort === 'montant', fn ($q) => $q->orderByDesc('total_chiffres'))
            ->when(!$request->sort, fn ($q) => $q->orderByDesc('note_date'));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user = auth()->user();

        if ($request->statut) {
            // Le statut (impayé/partiel/payé) est calculé, pas stocké en base — pas de
            // clause SQL possible ; on filtre en PHP puis on pagine manuellement la
            // collection filtrée (paginer avant de filtrer donnerait un total/nombre
            // de pages incohérent avec ce qui est réellement affiché).
            $tous = $this->baseQuery($request, $user)->get()
                ->map(fn ($f) => $f->versArray())
                ->filter(fn ($f) => $f['statut'] === $request->statut)
                ->values();

            $page = (int) $request->input('page', 1);
            $items = new \Illuminate\Pagination\LengthAwarePaginator(
                $tous->forPage($page, 20),
                $tous->count(),
                20,
                $page,
                ['path' => $request->url(), 'query' => $request->query()]
            );
        } else {
            $items = $this->baseQuery($request, $user)->paginate(20)->withQueryString()
                ->through(fn ($f) => $f->versArray());
        }

        $baseFactures = Facture::whereHas('dossier', fn ($d) => $d->visiblePar($user))->with('paiements')->get();

        $totalFacture  = (float) $baseFactures->sum('total_chiffres');
        $totalEncaisse = (float) $baseFactures->sum(fn ($f) => $f->totalPaye());
        $parStatut = ['impaye' => 0, 'partiel' => 0, 'paye' => 0];
        foreach ($baseFactures as $f) {
            $parStatut[$f->statutPaiement()]++;
        }

        return Inertia::render('Facturation/Index', [
            'factures' => $items,
            'stats' => [
                'totalFacture'  => $totalFacture,
                'totalEncaisse' => $totalEncaisse,
                'soldeRestant'  => round($totalFacture - $totalEncaisse, 2),
                'parStatut'     => $parStatut,
            ],
            'filters' => [
                'q'      => $request->q      ?? '',
                'statut' => $request->statut ?? '',
                'sort'   => $request->sort   ?? '',
            ],
        ]);
    }

    public function enregistrerPaiement(Request $request, Dossier $dossier)
    {
        $this->authorize('gererFacturation', $dossier);

        $facture = $dossier->factures()->latest('id')->first();
        abort_if(!$facture, 422, "Aucune facture n'existe encore pour ce dossier.");

        $data = $request->validate([
            'date_paiement'  => ['required', 'date'],
            'montant'        => ['required', 'numeric', 'min:0.01'],
            'moyen_paiement' => ['nullable', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $paiement = Paiement::create([
            ...$data,
            'facture_id'        => $facture->id,
            'enregistre_par_id' => auth()->id(),
        ]);

        JournalActivite::enregistrer(
            $dossier,
            'Paiement enregistré : ' . number_format((float) $paiement->montant, 0, ',', ' ') . ' GNF',
            'facturation'
        );

        return back()->with('success', 'Paiement enregistré.');
    }

    public function genererRecu(Request $request, Paiement $paiement, RecuPdfService $pdfService)
    {
        $dossier = $paiement->facture->dossier;
        $this->authorize('gererFacturation', $dossier);

        if ($paiement->recu) {
            throw ValidationException::withMessages([
                'paiement' => ['Un reçu a déjà été généré pour ce paiement.'],
            ]);
        }

        $recu = Recu::create([
            'paiement_id'   => $paiement->id,
            'numero'        => Recu::genererNumero($dossier),
            'date_emission' => now(),
        ]);

        $chemin = $pdfService->genererPdf($recu);
        $recu->update(['chemin_fichier' => $chemin]);

        JournalActivite::enregistrer($dossier, "Reçu généré : {$recu->numero}", 'facturation');

        return back()->with('success', "Reçu {$recu->numero} généré.");
    }

    public function telechargerRecu(Recu $recu)
    {
        $recu->loadMissing('paiement.facture.dossier');
        $this->authorize('view', $recu->paiement->facture->dossier);

        abort_if(!$recu->chemin_fichier || !Storage::disk('public')->exists($recu->chemin_fichier), 404, 'Fichier introuvable.');

        return Storage::disk('public')->download($recu->chemin_fichier, "recu-{$recu->numero}.pdf");
    }
}
