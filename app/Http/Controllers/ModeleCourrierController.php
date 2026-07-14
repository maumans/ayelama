<?php

namespace App\Http\Controllers;

use App\Models\ModeleCourrier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ModeleCourrierController extends Controller
{
    public function store(Request $request)
    {
        $rules = [
            'nom'             => ['required', 'string', 'max:200'],
            'type_document'   => ['required', 'in:acte_principal,page_garde,attestation,declaration,dnsv,insertion,rccm,note_frais,bordereau,annexe,procedure,lettre,recepisse'],
            'version'         => ['required', 'string', 'max:10'],
            'applicable_tous' => ['required', 'boolean'],
            'type_acte_ids'   => ['required_if:applicable_tous,false', 'array'],
            'type_acte_ids.*' => ['exists:types_actes,id'],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['required', 'string', 'max:500'];
        }

        $data = $request->validate($rules);
        $typeActeIds = $data['type_acte_ids'] ?? [];
        unset($data['type_acte_ids']);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles/courrier', $filename, 'local');
            $data['chemin_fichier'] = 'modeles/courrier/' . $filename;
            unset($data['fichier']);
        }

        $modele = ModeleCourrier::create(array_merge($data, [
            'est_actif'  => true,
            'updated_by' => Auth::id(),
        ]));

        $modele->typesActes()->sync($typeActeIds);

        return back()->with('success', 'Modèle de courrier créé avec succès.');
    }

    public function update(Request $request, ModeleCourrier $modeleCourrier)
    {
        $rules = [
            'nom'             => ['sometimes', 'string', 'max:200'],
            'type_document'   => ['sometimes', 'in:acte_principal,page_garde,attestation,declaration,dnsv,insertion,rccm,note_frais,bordereau,annexe,procedure,lettre,recepisse'],
            'version'         => ['sometimes', 'string', 'max:10'],
            'est_actif'       => ['sometimes', 'boolean'],
            'applicable_tous' => ['sometimes', 'boolean'],
            'type_acte_ids'   => ['sometimes', 'array'],
            'type_acte_ids.*' => ['exists:types_actes,id'],
        ];

        if ($request->hasFile('fichier')) {
            $rules['fichier'] = ['required', 'file', 'mimes:docx', 'max:20480'];
        } else {
            $rules['chemin_fichier'] = ['sometimes', 'string', 'max:500'];
        }

        $data = $request->validate($rules);
        $typeActeIds = $data['type_acte_ids'] ?? null;
        unset($data['type_acte_ids']);

        if ($request->hasFile('fichier')) {
            $file     = $request->file('fichier');
            $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.docx';
            $file->storeAs('modeles/courrier', $filename, 'local');
            $data['chemin_fichier'] = 'modeles/courrier/' . $filename;
            unset($data['fichier']);

            if ($modeleCourrier->chemin_fichier && \Illuminate\Support\Facades\Storage::disk('local')->exists($modeleCourrier->chemin_fichier)) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($modeleCourrier->chemin_fichier);
            }
        }

        $modeleCourrier->update(array_merge($data, ['updated_by' => Auth::id()]));

        if ($typeActeIds !== null) {
            $modeleCourrier->typesActes()->sync($typeActeIds);
        }

        return back()->with('success', 'Modèle de courrier mis à jour.');
    }

    public function dupliquer(ModeleCourrier $modeleCourrier)
    {
        $copie = ModeleCourrier::create([
            'nom'             => 'Copie de ' . $modeleCourrier->nom,
            'type_document'   => $modeleCourrier->type_document,
            'chemin_fichier'  => $modeleCourrier->chemin_fichier,
            'version'         => '1.0',
            'est_actif'       => false,
            'applicable_tous' => $modeleCourrier->applicable_tous,
            'updated_by'      => Auth::id(),
        ]);

        $copie->typesActes()->sync($modeleCourrier->typesActes()->pluck('types_actes.id'));

        return back()->with('success', 'Modèle de courrier dupliqué — pensez à le renommer.');
    }

    public function destroy(ModeleCourrier $modeleCourrier)
    {
        $modeleCourrier->delete();

        return back()->with('success', 'Modèle de courrier supprimé.');
    }
}
