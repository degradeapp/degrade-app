<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TenantContext needs to be applied globally for all routes
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureTenantContext::class,
            \App\Http\Middleware\ResolveUnitContext::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'onboarding.completed' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'onboarding.incomplete' => \App\Http\Middleware\EnsureOnboardingNotCompleted::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sessão expirada/sem login em chamada de API (JSON): mensagem em pt-BR.
        // Navegação de tela (não-JSON) segue o padrão do Laravel: redireciona pro login.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão expirou. Faça login novamente.'], 401);
            }

            return null;
        });
    })->create();
