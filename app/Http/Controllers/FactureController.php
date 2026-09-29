<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Enums\TypeRemise;
use App\Models\Facture;
use App\Models\JournalActivite;
use App\Models\LigneFacture;
use App\Models\Paiement;
use App\Models\Recu;
use App\Services\FacturePdfService;
use App\Services\RecuPdfService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
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

    /**
     * Invariant de facturation : la somme des paiements d'une facture ne peut
     * jamais dépasser son total.
     *
     * Contrôlé sous verrou de ligne (`lockForUpdate` sur la facture, posé par
     * l'appelant) : sans ça, deux encaissements concurrents pourraient chacun
     * valider face au même solde et le dépasser à eux deux.
     *
     * Le pendant de cet invariant est déjà en place côté total : les lignes ne
     * sont plus modifiables dès qu'un paiement existe (assertLignesModifiables)
     * et la régénération de facture est refusée (FacturationService::genererFacture).
     * Le total ne peut donc pas non plus descendre sous les paiements déjà reçus.
     */
    private function assertMontantDansSolde(Facture $facture, float $montant, ?int $saufPaiementId = null): void
    {
        if ((float) $facture->total_chiffres <= 0) {
            throw ValidationException::withMessages([
                'montant' => ["Cette facture n'a aucun montant à encaisser (total à 0 GNF) — ajoutez d'abord une ligne."],
            ]);
        }

        $disponible = $facture->soldeDisponible($saufPaiementId);

        if ($disponible <= 0) {
            throw ValidationException::withMessages([
                'montant' => ['Cette facture est déjà entièrement soldée — aucun paiement supplémentaire ne peut être enregistré.'],
            ]);
        }

        // Comparaison sur des montants arrondis à 2 décimales : les deux valeurs
        // viennent de colonnes decimal(.,2), un test strict sur des flottants
        // rejetterait à tort un paiement soldant exactement la facture.
        if (round($montant, 2) > $disponible) {
            $fmt = fn (float $v) => number_format($v, 0, ',', ' ');

            throw ValidationException::withMessages([
                'montant' => [
                    "Le montant dépasse le solde restant dû. Total facturé : {$fmt((float) $facture->total_chiffres)} GNF, "
                    . "déjà encaissé : {$fmt($facture->totalPayeEnBase($saufPaiementId))} GNF, "
                    . "maximum encaissable ici : {$fmt($disponible)} GNF.",
                ],
            ]);
        }
    }

    public function enregistrerPaiement(Request $request, Dossier $dossier)
    {
        $this->authorize('gererFacturation', $dossier);

        $factureId = $dossier->factures()->latest('id')->value('id');
        abort_if(!$factureId, 422, "Aucune facture n'existe encore pour ce dossier.");

        $data = $request->validate([
            'date_paiement'  => ['required', 'date'],
            'montant'        => ['required', 'numeric', 'min:0.01'],
            'moyen_paiement' => ['nullable', 'string', 'max:30'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        $paiement = DB::transaction(function () use ($factureId, $data) {
            // Le verrou sérialise les encaissements concurrents sur cette facture.
            $facture = Facture::whereKey($factureId)->lockForUpdate()->firstOrFail();

            $this->assertMontantDansSolde($facture, (float) $data['montant']);

            return Paiement::create([
                ...$data,
                'facture_id'        => $facture->id,
                'enregistre_par_id' => auth()->id(),
            ]);
        });

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

        DB::transaction(function () use ($paiement, $data) {
            $facture = Facture::whereKey($paiement->facture_id)->lockForUpdate()->firstOrFail();

            // Le paiement en cours d'édition libère son propre montant : le porter
            // de 100 000 à 120 000 sur une facture soldée à 100 000 doit être
            // refusé, mais le ramener à 80 000 doit passer.
            $this->assertMontantDansSolde($facture, (float) $data['montant'], $paiement->id);

            $paiement->update($data);
        });

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

    /**
     * La note de frais en **PDF**.
     *
     * ⚠️ Cette méthode s'appelait déjà `telechargerPdf` mais renvoyait un **`.docx`** : elle rendait
     * le gabarit Word dans un fichier temporaire, puis l'expédiait avec `deleteFileAfterSend`.
     * L'étude attendait un PDF — et recevait par intermittence un `telecharger.htm`, la page HTML
     * d'erreur enregistrée par l'attribut `download` du lien sous le nom du dernier segment d'URL.
     *
     * Le PDF est désormais rendu **en mémoire** (voir FacturePdfService), ce qui supprime au passage
     * le fichier temporaire d'où venait cette fragilité.
     */
    public function telechargerPdf(Facture $facture, FacturePdfService $pdfService)
    {
        $this->authorize('view', $facture->dossier);

        return response($pdfService->rendre($facture), 200, [
            'Content-Type'        => 'application/pdf',
            // `attachment` et non `inline` : le bouton dit « Télécharger ». L'aperçu dans le
            // navigateur passe par la GED, qui a son propre écran.
            'Content-Disposition' => 'attachment; filename="' . $pdfService->nomFichier($facture) . '"',
        ]);
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

        LigneFacture::create([
            ...$data,
            'facture_id' => $facture->id,
            // Une ligne ajoutée à la main est remisable : l'étude l'a créée délibérément, et
            // aucun barème ne dit qu'elle serait un débours. Les lignes générées, elles,
            // héritent du drapeau de leur barème.
            'remise_autorisee' => true,
        ]);
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
            'designation'   => ['required', 'string', 'max:255'],
            'quantite'      => ['required', 'integer', 'min:1'],
            'montant'       => ['required', 'numeric', 'min:0'],
            // La remise telle qu'elle a été **exprimée** : son type et sa valeur. Le montant
            // qui en résulte n'est pas posté — il se calcule, et deux sources divergeraient.
            'remise_type'   => ['nullable', Rule::enum(TypeRemise::class)],
            'remise_valeur' => ['nullable', 'numeric', 'min:0', 'required_with:remise_type'],
        ]);

        $remise = $this->remiseValidee($ligne, $data);

        $avant = $ligne->only(['designation', 'quantite', 'montant', 'remise_type', 'remise_valeur']);
        $ligne->update([...$data, ...$remise]);
        $facture->recalculerTotal();

        JournalActivite::enregistrer(
            $facture->dossier,
            $this->libelleModificationLigne($ligne, $avant),
            'facturation',
            ['avant' => $avant, 'apres' => $ligne->only(['designation', 'quantite', 'montant', 'remise_type', 'remise_valeur'])],
        );

        return back()->with('success', 'Ligne mise à jour.');
    }

    /**
     * Contrôle la remise demandée et la rend sous sa forme persistable.
     *
     * Trois refus, chacun pour une raison différente :
     *
     *   - **ligne non remisable** : un débours est de l'argent que l'étude avance pour le
     *     client — elle paie 180 000 GNF au greffe quoi qu'il arrive. Le refuser côté serveur
     *     et pas seulement à l'écran : une règle ne peut pas ne vivre que dans le formulaire ;
     *   - **pourcentage au-delà de 100** : une ligne ne peut pas être remisée plus qu'elle ne vaut ;
     *   - **montant au-delà du brut** : idem, et le message dit le plafond.
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function remiseValidee(LigneFacture $ligne, array $data): array
    {
        $type = $data['remise_type'] ?? null;

        // Retirer la remise est toujours permis, même sur une ligne devenue non remisable.
        if (! $type || ($data['remise_valeur'] ?? 0) <= 0) {
            return ['remise_type' => null, 'remise_valeur' => null];
        }

        if (! $ligne->remiseAutorisee()) {
            throw ValidationException::withMessages([
                'remise_valeur' => ["Cette ligne n'accepte pas de remise : c'est un débours, que l'étude verse intégralement à un tiers. Ouvrez la remise sur son barème si c'est voulu."],
            ]);
        }

        $type   = $type instanceof TypeRemise ? $type : TypeRemise::from($type);
        $valeur = (float) $data['remise_valeur'];

        // Le brut est calculé sur les **nouvelles** quantité et montant, pas sur les anciennes :
        // l'utilisateur peut changer les trois d'un coup.
        $brut = round(((int) $data['quantite']) * ((float) $data['montant']), 2);

        if ($type === TypeRemise::Pourcentage && $valeur > 100) {
            throw ValidationException::withMessages([
                'remise_valeur' => ['Une remise ne peut pas dépasser 100 %.'],
            ]);
        }

        if ($type === TypeRemise::Montant && $valeur > $brut) {
            throw ValidationException::withMessages([
                'remise_valeur' => [sprintf(
                    'La remise ne peut pas dépasser le montant de la ligne (%s GNF).',
                    number_format($brut, 0, ',', ' '),
                )],
            ]);
        }

        return ['remise_type' => $type->value, 'remise_valeur' => $valeur];
    }

    /** Le journal dit ce qui a changé, et nomme la remise quand il y en a une. */
    private function libelleModificationLigne(LigneFacture $ligne, array $avant): string
    {
        $base = "Ligne de facture modifiée : {$ligne->designation}";

        if ($ligne->remiseMontant() > 0) {
            return $base . sprintf(
                ' — remise de %s GNF (%s %%)',
                number_format($ligne->remiseMontant(), 0, ',', ' '),
                rtrim(rtrim(number_format($ligne->remisePourcentage(), 2, ',', ' '), '0'), ','),
            );
        }

        if (($avant['remise_type'] ?? null) !== null) {
            return $base . ' — remise retirée';
        }

        return $base;
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
