<?php

namespace App\Http\Middleware;

use App\Models\Dossier;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $urgentCount = 0;
        $revisionCount = 0;

        if ($user) {
            $urgentCount   = Dossier::echeanceUrgente()->count();
            $revisionCount = Dossier::enRevision()->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id'        => $user->id,
                    'name'      => $user->name,
                    'email'     => $user->email,
                    'role'      => $user->role?->value,
                    'roleLabel' => $user->role?->label(),
                    'initiales' => $user->initiales,
                    'avatar'    => $user->avatar,
                    'actif'     => $user->actif,
                    'can'       => [
                        'creerDossier'    => $user->can('create', \App\Models\Dossier::class),
                        'reviser'         => $user->role?->peutReviser() ?? false,
                        'signer'          => $user->role?->peutSigner() ?? false,
                        'gererFormalites' => $user->role?->peutGererFormalites() ?? false,
                        'administrer'     => $user->role?->value === 'administrateur',
                    ],
                ] : null,
            ],
            'notifications' => [
                'urgentCount'   => $urgentCount,
                'revisionCount' => $revisionCount,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
