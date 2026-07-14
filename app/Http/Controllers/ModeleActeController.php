<?php

namespace App\Http\Controllers;

use App\Enums\CategorieActe;
use App\Models\ModeleActe;
use App\Models\ModeleCourrier;
use App\Models\TypeActe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ModeleActeController extends Controller
{
    public function index(Request $request)
    {
        $modeles = ModeleActe::with('typeActe')
            ->when($request->q, fn ($q, $s) => $q->where('nom', 'like', "%{$s}%")
                ->orWhereHas('typeActe', fn ($q2) => $q2->where('label', 'like', "%{$s}%")))
            ->when($request->categorie, fn ($q, $cat) => $q->whereHas('typeActe', fn ($q2) => $q2->where('categorie', $cat)))
            ->when($request->type_document, fn ($q, $td) => $q->where('type_document', $td))
            ->when($request->statut === 'actif',   fn ($q) => $q->where('est_actif', true))
            ->when($request->statut === 'inactif', fn ($q) => $q->where('est_actif', false))
            ->when($request->sort === 'date', fn ($q) => $q->orderByDesc('updated_at'))
            ->when($request->sort !== 'date', fn ($q) => $q->orderBy('nom'))
            ->get()
            ->map(fn ($m) => [
                'id'             => $m->id,
                'nom'            => $m->nom,
                'type_document'  => $m->type_document,
                'typeDocLabel'   => $m->typeDocumentLabel(),
                'chemin_fichier' => $m->chemin_fichier,
                'version'        => $m->version,
                'est_actif'      => $m->est_actif,
                'type_acte_id'   => $m->type_acte_id,
                'typeActeLabel'  => $m->typeActe?->label,
                'categorie'      => $m->typeActe?->categorie?->value,
                'categorieLabel' => $m->typeActe?->categorie?->label(),
                'updated_at'     => $m->updated_at?->format('d/m/Y'),
            ]);

        $total     = ModeleActe::count();
        $actifs    = ModeleActe::where('est_actif', true)->count();

        $parTypeDoc = ModeleActe::selectRaw('type_document, count(*) as total')
            ->groupBy('type_document')
            ->pluck('total', 'type_document')
            ->toArray();

        $parCategorie = ModeleActe::with('typeActe')
            ->get()
            ->groupBy(fn ($m) => $m->typeActe?->categorie?->label() ?? 'Autre')
            ->map->count()
            ->sortDesc()
            ->take(5)
            ->toArray();

        $modelesCourriers = ModeleCourrier::with('typesActes')
            ->orderBy('nom')
            ->get()
            ->map(fn (ModeleCourrier $m) => [
                'id'              => $m->id,
                'nom'             => $m->nom,
                'type_document'   => $m->type_document,
                'typeDocLabel'    => $m->typeDocumentLabel(),
                'chemin_fichier'  => $m->chemin_fichier,
                'version'         => $m->version,
                'est_actif'       => $m->est_actif,
                'applicable_tous' => $m->applicable_tous,
                'type_acte_ids'   => $m->typesActes->pluck('id'),
                'typesActesLabels' => $m->typesActes->pluck('label'),
                'updated_at'      => $m->updated_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Modeles/Index', [
            'modeles'          => $modeles,
            'modelesCourriers' => $modelesCourriers,
            'typesActes' => TypeActe::orderBy('label')->get(['id', 'label', 'categorie']),
            'categories' => collect(CategorieActe::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->label(),
            ]),
            'filters' => [
                'q'             => $request->q             ?? '',
                'categorie'     => $request->categorie     ?? '',
                'type_document' => $request->type_document ?? '',
                'statut'        => $request->statut        ?? '',
                'sort'          => $request->sort          ?? 'nom',
            ],
            'stats'   => [
                'total'        => $total,
                'actifs'       => $actifs,
                'inactifs'     => $total - $actifs,
                'parTypeDoc'   => $parTypeDoc,
                'parCategorie' => $parCategorie,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'nom'            => ['required', 'string', 'max:200'],
            'type_acte_id'   => ['required', 'exists:types_actes,id'],
            'type_document'  => ['required', 'in:acte_principal,page_garde,attestation,declaration,dnsv,insertion,rccm,note_frais,bordereau,annexe,procedure,lettre,recepisse'],
            'version'        => ['required', 'string', 'max:10'],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['required', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles', $filename, 'local');
            $data['chemin_fichier'] = $filename;
            unset($data['fichier']);
        }

        ModeleActe::create(array_merge($data, [
            'est_actif'  => true,
            'updated_by' => Auth::id(),
        ]));

        return back()->with('success', 'Modèle créé avec succès.');
    }

    public function update(Request $request, ModeleActe $modele)
    {
        $rules = [
            'nom'            => ['sometimes', 'string', 'max:200'],
            'type_acte_id'   => ['sometimes', 'exists:types_actes,id'],
            'type_document'  => ['sometimes', 'in:acte_principal,page_garde,attestation,declaration,dnsv,insertion,rccm,note_frais,bordereau,annexe,procedure,lettre,recepisse'],
            'version'        => ['sometimes', 'string', 'max:10'],
            'est_actif'      => ['sometimes', 'boolean'],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['sometimes', 'string', 'max:500'];
        }

        $data = $request->validate($rules);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles', $filename, 'local');
            $data['chemin_fichier'] = $filename;
            unset($data['fichier']);

            if ($modele->chemin_fichier && \Illuminate\Support\Facades\Storage::disk('local')->exists('modeles/' . $modele->chemin_fichier)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete('modeles/' . $modele->chemin_fichier);
            }
        }

        $modele->update(array_merge($data, ['updated_by' => Auth::id()]));

        return back()->with('success', 'Modèle mis à jour.');
    }

    public function dupliquer(ModeleActe $modele)
    {
        ModeleActe::create([
            'nom'            => 'Copie de ' . $modele->nom,
            'type_acte_id'   => $modele->type_acte_id,
            'type_document'  => $modele->type_document,
            'chemin_fichier' => $modele->chemin_fichier,
            'version'        => '1.0',
            'est_actif'      => false,
            'updated_by'     => Auth::id(),
        ]);

        return back()->with('success', 'Modèle dupliqué — pensez à le renommer.');
    }

    public function destroy(ModeleActe $modele)
    {
        $modele->delete();

        return back()->with('success', 'Modèle supprimé.');
    }
}
