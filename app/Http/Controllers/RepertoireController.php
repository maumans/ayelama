<?php

namespace App\Http\Controllers;

use App\Models\Partie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepertoireController extends Controller
{
    public function index(Request $request)
    {
        $query = Partie::with('dossier.typeActe')
            ->when($request->q, fn ($q, $s) => $q->where(fn ($qq) =>
                $qq->where('nom',       'like', "%{$s}%")
                   ->orWhere('cni',       'like', "%{$s}%")
                   ->orWhere('telephone', 'like', "%{$s}%")
                   ->orWhere('email',     'like', "%{$s}%")))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->when($request->sort === 'recent', fn ($q) => $q->orderByDesc('created_at'))
            ->when($request->sort === 'role',   fn ($q) => $q->orderBy('role')->orderBy('nom'))
            ->when(!in_array($request->sort, ['recent', 'role']), fn ($q) => $q->orderBy('nom'));

        $stats = [
            'total'            => Partie::count(),
            'dossiersCouverts' => Partie::whereNotNull('dossier_id')->distinct()->count('dossier_id'),
            'rolesDisctincts'  => Partie::distinct()->count('role'),
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
                'dossier'   => $p->dossier ? [
                    'reference' => $p->dossier->reference,
                    'objet'     => $p->dossier->objet,
                    'typeActe'  => $p->dossier->typeActe?->label,
                    'etape'     => $p->dossier->etape?->label(),
                ] : null,
            ]),
            'stats'   => $stats,
            'roles'   => $roles,
            'filters' => [
                'q'    => $request->q    ?? '',
                'role' => $request->role ?? '',
                'sort' => $request->sort ?? '',
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
