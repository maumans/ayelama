<?php

namespace App\Http\Controllers;

use App\Models\Demande;
use App\Models\User;
use App\Notifications\NouvelleDemandeNotification;
use App\Services\MistralOcrService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class IntakeController extends Controller
{
    public function show(string $token)
    {
        $demande = Demande::where('token', $token)->with('typeActe')->first();

        return Inertia::render('Intake/Show', [
            'etat'    => $this->etat($demande),
            'demande' => $demande ? [
                'typeActe'   => [
                    'code'  => $demande->typeActe->code,
                    'label' => $demande->typeActe->label,
                ],
                'clientRole' => $demande->client_role,
            ] : null,
            'token' => $token,
        ]);
    }

    public function ocr(Request $request, string $token, MistralOcrService $ocr)
    {
        $demande = Demande::where('token', $token)->firstOrFail();
        if (!$demande->estUtilisable()) {
            abort(410, 'Ce lien n\'est plus valide.');
        }

        $data = $request->validate([
            'fichier' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'champs'  => ['required', 'array', 'min:1'],
            'champs.*.id'    => ['required', 'string'],
            'champs.*.label' => ['required', 'string'],
        ]);

        $cheminTemp = $request->file('fichier')->store('intake-temp', 'local');

        $resultat = $ocr->extraire(Storage::disk('local')->path($cheminTemp), $data['champs']);

        Storage::disk('local')->delete($cheminTemp);

        return response()->json(['donnees' => $resultat]);
    }

    public function store(Request $request, string $token)
    {
        $demande = Demande::where('token', $token)->firstOrFail();
        if (!$demande->estUtilisable()) {
            abort(410, 'Ce lien n\'est plus valide.');
        }

        $data = $request->validate([
            'objet'          => ['nullable', 'string', 'max:500'],
            'donnees'        => ['nullable', 'array'],
            'parties'        => ['nullable', 'array'],
            'parties.*.nom'  => ['required_with:parties', 'string', 'max:200'],
            'parties.*.role' => ['required_with:parties', 'string', 'max:100'],
            'parties.*.cni'       => ['nullable', 'string', 'max:50'],
            'parties.*.telephone' => ['nullable', 'string', 'max:20'],
            'parties.*.adresse'   => ['nullable', 'string', 'max:500'],
            'parties.*.email'     => ['nullable', 'email', 'max:200'],
            'source'         => ['required', 'in:manuel,ocr'],
            'fichier'        => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $cheminScan = null;
        if ($request->hasFile('fichier')) {
            $cheminScan = $request->file('fichier')->store('demandes/' . $demande->token, 'local');
        }

        $demande->update([
            'objet'       => $data['objet'] ?? null,
            'donnees'     => $data['donnees'] ?? [],
            'parties'     => $data['parties'] ?? [],
            'source'      => $data['source'],
            'fichier_scan' => $cheminScan,
            'statut'      => 'soumise',
            'soumise_at'  => now(),
        ]);

        $destinataires = User::where('actif', true)
            ->where(fn ($q) => $q->withRole('notaire')->orWhere('id', $demande->cree_par_id))
            ->get();
        foreach ($destinataires as $user) {
            $user->notify(new NouvelleDemandeNotification($demande));
        }

        return Inertia::render('Intake/Show', [
            'etat'    => 'soumise',
            'demande' => null,
            'token'   => $token,
        ]);
    }

    private function etat(?Demande $demande): string
    {
        if (!$demande) return 'invalide';
        if ($demande->statut === 'soumise' || $demande->statut === 'traitee') return 'soumise';
        if ($demande->estExpiree()) return 'expiree';
        return 'active';
    }
}
