<?php

namespace App\Http\Middleware;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

class EnsureTenantContext
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $tenant = Tenant::findOrFail($request->user()->tenant_id);
            app(TenantContext::class)->set($tenant);
            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }
}
