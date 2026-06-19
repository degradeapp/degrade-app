<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $tenant = $user?->tenant;

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatarUrl(),
                    'role' => $user->role instanceof \BackedEnum ? $user->role->value : $user->role,
                ] : null,
            ],

            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug, // base do link público de agendamento (/agendar/{slug})
                'logo_url' => $tenant->logoUrl(),
                'status' => $tenant->status,
                'plan' => $tenant->plan,
                // Fuso da loja: o front exibe os horários nele (não no fuso do navegador).
                'timezone' => $tenant->setting('timezone', config('app.timezone')),
                'onboarding_completed_at' => $tenant->onboarding_completed_at?->toIso8601String(),
            ] : null,

            // Unidades da rede + unidade ativa. Barbeiro/recepção não trocam (can_switch=false).
            'units' => $tenant ? [
                'list' => app(\App\Modules\Tenant\Services\UnitContext::class)->units()
                    ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])->values(),
                'active_id' => app(\App\Modules\Tenant\Services\UnitContext::class)->scopedUnitId(),
                'can_switch' => app(\App\Modules\Tenant\Services\UnitContext::class)->canSwitch(),
                'multiple' => app(\App\Modules\Tenant\Services\UnitContext::class)->hasMultiple(),
            ] : null,

            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
