<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * ⚠️ Ce prédicat **remplace** l'heuristique par défaut de Laravel (`expectsJson()`), il ne
         * s'y ajoute pas. Réduit au seul `api/*` — préfixe qu'aucune route du projet n'utilise —
         * il rendait **toute** erreur de validation en redirection HTML, y compris pour les appels
         * XHR.
         *
         * Conséquence constatée le 2026-08-12 : les modales en axios (nouveau client, nouvelle
         * société, dépôt de pièce) recevaient un 302 suivi d'une page HTML au lieu d'une 422 avec
         * ses `errors`. Leur branche `status === 422` était du code mort, aucune erreur de champ ne
         * s'affichait jamais, et le HTML reçu était même interprété comme une session expirée.
         *
         * `expectsJson()` est donc rétabli, et `api/*` conservé pour un futur préfixe d'API qui
         * répondrait en JSON sans que le client le demande explicitement.
         */
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
