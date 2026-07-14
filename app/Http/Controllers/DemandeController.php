<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDossierRequest;
use App\Mail\DemandeLienMail;
use App\Models\Demande;
use App\Models\TypeActe;
use App\Models\User;
use App\Services\ActesGeneratorService;
use App\Services\FacturationService;
use App\Services\FormaliteGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class DemandeController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Demande::class);

        $demandes = Demande::with(['typeActe', 'creePar:id,name', 'dossier:id,reference'])
            ->when($request->statut, fn ($q, $s) => $q->where('statut', $s))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Demande $d) => [
                'id'            => $d->id,
                'token'         => $d->token,
                'typeActeLabel' => $d->typeActe?->label,
                'clientRole'    => $d->client_role,
                'statut'        => $d->statut,
                'expire_at'     => $d->expire_at->format('d/m/Y H:i'),
                'estExpiree'    => $d->estExpiree(),
                'creePar'       => $d->creePar?->name,
                'dossierRef'    => $d->dossier?->reference,
                'created_at'    => $d->created_at->format('d/m/Y'),
                'url'           => route('intake.show', $d->token),
            ]);

        return Inertia::render('Demandes/Index', [
            'demandes'   => $demandes,
            'typesActes' => TypeActe::actif()->get()->groupBy(fn ($t) => $t->categorie->value)->map(fn ($group) => $group->map(fn ($t) => [
                'id'    => $t->id,
                'code'  => $t->code,
                'label' => $t->label,
            ])),
            'filters' => ['statut' => $request->statut ?? ''],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Demande::class);

        $data = $request->validate([
            'type_acte_id' => ['required', 'integer', 'exists:types_actes,id'],
            'client_role'  => ['nullable', 'string', 'max:100'],
            'email'        => ['nullable', 'email', 'max:200'],
        ]);

        $demande = Demande::create([
            'token'        => Str::random(48),
            'type_acte_id' => $data['type_acte_id'],
            'client_role'  => $data['client_role'] ?? null,
            'expire_at'    => now()->addDays(7),
            'cree_par_id'  => Auth::id(),
        ]);

        if (!empty($data['email'])) {
            Mail::to($data['email'])->queue(new DemandeLienMail($demande));
        }

        return back()->with('success', 'Lien de demande généré avec succès.');
    }

    public function show(Demande $demande)
    {
        $this->authorize('view', $demande);

        $demande->load(['typeActe', 'creePar:id,name', 'traiteePar:id,name', 'dossier:id,reference']);

        return Inertia::render('Demandes/Show', [
            'demande' => [
                'id'             => $demande->id,
                'token'          => $demande->token,
                'typeActe'       => [
                    'id'    => $demande->typeActe->id,
                    'code'  => $demande->typeActe->code,
                    'label' => $demande->typeActe->label,
                ],
                'clientRole'     => $demande->client_role,
                'statut'         => $demande->statut,
                'expire_at'      => $demande->expire_at->format('d/m/Y H:i'),
                'estExpiree'     => $demande->estExpiree(),
                'objet'          => $demande->objet,
                'donnees'        => $demande->donnees,
                'parties'        => $demande->parties,
                'source'         => $demande->source,
                'has_scan'       => (bool) $demande->fichier_scan,
                'url_scan'       => $demande->fichier_scan ? route('demandes.scan', $demande) : null,
                'soumise_at'     => $demande->soumise_at?->format('d/m/Y H:i'),
                'creePar'        => $demande->creePar?->name,
                'traiteePar'     => $demande->traiteePar?->name,
                'dossierRef'     => $demande->dossier?->reference,
                'url'            => route('intake.show', $demande->token),
            ],
            'notaires'    => User::withRole('notaire')->where('actif', true)->get(['id', 'name']),
            'reviseurs'   => User::withRole('reviseur')->where('actif', true)->get(['id', 'name']),
            'formalistes' => User::withRole('formaliste')->where('actif', true)->get(['id', 'name']),
        ]);
    }

    public function scan(Demande $demande)
    {
        $this->authorize('view', $demande);

        if (!$demande->fichier_scan || !Storage::disk('local')->exists($demande->fichier_scan)) {
            abort(404, 'Fichier introuvable.');
        }

        $path = Storage::disk('local')->path($demande->fichier_scan);
        $mime = mime_content_type($path) ?: 'application/octet-stream';

        return response()->file($path, ['Content-Type' => $mime]);
    }

    public function convertir(
        Request $request,
        Demande $demande,
        ActesGeneratorService $generatorService,
        FacturationService $facturationService,
        FormaliteGenerationService $formaliteGenerationService,
        DossierController $dossierController
    ) {
        $this->authorize('update', $demande);

        if ($demande->statut === 'traitee') {
            return back()->with('error', 'Cette demande a déjà été convertie en dossier.');
        }

        $validated = $request->validate([
            'objet'         => ['required', 'string', 'min:10', 'max:500'],
            'notaire_id'    => ['required', 'integer', 'exists:users,id'],
            'reviseur_id'   => ['nullable', 'integer', 'exists:users,id'],
            'formaliste_id' => ['nullable', 'integer', 'exists:users,id'],
            'urgent'        => ['boolean'],
            'notes'         => ['nullable', 'string', 'max:2000'],
            'donnees'       => ['nullable', 'array'],
            ...StoreDossierRequest::partiesRules(),
        ]);

        $dossier = $dossierController->creerDossier([
            'type_acte_id'  => $demande->type_acte_id,
            'objet'         => $validated['objet'],
            'notaire_id'    => $validated['notaire_id'],
            'reviseur_id'   => $validated['reviseur_id'] ?? null,
            'formaliste_id' => $validated['formaliste_id'] ?? null,
            'urgent'        => $validated['urgent'] ?? false,
            'notes'         => $validated['notes'] ?? null,
            'donnees'       => $validated['donnees'] ?? $demande->donnees ?? [],
            'parties'       => $validated['parties'] ?? $demande->parties ?? [],
        ], $generatorService, $facturationService, $formaliteGenerationService);

        $demande->update([
            'statut'         => 'traitee',
            'traitee_par_id' => Auth::id(),
            'traitee_at'     => now(),
            'dossier_id'     => $dossier->id,
        ]);

        return redirect()->route('dossiers.show', $dossier->reference)
            ->with('success', "Dossier {$dossier->reference} créé à partir de la demande.");
    }

    public function destroy(Demande $demande)
    {
        $this->authorize('update', $demande);

        $demande->delete();

        return back()->with('success', 'Lien de demande révoqué.');
    }
}
