<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureOnboardingCompleted {
    protected array $except = [
        'onboarding/*',
        'api/onboarding/*',
        'logout',
        'profile',
    ];

    public function handle(Request $request, Closure $next) {
        if ($request->user() && $request->user()->isOwner()) {
            $tenant = $request->user()->tenant;

            if (! $tenant->onboarding_completed_at && ! $this->isExcluded($request)) {
                return redirect('/onboarding');
            }
        }

        return $next($request);
    }

    protected function isExcluded(Request $request): bool {
        return collect($this->except)->some(fn($path) =>
            $request->is($path)
        );
    }
}
