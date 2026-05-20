<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveSubscription {
    public function handle(Request $request, Closure $next) {
        if ($request->user()) {
            $tenant = $request->user()->tenant;

            if (! $tenant->isActive() && ! $tenant->isTrialing()) {
                return redirect('/billing')->with('error', 'Assinatura inativa ou expirada');
            }
        }

        return $next($request);
    }
}
