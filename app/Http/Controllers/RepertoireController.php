<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Partie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepertoireController extends Controller
{
    public function index(Request $request)
    {
        $query = Partie::with(['dossier.typeActe', 'client'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->where('nom',       'like', "%{$s}%")
                   ->orWhere('cni',       'like', "%{$s}%")
                   ->orWhere('telephone', 'like', "%{$s}%")
                   ->orWhere('email',     'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->client_id, fn ($q, $id) => $q->where('client_id', $id))
            ->when($request->sort === 'recent', fn ($q) => $q->orderByDesc('created_at'))
            ->when($request->sort === 'role',   fn ($q) => $q->orderBy('role')->orderBy('nom'))
            ->when(!in_array($request->sort, ['recent', 'role']), fn ($q) => $q->orderBy('nom'));

        $stats = [
            'total'            => Partie::count(),
            'dossiersCouverts' => Partie::whereNotNull('dossier_id')->distinct()->count('dossier_id'),
            'rolesDisctincts'  => Partie::distinct()->count('role'),
            'clientsUniques'   => Partie::whereNotNull('client_id')->distinct()->count('client_id'),
            'parRole'          => Partie::selectRaw('role, count(*) as n')
                ->groupBy('role')
                ->orderByDesc('n')
                ->get()
                ->map(fn ($r) => ['role' => $r->role, 'count' => (int) $r->n])
                ->values(),
        ];

        $parties = $query->paginate(30)->withQueryString();

        $roles = Partie::distinct()->pluck('role')->filter()->sort()->values();

        return Inertia::render('Repertoire/Index', [
            'parties' => $parties->through(fn ($p) => [
                'id'        => $p->id,
                'nom'       => $p->nom,
                'initiales' => $p->initiales,
                'role'      => $p->role,
                'cni'       => $p->cni,
                'telephone' => $p->telephone,
                'email'     => $p->email,
                'adresse'   => $p->adresse,
                'client_id' => $p->client_id,
                'client'    => $p->client ? [
                    'id'   => $p->client->id,
                    'type' => $p->client->type,
                    'nom'  => $p->client->nomComplet(),
                ] : null,
                'dossier'   => $p->dossier ? [
                    'reference' => $p->dossier->reference,
                    'objet'     => $p->dossier->objet,
                    'typeActe'  => $p->dossier->typeActe?->label,
                    'etape'     => $p->dossier->etape?->label(),
                ] : null,
            ]),
            'stats'        => $stats,
            'roles'        => $roles,
            'clientFiltre' => $request->client_id ? Client::find($request->client_id)?->nomComplet() : null,
            'filters' => [
                'q'         => $request->q         ?? '',
                'role'      => $request->role      ?? '',
                'sort'      => $request->sort      ?? '',
                'client_id' => $request->client_id ?? '',
            ],
        ]);
    }

    public function autocomplete(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $parties = Partie::where('nom', 'like', "%{$q}%")
            ->limit(10)
            ->get(['id', 'nom', 'role', 'cni', 'telephone'])
            ->map(fn ($p) => [
                'id'        => $p->id,
                'nom'       => $p->nom,
                'role'      => $p->role,
                'cni'       => $p->cni,
                'telephone' => $p->telephone,
            ]);

        return response()->json($parties);
    }
}
