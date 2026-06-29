<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Dossier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function store(Request $request, Dossier $dossier)
    {
        $data = $request->validate([
            'nom'                      => ['required', 'string', 'max:200'],
            'type_document'            => ['required', 'in:acte_principal,annexe,procedure,lettre,recepisse'],
            'version'                  => ['nullable', 'string', 'max:10'],
            'signature_client_requise' => ['boolean'],
            'fichier'                  => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt'],
        ]);

        $cheminFichier = null;
        if ($request->hasFile('fichier')) {
            $path = $request->file('fichier')->storeAs(
                'documents/' . $dossier->reference,
                Str::slug($data['nom']) . '_v' . ($data['version'] ?? '1') . '.' . $request->file('fichier')->extension(),
                'public'
            );
            $cheminFichier = $path;
        }

        $dossier->documents()->create([
            'nom'                      => $data['nom'],
            'type_document'            => $data['type_document'],
            'version'                  => $data['version'] ?? '1.0',
            'statut'                   => 'a_editer',
            'signature_client_requise' => $data['signature_client_requise'] ?? true,
            'chemin_fichier'           => $cheminFichier,
        ]);

        return back()->with('success', 'Document ajouté.');
    }

    public function update(Request $request, Document $document)
    {
        $data = $request->validate([
            'statut'        => ['sometimes', 'in:a_editer,edite,signe_client,signe_notaire'],
            'nom'           => ['sometimes', 'string', 'max:200'],
            'version'       => ['sometimes', 'string', 'max:10'],
            'fichier'       => ['sometimes', 'nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt'],
        ]);

        if ($request->hasFile('fichier')) {
            if ($document->chemin_fichier) {
                Storage::disk('public')->delete($document->chemin_fichier);
            }
            $dossierRef = $document->dossier->reference;
            $path = $request->file('fichier')->storeAs(
                'documents/' . $dossierRef,
                Str::slug($document->nom) . '_v' . ($data['version'] ?? $document->version) . '.' . $request->file('fichier')->extension(),
                'public'
            );
            $data['chemin_fichier'] = $path;
        }

        if (isset($data['statut'])) {
            $now = now();
            match ($data['statut']) {
                'edite'          => $data += ['edite_at' => $now, 'edite_par' => auth()->id()],
                'signe_client'   => $data += ['signe_client_at' => $now],
                'signe_notaire'  => $data += ['signe_notaire_at' => $now],
                default          => null,
            };
        }

        $document->update($data);

        return back()->with('success', 'Document mis à jour.');
    }

    public function destroy(Document $document)
    {
        if ($document->chemin_fichier) {
            Storage::disk('public')->delete($document->chemin_fichier);
        }

        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }

    public function download(Document $document)
    {
        if (!$document->chemin_fichier || !Storage::disk('public')->exists($document->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        return Storage::disk('public')->download($document->chemin_fichier, $document->nom);
    }

    public function preview(Document $document)
    {
        if (!$document->chemin_fichier || !Storage::disk('public')->exists($document->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $path     = Storage::disk('public')->path($document->chemin_fichier);
        $mime     = mime_content_type($path) ?: 'application/octet-stream';
        $filename = basename($document->chemin_fichier);

        return response()->file($path, [
            'Content-Type'        => $mime,
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }
}
