<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->actif) {
            abort(403, 'Accès refusé.');
        }

        if (empty($roles)) {
            return $next($request);
        }

        if (!$user->hasAnyRole($roles)) {
            abort(403, 'Vous n\'avez pas les droits nécessaires pour accéder à cette ressource.');
        }

        return $next($request);
    }
}
