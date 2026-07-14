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

        $partie->delete();

        return back()->with('success', 'Personne retirée du dossier.');
    }
}
