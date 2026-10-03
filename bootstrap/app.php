<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derrière Caddy (TLS terminé par le reverse-proxy) : faire confiance
        // à X-Forwarded-Proto pour générer des URLs https (asset(), url()).
        // Sans ça : Mixed Content (scripts http:// bloqués sur page https).
        $middleware->trustProxies(at: '*');
        $middleware->validateCsrfTokens(except: [
            'orders',
            'orders/*',
            'payment/webhook',
            'mobile/*',
            'api/*',
        ]);
        // En-têtes de sécurité sur toutes les réponses (web + API)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
