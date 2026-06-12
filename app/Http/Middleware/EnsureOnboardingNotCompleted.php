<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Fecha o fluxo de onboarding depois de concluído. Sem isso, o dono conseguia
 * voltar pro wizard e re-submeter, recriando serviço/barbeiro já existentes
 * (violação de constraint único). Página → redireciona pro app; API → 409.
 */
class EnsureOnboardingNotCompleted
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $request->user()?->tenant;

        if ($tenant && $tenant->onboarding_completed_at) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'O onboarding já foi concluído.'], 409);
            }

            return redirect('/');
        }

        return $next($request);
    }
}
