<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Dossier;
use App\Models\JournalActivite;
use App\Models\ModeleActe;
use App\Models\RevisionPoint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentController extends Controller
{
    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('genererDocuments', $dossier);

        $data = $request->validate([
            'nom'          => ['required', 'string', 'max:200'],
            'type_document'=> ['required', 'in:acte_principal,annexe,procedure,lettre,recepisse'],
            'version'      => ['nullable', 'string', 'max:10'],
            'fichier'      => ['nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt,xlsx,xls'],
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
            'nom'           => $data['nom'],
            'type_document' => $data['type_document'],
            'version'       => $data['version'] ?? '1.0',
            'statut'        => 'a_editer',
            'chemin_fichier'=> $cheminFichier,
        ]);

        return back()->with('success', 'Document ajouté.');
    }

    public function update(Request $request, Document $document)
    {
        $this->authorize('genererDocuments', $document->dossier);

        $data = $request->validate([
            'statut'        => ['sometimes', 'in:a_editer,edite'],
            'nom'           => ['sometimes', 'string', 'max:200'],
            'version'       => ['sometimes', 'string', 'max:10'],
            'fichier'       => ['sometimes', 'nullable', 'file', 'max:20480', 'mimes:pdf,doc,docx,odt,xlsx,xls'],
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

        if (isset($data['statut']) && $data['statut'] === 'edite') {
            $data += ['edite_at' => now(), 'edite_par' => auth()->id()];
        }

        $document->update($data);

        return back()->with('success', 'Document mis à jour.');
    }

    public function regenerer(Document $document, \App\Services\ActesGeneratorService $generatorService)
    {
        $dossier = $document->dossier;
        $this->authorize('genererDocuments', $dossier);

        $modele = ModeleActe::where('type_acte_id', $dossier->type_acte_id)
            ->where('nom', $document->nom)
            ->where('est_actif', true)
            ->first();

        if (! $modele) {
            return back()->with('error', "Aucun modèle actif trouvé pour « {$document->nom} ».");
        }

        $dossier->load('questionnaire');

        // Supprime l'ancien fichier généré
        if ($document->chemin_fichier) {
            Storage::disk('public')->delete($document->chemin_fichier);
        }

        // Le contenu change : la vérification déjà enregistrée pour ce document n'est
        // plus fiable, on force une nouvelle évaluation en révision.
        RevisionPoint::where('point_id', (string) $document->id)
            ->whereHas('revision', fn ($q) => $q->where('dossier_id', $dossier->id))
            ->delete();

        $chemin = $generatorService->genererDocument(
            $dossier,
            $modele->chemin_fichier,
            Str::slug($document->nom)
        );

        $document->update([
            'chemin_fichier' => $chemin,
            'statut'         => 'a_editer',
        ]);

        JournalActivite::enregistrer(
            $dossier,
            "Document « {$document->nom} » régénéré depuis le modèle",
            'etape',
            []
        );

        return back()->with('success', "« {$document->nom} » régénéré avec succès.");
    }

    public function destroy(Document $document)
    {
        $this->authorize('genererDocuments', $document->dossier);

        if ($document->chemin_fichier) {
            Storage::disk('public')->delete($document->chemin_fichier);
        }

        RevisionPoint::where('point_id', (string) $document->id)
            ->whereHas('revision', fn ($q) => $q->where('dossier_id', $document->dossier_id))
            ->delete();

        $document->delete();

        return back()->with('success', 'Document supprimé.');
    }

    public function download(Document $document)
    {
        $this->authorize('view', $document->dossier);

        if (!$document->chemin_fichier || !Storage::disk('public')->exists($document->chemin_fichier)) {
            abort(404, 'Fichier introuvable.');
        }

        $ext      = pathinfo($document->chemin_fichier, PATHINFO_EXTENSION);
        $filename = $document->nom . ($ext ? '.' . $ext : '');

        return Storage::disk('public')->download($document->chemin_fichier, $filename);
    }

    public function preview(Document $document)
    {
        $this->authorize('view', $document->dossier);

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
