<?php

namespace App\Http\Controllers;

use App\Modules\Service\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(): Response
    {
        $user = auth()->user();
        $tenant = $user->tenant;

        return Inertia::render('Onboarding/Wizard', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'completed_at' => $tenant->onboarding_completed_at?->toIso8601String(),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
        ]);
    }

    public function saveBusiness(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|min:2|max:100',
            'timezone' => 'required|string|max:60',
        ]);

        $tenant = app('tenant');
        $settings = $this->parseSettings($tenant);
        $tenant->name = $request->input('name');
        $settings['timezone'] = $request->input('timezone');
        $tenant->settings = $settings;
        $tenant->save();

        return response()->json(['ok' => true]);
    }

    public function saveHours(Request $request): JsonResponse
    {
        $request->validate([
            'business_hours' => 'required|array|min:7|max:7',
        ]);

        $tenant = app('tenant');
        $settings = $this->parseSettings($tenant);
        $settings['business_hours'] = $request->input('business_hours');
        $tenant->settings = $settings;
        $tenant->save();

        // O dono (já criado como barbeiro no registro) herda o expediente da barbearia
        // como horário de trabalho. Assim ele aparece na agenda sem um passo extra.
        $this->syncOwnerSchedules($tenant, $request->input('business_hours'));

        return response()->json(['ok' => true]);
    }

    /**
     * Regenera os horários de trabalho do barbeiro-dono a partir do expediente.
     * Idempotente: apaga e recria, então reenviar o passo não duplica.
     */
    private function syncOwnerSchedules($tenant, array $businessHours): void
    {
        $barber = auth()->user()->barber;
        if (! $barber) {
            return;
        }

        $barber->schedules()->delete();
        foreach ($businessHours as $h) {
            if (! ($h['closed'] ?? false) && ! empty($h['start_time']) && ! empty($h['end_time'])) {
                $barber->schedules()->create([
                    'tenant_id' => $tenant->id,
                    'day_of_week' => $h['day_of_week'],
                    'start_time' => $h['start_time'],
                    'end_time' => $h['end_time'],
                ]);
            }
        }
    }

    public function saveFirstService(Request $request): JsonResponse
    {
        // Aceita vários serviços de uma vez (lista de serviços comuns + preço base).
        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.name' => 'required|string|min:2|max:80',
            'services.*.price' => 'required|numeric|min:0|max:999999',
        ]);

        $tenantId = app('tenant')->id;
        $created = [];

        foreach ($request->input('services') as $item) {
            $service = Service::firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $item['name']],
                ['price' => (float) $item['price'], 'is_active' => true],
            );
            $created[] = ['id' => $service->id, 'name' => $service->name];
        }

        return response()->json(['data' => $created]);
    }

    public function complete(): JsonResponse
    {
        $tenant = app('tenant');
        $tenant->onboarding_completed_at = Carbon::now();
        $tenant->save();

        return response()->json(['ok' => true, 'redirect' => '/']);
    }

    private function parseSettings($tenant): array
    {
        $raw = $tenant->settings ?? [];
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }

        return $raw;
    }
}
