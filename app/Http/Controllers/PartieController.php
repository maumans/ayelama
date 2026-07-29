<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Partie;
use Illuminate\Http\Request;

class PartieController extends Controller
{
    /**
     * Ajoute une personne au dossier en dehors des rôles déclarés par le
     * questionnaire (ex. accompagnateur, témoin) — indépendant du flux de
     * sauvegarde du questionnaire pour ne pas interférer avec la
     * synchronisation par « managedRoles » de DossierController::updateQuestionnaire().
     */
    public function store(Request $request, Dossier $dossier)
    {
        $this->authorize('update', $dossier);

        $data = $request->validate([
            'nom'         => ['required', 'string', 'max:200'],
            'role'        => ['required', 'string', 'max:100'],
            'client_id'   => ['nullable', 'integer', 'exists:clients,id'],
            'cni'         => ['nullable', 'string', 'max:50'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'adresse'     => ['nullable', 'string', 'max:500'],
            'email'       => ['nullable', 'email', 'max:200'],
        ]);

        $dossier->parties()->create($data);

        return back()->with('success', 'Personne ajoutée au dossier.');
    }

    public function destroy(Partie $partie)
    {
        $this->authorize('update', $partie->dossier);

        foreach ($partie->pieces as $piece) {
            $piece->supprimerAvecFichiers();
        }

        $partie->delete();

        return back()->with('success', 'Personne retirée du dossier.');
    }

    public function uploaderPhoto(Request $request, Partie $partie)
    {
        $this->authorize('genererDocuments', $partie->dossier);

        $request->validate([
            'fichier' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $photo = $partie->pieces()->where('categorie', 'photo')->first()
            ?? $partie->pieces()->create(['nom' => 'Photo', 'categorie' => 'photo']);

        $photo->nouvelleVersion($request->file('fichier'), 'parties/' . $partie->dossier->reference);

        return back()->with('success', 'Photo mise à jour.');
    }

    public function uploaderPiece(Request $request, Partie $partie)
    {
        $this->authorize('genererDocuments', $partie->dossier);

        $data = $request->validate([
            'nom'     => ['required', 'string', 'max:200'],
            'fichier' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ]);

        $piece = $partie->pieces()->create([
            'nom'       => $data['nom'],
            'categorie' => 'piece_justificative',
        ]);

        $piece->nouvelleVersion($request->file('fichier'), 'parties/' . $partie->dossier->reference);

        return back()->with('success', 'Pièce ajoutée.');
    }
}
