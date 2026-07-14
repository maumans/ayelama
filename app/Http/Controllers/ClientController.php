<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /**
     * Recherche de clients pour l'auto-complétion (utilisé par le sélecteur de client
     * pendant la création d'un dossier). Réponse JSON brute, pas une page Inertia.
     */
    public function autocomplete(Request $request)
    {
        $q = trim((string) $request->get('q', ''));

        if (mb_strlen($q) < 2) {
            // Aucune recherche saisie : proposer les clients les plus récents
            // plutôt que de renvoyer une liste vide, pour permettre de parcourir
            // le répertoire sans avoir à connaître déjà le nom recherché.
            $clients = Client::orderByDesc('created_at')->limit(20)->get();

            return response()->json($clients);
        }

        $clients = Client::query()
            ->where(function ($query) use ($q) {
                $query->where('prenom_nom', 'like', "%{$q}%")
                    ->orWhere('denomination', 'like', "%{$q}%")
                    ->orWhere('piece_numero', 'like', "%{$q}%")
                    ->orWhere('rccm', 'like', "%{$q}%")
                    ->orWhere('telephone', 'like', "%{$q}%");
            })
            ->orderBy('prenom_nom')
            ->limit(15)
            ->get();

        return response()->json($clients);
    }

    /**
     * Création rapide d'un client depuis le questionnaire de création de dossier.
     * Réponse JSON (le client créé) pour insertion immédiate côté client, sans
     * naviguer hors de l'assistant.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'                    => ['required', 'in:physique,morale'],
            'civilite'                => ['nullable', 'string', 'max:10'],
            'prenom_nom'              => ['required_if:type,physique', 'nullable', 'string', 'max:200'],
            'ne_a'                    => ['nullable', 'string', 'max:100'],
            'date_naissance'          => ['nullable', 'date'],
            'nationalite'             => ['nullable', 'string', 'max:100'],
            'piece_type'              => ['nullable', 'string', 'max:100'],
            'piece_numero'            => ['nullable', 'string', 'max:100'],
            'piece_delivree_le'       => ['nullable', 'date'],
            'piece_delivree_a'        => ['nullable', 'string', 'max:100'],
            'piece_expire_le'         => ['nullable', 'date'],
            'situation_matrimoniale'  => ['nullable', 'string', 'max:50'],
            'regime_matrimonial'      => ['nullable', 'string', 'max:100'],
            'denomination'            => ['required_if:type,morale', 'nullable', 'string', 'max:200'],
            'forme'                   => ['nullable', 'string', 'max:50'],
            'rccm'                    => ['nullable', 'string', 'max:100'],
            'representant_legal'      => ['nullable', 'string', 'max:200'],
            'demeurant_ville'         => ['nullable', 'string', 'max:100'],
            'quartier'                => ['nullable', 'string', 'max:100'],
            'commune'                 => ['nullable', 'string', 'max:100'],
            'pays'                    => ['nullable', 'string', 'max:100'],
            'telephone'               => ['nullable', 'string', 'max:25'],
            'email'                   => ['nullable', 'email', 'max:150'],
            'siege'                   => ['nullable', 'string', 'max:200'],
        ]);

        $client = Client::create($data);

        return response()->json($client, 201);
    }
}
