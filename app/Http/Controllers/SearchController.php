<?php

namespace App\Http\Controllers;

use App\Models\Dossier;
use App\Models\Partie;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $dossiers = Dossier::with('typeActe')
            ->where(function ($query) use ($q) {
                $query->where('reference', 'like', "%{$q}%")
                      ->orWhere('objet', 'like', "%{$q}%");
            })
            ->limit(8)
            ->get()
            ->map(fn ($d) => [
                'type'     => 'dossier',
                'id'       => $d->id,
                'label'    => $d->reference,
                'sublabel' => $d->objet,
                'badge'    => $d->typeActe?->label,
                'href'     => "/dossiers/{$d->reference}",
            ]);

        $parties = Partie::with('dossier')
            ->where('nom', 'like', "%{$q}%")
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type'     => 'partie',
                'id'       => $p->id,
                'label'    => $p->nom,
                'sublabel' => $p->dossier?->reference . ' — ' . $p->role,
                'badge'    => ucfirst($p->role),
                'href'     => "/dossiers/{$p->dossier?->reference}",
            ]);

        $results = $dossiers->concat($parties)->values();

        return response()->json(['results' => $results]);
    }
}
