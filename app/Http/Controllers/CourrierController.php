<?php

namespace App\Http\Controllers;

use App\Models\Courrier;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
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
        $query = Courrier::with(['dossier:id,reference,objet', 'redacteur:id,name,initiales'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($q2) =>
                $q2->where('reference',    'like', "%{$s}%")
                   ->orWhere('objet',       'like', "%{$s}%")
                   ->orWhere('destinataire','like', "%{$s}%")))
            ->when($request->type,   fn ($q, $t) => $q->where('type', $t))
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->when($request->sort === 'objet',  fn ($q) => $q->orderBy('objet'))
            ->when($request->sort === 'envoye', fn ($q) => $q->orderByDesc('envoye_at'))
            ->when(!in_array($request->sort, ['objet', 'envoye']), fn ($q) => $q->orderByDesc('created_at'));

        $stats = [
            'total'      => Courrier::count(),
            'brouillons' => Courrier::brouillon()->count(),
            'envoyes'    => Courrier::envoye()->count(),
            'ceMois'     => Courrier::whereMonth('created_at', now()->month)
                                ->whereYear('created_at', now()->year)->count(),
            'parType'    => Courrier::selectRaw('type, count(*) as n')
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
            'redacteur'    => $c->redacteur?->name,
            'initiales'    => $c->redacteur?->initiales,
            'dossier_id'   => $c->dossier_id,
            'dossierRef'   => $c->dossier?->reference,
            'dossierObjet' => $c->dossier?->objet,
            'created_at'   => $c->created_at->format('d/m/Y'),
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

        $data = $request->validate([
            'destinataire' => ['sometimes', 'string', 'max:300'],
            'adresse'      => ['sometimes', 'nullable', 'string', 'max:500'],
            'objet'        => ['sometimes', 'string', 'max:500'],
            'type'         => ['sometimes', 'in:transmission,convocation,relance,divers'],
            'contenu'      => ['sometimes', 'nullable', 'string'],
            'statut'       => ['sometimes', 'in:brouillon,envoye'],
        ]);

        if (isset($data['statut'])) {
            if ($data['statut'] === 'envoye' && $courrier->statut !== 'envoye') {
                $data['envoye_at'] = now();
            } elseif ($data['statut'] === 'brouillon') {
                $data['envoye_at'] = null;
            }
        }

        $courrier->update($data);

        return back()->with('success', 'Courrier mis à jour.');
    }

    public function destroy(Courrier $courrier)
    {
        $this->authorize('delete', $courrier);

        $courrier->delete();

        return back()->with('success', 'Courrier supprimé.');
    }

    public function genererDepuisModele(Request $request, Dossier $dossier, ActesGeneratorService $generatorService)
    {
        $this->authorize('genererCourriers', $dossier);

        $data   = $request->validate(['modele_acte_id' => ['required', 'integer', 'exists:modeles_actes,id']]);
        $modele = ModeleActe::actif()->findOrFail($data['modele_acte_id']);

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
