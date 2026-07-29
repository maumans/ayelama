<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\LigneFacture;
use App\Models\Paiement;
use App\Models\Recu;
use App\Services\FactureGeneratorService;
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

    public function updatePaiement(Request $request, Paiement $paiement)
    {
        $dossier = $paiement->facture->dossier;
        $this->authorize('gererFacturation', $dossier);

        if ($paiement->recu) {
            throw ValidationException::withMessages([
                'montant' => ['Un reçu a déjà été émis pour ce paiement — il ne peut plus être modifié.'],
            ]);
        }

        $data = $request->validate([
            'date_paiement'  => ['required', 'date'],
            'montant'        => ['required', 'numeric', 'min:0.01'],
            'moyen_paiement' => ['nullable', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $avant = $paiement->only(['date_paiement', 'montant', 'moyen_paiement', 'notes']);
        $paiement->update($data);

        JournalActivite::enregistrer($dossier, 'Paiement modifié : ' . number_format((float) $avant['montant'], 0, ',', ' ')
            . ' GNF → ' . number_format((float) $paiement->montant, 0, ',', ' ') . ' GNF', 'facturation', ['avant' => $avant, 'apres' => $data]);

        return back()->with('success', 'Paiement mis à jour.');
    }

    public function destroyPaiement(Paiement $paiement)
    {
        $dossier = $paiement->facture->dossier;
        $this->authorize('gererFacturation', $dossier);

        if ($paiement->recu) {
            throw ValidationException::withMessages([
                'montant' => ['Un reçu a déjà été émis pour ce paiement — il ne peut plus être supprimé.'],
            ]);
        }

        $montant = (float) $paiement->montant;
        $paiement->delete();

        JournalActivite::enregistrer($dossier, 'Paiement supprimé : ' . number_format($montant, 0, ',', ' ') . ' GNF', 'facturation');

        return back()->with('success', 'Paiement supprimé.');
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

    public function telechargerPdf(Facture $facture, FactureGeneratorService $generatorService)
    {
        $this->authorize('view', $facture->dossier);

        // Rendu à la demande, jamais persisté (voir FactureGeneratorService) — le fichier
        // est donc supprimé une fois le téléchargement envoyé, pour ne pas accumuler de
        // nouveaux fichiers orphelins hors GED à chaque clic (voir ayelema:ged-lister-orphelins).
        $chemin = $generatorService->genererDocument($facture);
        $cheminAbsolu = Storage::disk('public')->path($chemin);

        // note_numero contient des "/" (ex. "001/MAB/26") — invalide dans un nom de fichier.
        $nomFichier = 'facture-' . str_replace('/', '-', $facture->note_numero) . '.docx';

        return response()->download($cheminAbsolu, $nomFichier)->deleteFileAfterSend(true);
    }

    /**
     * Verrou commun aux 3 méthodes ci-dessous : une fois qu'un paiement a été
     * enregistré sur la facture, la structure des lignes (et donc le total) ne
     * doit plus bouger sous les encaissements déjà effectués.
     */
    private function assertLignesModifiables(Facture $facture): void
    {
        if ($facture->paiements()->exists()) {
            throw ValidationException::withMessages([
                'quantite' => ['Impossible de modifier les lignes : des paiements ont déjà été enregistrés sur cette facture.'],
            ]);
        }
    }

    public function storeLigne(Request $request, Facture $facture)
    {
        $this->authorize('gererFacturation', $facture->dossier);
        $this->assertLignesModifiables($facture);

        $data = $request->validate([
            'designation' => ['required', 'string', 'max:255'],
            'quantite'    => ['required', 'integer', 'min:1'],
            'montant'     => ['required', 'numeric', 'min:0'],
        ]);

        LigneFacture::create([...$data, 'facture_id' => $facture->id]);
        $facture->recalculerTotal();

        JournalActivite::enregistrer($facture->dossier, "Ligne de facture ajoutée : {$data['designation']}", 'facturation');

        return back()->with('success', 'Ligne ajoutée.');
    }

    public function updateLigne(Request $request, LigneFacture $ligne)
    {
        $facture = $ligne->facture;
        $this->authorize('gererFacturation', $facture->dossier);
        $this->assertLignesModifiables($facture);

        $data = $request->validate([
            'designation' => ['required', 'string', 'max:255'],
            'quantite'    => ['required', 'integer', 'min:1'],
            'montant'     => ['required', 'numeric', 'min:0'],
        ]);

        $avant = $ligne->only(['designation', 'quantite', 'montant']);
        $ligne->update($data);
        $facture->recalculerTotal();

        JournalActivite::enregistrer($facture->dossier, "Ligne de facture modifiée : {$data['designation']}", 'facturation', ['avant' => $avant, 'apres' => $data]);

        return back()->with('success', 'Ligne mise à jour.');
    }

    public function destroyLigne(LigneFacture $ligne)
    {
        $facture = $ligne->facture;
        $this->authorize('gererFacturation', $facture->dossier);
        $this->assertLignesModifiables($facture);

        $designation = $ligne->designation;
        $ligne->delete();
        $facture->recalculerTotal();

        JournalActivite::enregistrer($facture->dossier, "Ligne de facture supprimée : {$designation}", 'facturation');

        return back()->with('success', 'Ligne supprimée.');
    }

    public function telechargerRecu(Recu $recu)
    {
        $recu->loadMissing('paiement.facture.dossier');
        $this->authorize('view', $recu->paiement->facture->dossier);

        abort_if(!$recu->chemin_fichier || !Storage::disk('public')->exists($recu->chemin_fichier), 404, 'Fichier introuvable.');

        return Storage::disk('public')->download($recu->chemin_fichier, "recu-{$recu->numero}.pdf");
    }

    public function apercuRecu(Recu $recu)
    {
        $recu->loadMissing('paiement.facture.dossier');
        $this->authorize('view', $recu->paiement->facture->dossier);

        abort_if(!$recu->chemin_fichier || !Storage::disk('public')->exists($recu->chemin_fichier), 404, 'Fichier introuvable.');

        $path = Storage::disk('public')->path($recu->chemin_fichier);

        return response()->file($path, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"recu-{$recu->numero}.pdf\"",
        ]);
    }
}
