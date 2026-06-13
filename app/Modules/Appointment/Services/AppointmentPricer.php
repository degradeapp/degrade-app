<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class AppointmentPricer
{
    /**
     * @param  array<int,float>  $priceOverrides  preço por serviço (id => valor); vazio = preço de catálogo
     */
    public function calculateTotal(Collection $services, array $priceOverrides = []): float
    {
        return $services->sum(fn ($service) => $priceOverrides[$service->id] ?? $service->price);
    }

    /**
     * @param  array<int,float>  $priceOverrides  preço por serviço (id => valor) só para este atendimento
     */
    public function snapshotServices(
        Appointment $appointment,
        Collection $services,
        array $barberIds = [],
        array $priceOverrides = []
    ): void {
        // E3: pré-carrega TODOS os barbeiros envolvidos com o pivot de serviços de
        // uma vez só. Antes era Barber::find() + services()->first() DENTRO do loop
        // (N+1: ~2 queries por serviço); agora é 1 query, resolvida em memória.
        $barberIdsUnique = collect($barberIds)->filter()->unique()->values();
        $barbers = $barberIdsUnique->isEmpty()
            ? collect()
            : Barber::with('services')->whereIn('id', $barberIdsUnique)->get()->keyBy('id');

        foreach ($services as $index => $service) {
            $barberId = $barberIds[$index] ?? null;
            $barber = $barberId ? $barbers->get($barberId) : null;

            $commissionPercentage = $this->resolveCommission($service, $barber);

            $appointment->services()->create([
                'service_id' => $service->id,
                'barber_id' => $barberId,
                'price_snapshot' => $priceOverrides[$service->id] ?? $service->price,
                'commission_percentage_snapshot' => $commissionPercentage,
            ]);
        }
    }

    private function resolveCommission(Service $service, ?Barber $barber): ?float
    {
        // 1) Comissão específica do barbeiro PARA este serviço (pivot já carregado).
        if ($barber) {
            $pivotService = $barber->services->firstWhere('id', $service->id);
            if ($pivotService && $pivotService->pivot->commission_percentage !== null) {
                return (float) $pivotService->pivot->commission_percentage;
            }
        }

        // 2) Comissão padrão do serviço.
        if ($service->commission_percentage !== null) {
            return (float) $service->commission_percentage;
        }

        // 3) Comissão padrão do barbeiro.
        if ($barber) {
            return (float) $barber->default_commission_percentage;
        }

        // 4) Padrão financeiro do tenant.
        $tenant = app('tenant');
        $settings = is_string($tenant->settings ?? null) ? json_decode($tenant->settings, true) : ($tenant->settings ?? []);
        $tenantDefault = data_get($settings, 'financial.default_commission_percentage');

        return $tenantDefault !== null ? (float) $tenantDefault : null;
    }
}
