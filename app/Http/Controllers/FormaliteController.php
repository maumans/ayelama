<?php

namespace App\Http\Controllers;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutFormalite;
use App\Models\Dossier;
use App\Models\Formalite;
use App\Models\FormalitePiece;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FormaliteController extends Controller
{
    /**
     * Requête filtrée partagée entre index() (liste paginée) et exportCsv()
     * (export intégral, mêmes filtres) — évite que les deux divergent.
     */
    private function baseQuery(Request $request, $user)
    {
        return Formalite::with(['dossier.typeActe', 'dossier.parties.client', 'pieces', 'dependDe', 'dependants'])
            ->whereHas('dossier', fn ($d) => $d->visiblePar($user))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->whereHas('dossier', fn ($d) =>
                    $d->where('reference', 'like', "%{$s}%")
                      ->orWhere('objet', 'like', "%{$s}%"))
                   ->orWhere('organisme', 'like', "%{$s}%")))
            ->when($request->statut,      fn ($q, $s) => $q->where('statut', $s))
            ->when(!$request->statut,     fn ($q)     => $q->where('statut', '!=', 'cloture'))
            ->when($request->organisme,   fn ($q, $s) => $q->where('organisme', $s))
            ->when($request->urgentes === '1', fn ($q) => $q->urgentes())
            ->when($request->sort === 'montant',   fn ($q) => $q->orderByDesc('montant_calcule')->orderBy('ordre'))
            ->when($request->sort === 'organisme', fn ($q) => $q->orderBy('organisme')->orderBy('ordre'))
            ->when(!in_array($request->sort, ['montant', 'organisme']), fn ($q) =>
                $q->orderByRaw('echeance_at IS NULL, echeance_at ASC')->orderBy('ordre'));
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user = auth()->user();

        $query = $this->baseQuery($request, $user);

        $baseStats = Formalite::whereHas('dossier', fn ($d) => $d->visiblePar($user));

        $stats = [
            'total'       => (clone $baseStats)->where('statut', '!=', 'cloture')->count(),
            'aDeposer'    => (clone $baseStats)->where('statut', 'a_deposer')->count(),
            'enCours'     => (clone $baseStats)->whereIn('statut', ['depose', 'en_attente'])->count(),
            'retourRecu'  => (clone $baseStats)->where('statut', 'retour_recu')->count(),
            'urgentes'    => (clone $baseStats)->urgentes()->count(),
            'montantTotal' => (float) (clone $baseStats)->where('statut', '!=', 'cloture')->sum('montant_calcule'),
            'parOrganisme' => (clone $baseStats)->where('statut', '!=', 'cloture')
                ->selectRaw('organisme, count(*) as total')
                ->groupBy('organisme')
                ->pluck('total', 'organisme'),
        ];

        $formalites = $query->paginate(20)->withQueryString();

        return Inertia::render('Formalites/Index', [
            'formalites' => $formalites->through(fn ($f) => $f->versArray($user)),
            'stats'   => $stats,
            'statuts' => collect(StatutFormalite::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => match($s) {
                    StatutFormalite::ADeposer    => 'À déposer',
                    StatutFormalite::Depose      => 'Déposé',
                    StatutFormalite::EnAttente   => 'En attente retour',
                    StatutFormalite::RetourRecu  => 'Retour reçu',
                    StatutFormalite::Rejete      => 'Rejeté — à corriger',
                    StatutFormalite::Cloture     => 'Clôturé',
                },
            ]),
            'filters' => [
                'q'         => $request->q         ?? '',
                'statut'    => $request->statut     ?? '',
                'organisme' => $request->organisme  ?? '',
                'urgentes'  => $request->urgentes   ?? '',
                'sort'      => $request->sort       ?? '',
            ],
        ]);
    }

    public function exportCsv(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        $user = auth()->user();
        $formalites = $this->baseQuery($request, $user)->get();

        $colonnes = ['Dossier', 'Client', 'Organisme', 'Démarche', 'Statut', 'Frais (GNF)', 'Délai (jours)', 'Échéance', 'Date dépôt', 'Date retour'];

        return response()->streamDownload(function () use ($formalites, $colonnes) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $colonnes, ',', '"', '\\');

            foreach ($formalites as $f) {
                $a = $f->versArray();
                fputcsv($out, [
                    $a['dossier']['reference'],
                    $a['dossier']['clientPrincipal'],
                    $a['organismeLabel'],
                    $a['libelle'],
                    $a['statut'],
                    $a['montant_calcule'],
                    $a['joursRetardOuAvance'],
                    $a['echeance_at'],
                    $a['depose_at'],
                    $a['retour_at'],
                ], ',', '"', '\\');
            }

            fclose($out);
        }, 'formalites_' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('gererFormalites', $dossier);

        $data = $request->validate([
            'organisme'    => ['required', 'string', 'in:apip,impots,conservation_fonciere,cnss,greffe'],
            'libelle'      => ['nullable', 'string', 'max:150'],
            'statut'       => ['required', 'string'],
            'montant_base' => ['nullable', 'numeric', 'min:0'],
            'taux'         => ['nullable', 'numeric', 'min:0', 'max:1'],
            'echeance_at'  => ['nullable', 'date'],
            'type_impot'   => ['nullable', 'string', 'max:100'],
            'pieces'       => ['nullable', 'array'],
            'pieces.*.label' => ['required_with:pieces', 'string', 'max:200'],
        ]);

        $formalite = Formalite::create(array_merge(
            ['dossier_id' => $dossier->id],
            collect($data)->except('pieces')->toArray()
        ));

        if ($formalite->taux && $formalite->montant_base) {
            $formalite->calculerMontant();
        }

        foreach ($data['pieces'] ?? [] as $piece) {
            FormalitePiece::create(['formalite_id' => $formalite->id, 'label' => $piece['label'], 'est_fourni' => false]);
        }

        JournalActivite::enregistrer($dossier, "Formalité ajoutée : {$formalite->labelAffiche()}", 'formalite');

        return back()->with('success', 'Formalité créée.');
    }

    public function update(Request $request, Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        $data = $request->validate([
            'statut'     => ['sometimes', 'string'],
            'depose_at'  => ['sometimes', 'nullable', 'date'],
            'retour_at'  => ['sometimes', 'nullable', 'date'],
            'pieces'     => ['sometimes', 'array'],
            'pieces.*.id'         => ['required_with:pieces', 'integer'],
            'pieces.*.est_fourni' => ['required_with:pieces', 'boolean'],
        ]);

        if (isset($data['pieces'])) {
            foreach ($data['pieces'] as $pieceData) {
                FormalitePiece::where('id', $pieceData['id'])
                    ->where('formalite_id', $formalite->id)
                    ->update([
                        'est_fourni' => $pieceData['est_fourni'],
                        'fourni_at'  => $pieceData['est_fourni'] ? now() : null,
                    ]);
            }
            unset($data['pieces']);
        }

        $formalite->update($data);

        JournalActivite::enregistrer(
            $formalite->dossier,
            "Formalité mise à jour : {$formalite->labelAffiche()} → statut {$formalite->statut?->value}",
            'formalite'
        );

        return back()->with('success', 'Formalité mise à jour.');
    }

    /**
     * Flux guidé "Enregistrer un dépôt" (maquette 2) : capture la date de dépôt,
     * le montant réellement payé et le n° de récépissé, puis recalcule la date de
     * retour prévue à partir de la date réelle de dépôt (et non plus depuis la
     * création du dossier, comme le faisait la génération initiale). Toutes les
     * pièces requises doivent être marquées fournies avant de pouvoir déposer.
     */
    public function deposer(Request $request, Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        abort_if($formalite->estBloquee(), 422, 'Cette démarche est bloquée par une dépendance non résolue.');

        $piecesManquantes = $formalite->pieces()->where('est_fourni', false)->exists();
        if ($piecesManquantes) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pieces' => ['Toutes les pièces requises doivent être marquées fournies avant de confirmer le dépôt.'],
            ]);
        }

        $data = $request->validate([
            'date_depot'       => ['nullable', 'date'],
            'montant_paye'     => ['nullable', 'numeric', 'min:0'],
            'numero_recepisse' => ['required', 'string', 'max:100'],
        ]);

        $dateDepot = $data['date_depot'] ?? now()->toDateString();

        $formalite->update([
            'statut'           => 'depose',
            'depose_at'        => $dateDepot,
            'montant_paye'     => $data['montant_paye'] ?? null,
            'numero_recepisse' => $data['numero_recepisse'],
            'echeance_at'      => $formalite->delai_heures
                ? \Carbon\Carbon::parse($dateDepot)->addHours($formalite->delai_heures)
                : $formalite->echeance_at,
        ]);

        JournalActivite::enregistrer(
            $formalite->dossier,
            "Dépôt enregistré : {$formalite->labelAffiche()} (récépissé {$data['numero_recepisse']})",
            'formalite'
        );

        return back()->with('success', 'Dépôt enregistré.');
    }

    /**
     * Flux guidé "Enregistrer un retour" (maquette 3) : résultat positif ou rejeté,
     * référence du document reçu. Le déblocage des démarches dépendantes (ex. Greffe
     * après réception du RCCM) est purement dérivé — Formalite::estBloquee() relit
     * le statut de la démarche dont on dépend, aucune donnée supplémentaire à mettre à jour.
     */
    public function retour(Request $request, Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        $data = $request->validate([
            'resultat'                => ['required', 'string', 'in:recu,rejete'],
            'date_retour'             => ['required', 'date'],
            'reference_document_recu' => ['required_if:resultat,recu', 'nullable', 'string', 'max:200'],
        ]);

        $formalite->update([
            'statut'                  => $data['resultat'] === 'recu' ? 'retour_recu' : 'rejete',
            'retour_at'               => $data['date_retour'],
            'reference_document_recu' => $data['reference_document_recu'] ?? null,
        ]);

        $verdict = $data['resultat'] === 'recu' ? 'positif' : 'rejeté';
        JournalActivite::enregistrer(
            $formalite->dossier,
            "Retour {$verdict} enregistré : {$formalite->labelAffiche()}",
            'formalite'
        );

        return back()->with('success', 'Retour enregistré.');
    }

    /**
     * Autres démarches en retard chez le même organisme, tous dossiers visibles
     * confondus — affiché comme rappel dans le formulaire de retour (maquette 3) :
     * le formaliste, déjà physiquement au guichet, peut en profiter pour relancer.
     */
    public function autresRetardsMemeOrganisme(Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        $user = auth()->user();

        $autres = Formalite::with('dossier')
            ->where('organisme', $formalite->organisme)
            ->where('id', '!=', $formalite->id)
            ->whereNotIn('statut', ['retour_recu', 'cloture'])
            ->whereNotNull('echeance_at')
            ->where('echeance_at', '<', now())
            ->whereHas('dossier', fn ($d) => $d->visiblePar($user))
            ->get()
            ->map(fn ($f) => [
                'id'              => $f->id,
                'libelle'         => $f->labelAffiche(),
                'dossierReference' => $f->dossier?->reference,
                'dossierObjet'    => $f->dossier?->objet,
                'joursRetard'     => $f->joursRetardOuAvance(),
            ])
            ->values();

        return response()->json(['formalites' => $autres]);
    }

    public function televerserPiece(Request $request, FormalitePiece $piece)
    {
        $this->authorize('gererFormalites', $piece->formalite->dossier);

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        if ($piece->chemin_fichier) {
            Storage::disk('public')->delete($piece->chemin_fichier);
        }

        $fichier = $request->file('fichier');
        $dossierRef = $piece->formalite->dossier->reference;
        $nomOriginal = $fichier->getClientOriginalName();

        $path = $fichier->storeAs(
            'formalites/' . $dossierRef,
            $piece->id . '_' . Str::slug($piece->label) . '.' . $fichier->extension(),
            'public'
        );

        $piece->update([
            'chemin_fichier'   => $path,
            'nom_original'     => $nomOriginal,
            'mime_type'        => $fichier->getClientMimeType(),
            'taille_octets'    => $fichier->getSize(),
            'televerse_par_id' => auth()->id(),
            'televerse_at'     => now(),
            'est_fourni'       => true,
            'fourni_at'        => now(),
        ]);

        return back()->with('success', 'Pièce téléversée.');
    }

    public function telechargerPiece(FormalitePiece $piece)
    {
        $this->authorize('view', $piece->formalite->dossier);

        if (!$piece->chemin_fichier || !Storage::disk('public')->exists($piece->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('public')->download($piece->chemin_fichier, $piece->nom_original ?: $piece->label);
    }

    public function destroy(Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        abort_if(
            $formalite->statut?->value === 'cloture',
            403,
            'Une formalité clôturée ne peut pas être supprimée.'
        );

        $label   = $formalite->labelAffiche();
        $dossier = $formalite->dossier;

        $formalite->pieces()->delete();
        $formalite->delete();

        JournalActivite::enregistrer($dossier, "Formalité supprimée : {$label}", 'formalite');

        return back()->with('success', "Formalité {$label} supprimée.");
    }
}
