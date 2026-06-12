<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Services\UnitContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolve a unidade ativa DEPOIS que o tenant e o usuário já estão disponíveis
 * (roda após EnsureTenantContext). Não derruba requests sem tenant/usuário.
 */
class ResolveUnitContext
{
    public function handle(Request $request, Closure $next)
    {
        app(UnitContext::class)->resolve();

        return $next($request);
    }
}
