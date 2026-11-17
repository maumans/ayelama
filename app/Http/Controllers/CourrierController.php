<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleCourrier;
use App\Services\ActesGeneratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CourrierController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Dossier::class);

        // Cloisonnement par assignation, comme FormaliteController::index() et
        // GedController::index() : sans ce filtre, tout utilisateur connecté voyait les
        // courriers de tous les dossiers de l'office, y compris ceux auxquels il n'a
        // aucun accès depuis la fiche dossier.
        $user = auth()->user();
        $visibles = fn ($q) => $q->whereHas('dossier', fn ($d) => $d->visiblePar($user));

        // Les colonnes d'assignation sont lues par CourrierPolicy (`peut_supprimer` ci-dessous) :
        // sans elles, la policy verrait un dossier sans personne et refuserait tout non-admin.
        $query = Courrier::with(['dossier:id,reference,objet,redacteur_id,reviseur_id,notaire_id,formaliste_id', 'redacteur:id,name,initiales'])
            ->tap($visibles)
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q2) =>
                $q2->where('reference',    'like', "%{$s}%")
                   ->orWhere('objet',       'like', "%{$s}%")
                   ->orWhere('destinataire','like', "%{$s}%")))
            ->when($request->type,   fn ($q, $t) => $q->where('type', $t))
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->sort === 'objet',  fn ($q) => $q->orderBy('objet'))
            ->when($request->sort === 'envoye', fn ($q) => $q->orderByDesc('envoye_at'))
            ->when(!in_array($request->sort, ['objet', 'envoye']), fn ($q) => $q->orderByDesc('created_at'));

        // Compteurs sur le même périmètre que la liste : afficher « 47 courriers » au-dessus
        // d'une liste qui en montre 3 est un bug d'affichage autant qu'une fuite.
        $stats = [
            'total'      => Courrier::tap($visibles)->count(),
            'brouillons' => Courrier::tap($visibles)->brouillon()->count(),
            'envoyes'    => Courrier::tap($visibles)->envoye()->count(),
            'ceMois'     => Courrier::tap($visibles)
                                ->whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)->count(),
            'parType'    => Courrier::tap($visibles)
                                ->selectRaw('type, count(*) as n')
                                ->groupBy('type')
                                ->get()
                                ->map(fn ($r) => ['type' => $r->type, 'count' => (int) $r->n])
                                ->values(),
        ];

        $courriers = $query->paginate(20)->withQueryString()->through(fn ($c) => [
            'id'           => $c->id,
            'reference'    => $c->reference,
            'objet'        => $c->objet,
            'destinataire' => $c->destinataire,
            'adresse'      => $c->adresse,
            'type'         => $c->type,
            'typeLabel'    => $c->typeLabel(),
            'statut'       => $c->statut,
            'contenu'      => $c->contenu,
            'envoye_at'    => $c->envoye_at?->format('d/m/Y'),
            'transmis_par' => $c->transmis_par,
            'bornes_envoi' => $c->bornesDateEnvoi(),
            'redacteur'    => $c->redacteur?->name,
            'initiales'    => $c->redacteur?->initiales,
            'dossier_id'   => $c->dossier_id,
            'dossierRef'   => $c->dossier?->reference,
            'dossierObjet' => $c->dossier?->objet,
            'created_at'   => $c->created_at->format('d/m/Y'),
            // Le bouton « Supprimer » s'affichait à tous — y compris au comptable, qui voit tous les
            // dossiers mais que CourrierPolicy exclut, et sur un courrier signé, que `destroy`
            // refuse (retour R07, 2026-11-16).
            'peut_supprimer' => ! $c->est_signe_cachete && $user->can('delete', $c),
            'chemin_fichier' => $c->chemin_fichier,
            'has_file'       => (bool) $c->chemin_fichier,
            'url_download'   => $c->chemin_fichier ? route('courriers.download', $c) : null,
            'url_preview'    => $c->chemin_fichier ? route('courriers.preview', $c) : null,
        ]);

        return Inertia::render('Courriers/Index', [
            'courriers' => $courriers,
            'dossiers'  => Dossier::whereNotIn('etape', ['cloture'])
                              ->orderBy('reference')
                              ->get(['id', 'reference', 'objet']),
            'stats'   => $stats,
            'filters' => [
                'q'      => $request->q      ?? '',
                'type'   => $request->type   ?? '',
                'statut' => $request->statut ?? '',
                'sort'   => $request->sort   ?? '',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Courrier::class);

        $data = $request->validate([
            'destinataire' => ['required', 'string', 'max:300'],
            'adresse'      => ['nullable', 'string', 'max:500'],
            'objet'        => ['required', 'string', 'max:500'],
            'type'         => ['required', 'in:transmission,convocation,relance,divers'],
            'contenu'      => ['nullable', 'string'],
            'dossier_id'   => ['nullable', 'exists:dossiers,id'],
        ]);

        if (!empty($data['dossier_id'])) {
            $this->authorize('view', Dossier::findOrFail($data['dossier_id']));
        }

        $annee    = now()->year;
        $count    = Courrier::whereYear('created_at', $annee)->count() + 1;
        $reference = 'COU-' . $annee . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        Courrier::create(array_merge($data, [
            'reference'    => $reference,
            'redacteur_id' => Auth::id(),
            'statut'       => 'brouillon',
        ]));

        return back()->with('success', 'Courrier créé.');
    }

    public function update(Request $request, Courrier $courrier)
    {
        $this->authorize('update', $courrier);
        abort_if($courrier->est_signe_cachete, 403, 'Courrier verrouillé : déjà signé/cacheté, non modifiable.');

        $data = $request->validate([
            'destinataire' => ['sometimes', 'string', 'max:300'],
            'adresse'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'objet'        => ['sometimes', 'string', 'max:500'],
            'type'         => ['sometimes', 'in:transmission,convocation,relance,divers'],
            'contenu'      => ['sometimes', 'nullable', 'string'],
            // `envoye` n'est plus accepté ici (2026-11-17) : un clic suffisait à marquer un courrier
            // envoyé, daté du clic et sans personne nommée. L'envoi passe par `envoyer()`, qui exige
            // la date de transmission et le transmetteur. Seul le retour en brouillon reste ici.
            'statut'       => ['sometimes', 'in:brouillon'],
        ]);

        if (($data['statut'] ?? null) === 'brouillon') {
            $data['envoye_at']    = null;
            $data['transmis_par'] = null;
        }

        $courrier->update($data);

        return back()->with('success', 'Courrier mis à jour.');
    }

    /**
     * Enregistre la transmission d'un courrier : **date** et **personne qui transmet**, toutes deux
     * exigées (demande de l'étude, 2026-11-17).
     *
     * La date est bornée par Courrier::bornesDateEnvoi(), les mêmes bornes que l'écran. Un courrier
     * signé/cacheté peut être transmis : l'envoyer ne modifie pas son contenu — c'est même l'ordre
     * normal, la lettre est signée avant de partir.
     */
    public function envoyer(Request $request, Courrier $courrier)
    {
        $this->authorize('update', $courrier);
        abort_if($courrier->estEnvoye(), 422, 'Ce courrier est déjà marqué envoyé.');

        $bornes = $courrier->bornesDateEnvoi();

        $data = $request->validate([
            'envoye_le'    => ['required', 'date', 'after_or_equal:' . $bornes['min'], 'before_or_equal:' . $bornes['max']],
            'transmis_par' => ['required', 'string', 'max:150'],
        ], [
            'envoye_le.required'        => 'La date de transmission est obligatoire.',
            'envoye_le.after_or_equal'  => 'Un courrier ne peut pas être transmis avant sa création ('
                . \Carbon\Carbon::parse($bornes['min'])->format('d/m/Y') . ').',
            'envoye_le.before_or_equal' => 'La date de transmission ne peut pas être dans le futur.',
            'transmis_par.required'     => 'Indiquez la personne qui transmet le courrier.',
        ]);

        $courrier->update([
            'statut'       => 'envoye',
            'envoye_at'    => \Carbon\Carbon::parse($data['envoye_le'])->startOfDay(),
            'transmis_par' => trim($data['transmis_par']),
        ]);

        if ($courrier->dossier) {
            JournalActivite::enregistrer(
                $courrier->dossier,
                "Courrier « {$courrier->objet} » transmis le {$courrier->envoye_at->format('d/m/Y')} par {$courrier->transmis_par}",
                'expedition',
                ['courrier_id' => $courrier->id],
            );
        }

        return back()->with('success', "« {$courrier->objet} » marqué envoyé.");
    }

    public function destroy(Courrier $courrier)
    {
        $this->authorize('delete', $courrier);
        abort_if($courrier->est_signe_cachete, 403, 'Courrier verrouillé : déjà signé/cacheté, non modifiable.');

        $courrier->delete();

        return back()->with('success', 'Courrier supprimé.');
    }

    public function genererDepuisModele(Request $request, Dossier $dossier, ActesGeneratorService $generatorService)
    {
        $this->authorize('genererCourriers', $dossier);

        $data   = $request->validate(['modele_courrier_id' => ['required', 'integer', 'exists:modeles_courriers,id']]);
        $modele = ModeleCourrier::actif()->findOrFail($data['modele_courrier_id']);

        $dernier = Courrier::where('dossier_id', $dossier->id)
            ->where('objet', $modele->nom)
            ->latest('id')
            ->first();
        // Un courrier se génère une fois (retour R10, 2026-11-16) : chaque appel en créait un de plus,
        // sans versionnage, et la liste du dossier se remplissait de doublons. Pour le refaire, on
        // supprime le précédent — geste explicite, soumis à CourrierPolicy::delete.
        abort_if(
            $dernier !== null,
            422,
            "« {$modele->nom} » a déjà été généré pour ce dossier. Supprimez-le depuis l'écran Courriers pour le refaire.",
        );

        $dossier->load('questionnaire');

        $chemin = $generatorService->genererDocument($dossier, $modele->chemin_fichier, Str::slug($modele->nom));

        $annee     = now()->year;
        $count     = Courrier::whereYear('created_at', $annee)->count() + 1;
        $reference = 'COU-' . $annee . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        Courrier::create([
            'reference'      => $reference,
            'dossier_id'     => $dossier->id,
            'redacteur_id'   => Auth::id(),
            'destinataire'   => '',
            'objet'          => $modele->nom,
            'type'           => 'transmission',
            'statut'         => 'brouillon',
            'chemin_fichier' => $chemin,
        ]);

        JournalActivite::enregistrer($dossier, "Courrier « {$modele->nom} » généré", 'expedition', []);

        return back()->with('success', "« {$modele->nom} » généré avec succès.");
    }

    public function download(Courrier $courrier)
    {
        $this->authorize('view', $courrier);

        if (!$courrier->chemin_fichier || !Storage::disk('public')->exists($courrier->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $ext      = pathinfo($courrier->chemin_fichier, PATHINFO_EXTENSION);
        $filename = $courrier->objet . ($ext ? '.' . $ext : '');

        return Storage::disk('public')->download($courrier->chemin_fichier, $filename);
    }

    /**
     * Dépôt de la version finale signée/cachetée d'un courrier obligatoire — même
     * principe que DocumentController::televerserSigne(), mais Courrier n'a pas
     * d'historique de versions (différence assumée avec les documents, voir plan
     * GED/Clôture) : le fichier précédent est simplement remplacé, pas conservé.
     */
    public function televerserSigne(Request $request, Courrier $courrier)
    {
        $dossier = $courrier->dossier;
        $this->authorize('cloturerDocuments', $dossier);
        abort_if($courrier->est_signe_cachete, 403, 'Courrier déjà signé/cacheté — verrouillé, non modifiable.');

        $request->validate([
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,docx'],
        ]);

        if ($courrier->chemin_fichier) {
            Storage::disk('public')->delete($courrier->chemin_fichier);
        }

        $chemin = $request->file('fichier')->storeAs(
            'documents/' . $dossier->reference,
            Str::slug($courrier->objet) . '_signe.' . $request->file('fichier')->extension(),
            'public'
        );

        $courrier->update([
            // Le dépôt de la version signée ne vaut plus envoi (2026-11-17) : il marquait le courrier
            // « envoyé » à la date du dépôt, sans transmetteur — exactement ce que l'étude refuse.
            // La lettre est signée *avant* de partir ; sa transmission s'enregistre par `envoyer()`.
            'chemin_fichier'       => $chemin,
            'est_signe_cachete'    => true,
            'signe_cachete_at'     => now(),
            'signe_cachete_par_id' => Auth::id(),
        ]);

        JournalActivite::enregistrer(
            $dossier,
            "Courrier « {$courrier->objet} » déposé signé/cacheté (verrouillé)",
            'expedition',
            []
        );

        return back()->with('success', "« {$courrier->objet} » enregistré comme signé/cacheté — verrouillé.");
    }

    public function preview(Courrier $courrier)
    {
        $this->authorize('view', $courrier);

        if (!$courrier->chemin_fichier || !Storage::disk('public')->exists($courrier->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $path     = Storage::disk('public')->path($courrier->chemin_fichier);
        $mime     = mime_content_type($path) ?: 'application/octet-stream';
        $filename = basename($courrier->chemin_fichier);

        return response()->file($path, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
