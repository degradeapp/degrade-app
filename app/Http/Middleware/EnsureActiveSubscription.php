<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $tenant = $request->user()->tenant;

            if ($tenant && ! $tenant->isActive() && ! $tenant->isTrialing()) {
                // API (JSON): 402 Payment Required em pt-BR, com flag pro front
                // saber rotear pra cobrança. Página (web): redireciona pro /billing.
                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => 'Assinatura inativa ou expirada. Regularize para continuar.',
                        'subscription_inactive' => true,
                    ], 402);
                }

                return redirect('/billing')->with('error', 'Assinatura inativa ou expirada');
            }
        }

        return $next($request);
    }
}
