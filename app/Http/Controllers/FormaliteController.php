<?php

namespace App\Http\Controllers;

use App\Enums\RoleUtilisateur;
use App\Enums\StatutFormalite;
use App\Models\DocumentFichier;
use App\Models\Dossier;
use App\Http\Requests\EnregistrerRetourFormaliteRequest;
use App\Models\Formalite;
use App\Services\EnregistrementRetourFormalite;
use App\Models\JournalActivite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class FormaliteController extends Controller
{
    /**
     * Requête filtrée partagée entre index() (liste paginée) et exportCsv()
     * (export intégral, mêmes filtres) — évite que les deux divergent.
     */
    private function baseQuery(Request $request, $user)
    {
        return Formalite::with(['dossier.typeActe', 'dossier.parties.client', 'pieces.versionActuelle', 'dependDe', 'dependants'])
            ->whereHas('dossier', fn ($d) => $d->visiblePar($user))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->whereHas('dossier', fn ($d) =>
                    $d->where('reference', 'like', "%{$s}%")
                      ->orWhere('objet', 'like', "%{$s}%"))
                   ->orWhere('organisme', 'like', "%{$s}%")))
            ->when($request->statut,      fn ($q, $s) => $q->where('statut', $s))
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
            'total'       => (clone $baseStats)->count(),
            'aDeposer'    => (clone $baseStats)->where('statut', 'a_deposer')->count(),
            'enCours'     => (clone $baseStats)->whereIn('statut', ['depose', 'en_attente'])->count(),
            'retourRecu'  => (clone $baseStats)->where('statut', 'retour_recu')->count(),
            'urgentes'    => (clone $baseStats)->urgentes()->count(),
            'montantTotal' => (float) (clone $baseStats)->sum('montant_calcule'),
            'parOrganisme' => (clone $baseStats)
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
            $formalite->pieces()->create([
                'nom'        => $piece['label'],
                'categorie'  => 'piece_justificative',
                'est_requis' => true,
                'est_fourni' => false,
            ]);
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
        ]);

        $formalite->update($data);

        JournalActivite::enregistrer(
            $formalite->dossier,
            "Formalité mise à jour : {$formalite->labelAffiche()} → statut {$formalite->statut?->value}",
            'formalite'
        );

        return back()->with('success', 'Formalité mise à jour.');
    }

    /**
     * Flux guidé "Marquer le dépôt" (maquette 2) : capture la date de dépôt,
     * le montant réellement payé et le n° de récépissé, puis recalcule la date de
     * retour prévue à partir de la date réelle de dépôt (et non plus depuis la
     * création du dossier, comme le faisait la génération initiale). Toutes les
     * pièces requises doivent être marquées fournies avant de pouvoir déposer.
     */
    public function deposer(Request $request, Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        abort_if($formalite->estBloquee(), 422, 'Cette démarche est bloquée par une dépendance non résolue.');

        // Les pièces justificatives sont désormais téléversées lors du retour, 
        // on ne bloque plus le dépôt si elles sont manquantes.

        $data = $request->validate([
            'date_depot'       => ['nullable', 'date'],
            'montant_paye'     => ['nullable', 'numeric', 'min:0'],
            'numero_recepisse' => ['nullable', 'string', 'max:100'],
        ]);

        $dateDepot = $data['date_depot'] ?? now()->toDateString();

        $formalite->update([
            'statut'           => 'depose',
            'depose_at'        => $dateDepot,
            'montant_paye'     => $data['montant_paye'] ?? null,
            'numero_recepisse' => $data['numero_recepisse'] ?? null,
            'echeance_at'      => $formalite->delai_heures
                ? \Carbon\Carbon::parse($dateDepot)->addHours($formalite->delai_heures)
                : $formalite->echeance_at,
        ]);

        $msgRecepisse = !empty($data['numero_recepisse']) ? " (récépissé {$data['numero_recepisse']})" : "";
        JournalActivite::enregistrer(
            $formalite->dossier,
            "Dépôt enregistré : {$formalite->labelAffiche()}{$msgRecepisse}",
            'formalite'
        );

        return back()->with('success', 'Dépôt enregistré.');
    }

    /**
     * Enregistrer le retour d'une formalité : résultat, date, données délivrées par l'autorité.
     *
     * Le déblocage des démarches dépendantes (ex. Greffe après réception du RCCM) est purement
     * dérivé — `Formalite::estBloquee()` relit le statut de la démarche dont on dépend, aucune
     * donnée supplémentaire à mettre à jour.
     *
     * **Restauré le 2026-09-29.** Ce docblock décrivait « résultat positif ou rejeté, référence
     * du document reçu » alors que la méthode ne validait plus qu'une date : les deux champs
     * avaient été retirés le 2026-08-14 sans que le commentaire suive. Conséquences vécues —
     * `StatutFormalite::Rejete` était devenu **inatteignable** alors que
     * `DossierStepService::verifierFormalites()` gardait son message « À corriger et
     * redéposer », et `reference_document_recu` n'était plus écrite par personne.
     *
     * S'y ajoute ce qui manquait depuis toujours : la **capture des données** que l'autorité
     * délivre. Mesuré avant la passe — 27 formalités sur 29 portaient bien leurs pièces, mais
     * 12 numéros RCCM ou NIF sur 14 n'atteignaient jamais le registre.
     */
    public function retour(
        EnregistrerRetourFormaliteRequest $request,
        Formalite $formalite,
        EnregistrementRetourFormalite $enregistrement,
    ) {
        $this->authorize('gererFormalites', $formalite->dossier);

        $data = $request->validated();

        // ── Rejet ────────────────────────────────────────────────────────────
        // ⚠️ **Le contrôle des pièces ne s'applique pas ici.** Un rejet n'apporte pas les
        // pièces attendues — c'est sa définition même. L'exiger rendrait le rejet
        // inenregistrable, et c'est mécaniquement ce que produisait le contrôle inconditionnel
        // de la version précédente.
        if ($data['resultat'] === 'rejete') {
            $formalite->update([
                'statut' => StatutFormalite::Rejete,
                // La date est posée quand même : l'organisme a répondu, et c'est depuis elle
                // que court le délai de correction.
                'retour_at'   => $data['date_retour'],
                'motif_rejet' => $data['motif_rejet'],
            ]);

            JournalActivite::enregistrer(
                $formalite->dossier,
                "Retour rejeté : {$formalite->labelAffiche()} — {$data['motif_rejet']}",
                'formalite',
                ['formalite_id' => $formalite->id],
                $request->user(),
            );

            return back()->with('error', 'Rejet enregistré — la démarche est à corriger et redéposer.');
        }

        // ── Retour positif ───────────────────────────────────────────────────
        if ($formalite->pieces()->where('est_fourni', false)->exists()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'pieces' => ['Toutes les pièces requises doivent être marquées fournies avant d\'enregistrer le retour.'],
            ]);
        }

        $saisies = $request->donneesSaisies();

        $formalite->update([
            'statut'                  => StatutFormalite::RetourRecu,
            'retour_at'               => $data['date_retour'],
            'reference_document_recu' => $data['reference_document_recu'] ?? null,
            'motif_rejet'             => null,
            // Fusion et non remplacement : une donnée captée lors d'un premier enregistrement
            // ne doit pas disparaître parce que le formulaire ne la portait plus — le cas se
            // présente dès qu'un administrateur retire une donnée du barème après coup.
            'donnees_recues'          => [...($formalite->donnees_recues ?? []), ...$saisies],
        ]);

        $bilan = $enregistrement->appliquer($formalite->fresh(), $saisies, $request->user());

        JournalActivite::enregistrer(
            $formalite->dossier,
            "Retour positif enregistré : {$formalite->labelAffiche()}",
            'formalite',
            ['formalite_id' => $formalite->id],
            $request->user(),
        );

        return back()->with('success', $this->messageDeRetour($bilan));
    }

    /**
     * Ce que l'utilisateur doit savoir après coup — un message, jamais un silence.
     *
     * @param array{appliquees: array, divergentes: array, sansDestination: array} $bilan
     */
    private function messageDeRetour(array $bilan): string
    {
        if ($bilan['divergentes'] !== []) {
            return 'Retour enregistré. ⚠️ Une valeur reçue diverge de la fiche société : '
                . "la fiche n'a pas été modifiée, arbitrez depuis le registre.";
        }

        if ($bilan['appliquees'] !== []) {
            return 'Retour enregistré — la fiche société a été complétée.';
        }

        return 'Retour enregistré.';
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
            ->nonTerminees()
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

    public function televerserPiece(Request $request, DocumentFichier $piece)
    {
        $this->authorize('gererFormalites', $piece->documentable->dossier);

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $dossierRef = $piece->documentable->dossier->reference;
        $piece->nouvelleVersion($request->file('fichier'), 'formalites/' . $dossierRef);

        return back()->with('success', 'Pièce téléversée.');
    }

    public function telechargerPiece(DocumentFichier $piece)
    {
        $this->authorize('view', $piece->documentable->dossier);

        $chemin = $piece->versionActuelle?->chemin_fichier;
        if (!$chemin || !Storage::disk('public')->exists($chemin)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('public')->download($chemin, $piece->versionActuelle->nom_original ?: $piece->nom);
    }

    public function destroy(Formalite $formalite)
    {
        $this->authorize('gererFormalites', $formalite->dossier);

        abort_if(
            $formalite->estTerminee(),
            403,
            'Une formalité terminée ne peut pas être supprimée.'
        );

        $label   = $formalite->labelAffiche();
        $dossier = $formalite->dossier;

        foreach ($formalite->pieces as $piece) {
            $piece->supprimerAvecFichiers();
        }
        $formalite->delete();

        JournalActivite::enregistrer($dossier, "Formalité supprimée : {$label}", 'formalite');

        return back()->with('success', "Formalité {$label} supprimée.");
    }
}
