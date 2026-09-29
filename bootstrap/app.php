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
            // Guarda o hash da senha na sessão: trocar/resetar a senha derruba as sessões
            // (e o "lembrar de mim") dos outros aparelhos — ex.: funcionário que saiu.
            // Versão do Sanctum: a do Illuminate quebra com o guard 'sanctum' (RequestGuard).
            \Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \App\Http\Middleware\EnsureTenantContext::class,
        ]);

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserRole::class,
            'subscription.active' => \App\Http\Middleware\EnsureActiveSubscription::class,
            'onboarding.completed' => \App\Http\Middleware\EnsureOnboardingCompleted::class,
            'onboarding.incomplete' => \App\Http\Middleware\EnsureOnboardingNotCompleted::class,
        ]);

        // Webhooks externos (Asaas/Meta) chegam SEM token CSRF; estão no grupo web
        // (logo, sob VerifyCsrfToken) e seriam rejeitados com 419 em produção. A
        // autenticidade deles é garantida por assinatura HMAC nos próprios handlers.
        $middleware->validateCsrfTokens(except: [
            'webhooks/whatsapp',
            'api/webhooks/asaas',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentry: reporta exceções não tratadas. Inerte com SENTRY_LARAVEL_DSN vazio.
        \Sentry\Laravel\Integration::handles($exceptions);

        // Sessão expirada/sem login em chamada de API (JSON): mensagem em pt-BR.
        // Navegação de tela (não-JSON) segue o padrão do Laravel: redireciona pro login.
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Sua sessão expirou. Faça login novamente.'], 401);
            }

            // Visitante na raiz do domínio vê a landing (200, URL intacta) em vez de ser
            // jogado no login; logado, "/" continua sendo o painel. Assim a raiz serve os
            // dois sem mexer na URL do painel (links, testes, onboarding apontam pra "/").
            if ($request->is('/') && $request->isMethod('GET')) {
                return \Inertia\Inertia::render('Landing', [
                    'plans' => \App\Enums\BillingPlan::catalog(),
                    'trialDays' => (int) config('app.trial_days'),
                ])->toResponse($request);
            }

            return null;
        });
    })->create();
