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
        foreach ($services as $index => $service) {
            $barberId = $barberIds[$index] ?? null;

            $commissionPercentage = $this->resolveCommission($service, $barberId);

            $appointment->services()->create([
                'service_id' => $service->id,
                'barber_id' => $barberId,
                'price_snapshot' => $priceOverrides[$service->id] ?? $service->price,
                'commission_percentage_snapshot' => $commissionPercentage,
            ]);
        }
    }

    private function resolveCommission(Service $service, ?int $barberId): ?float
    {
        if ($barberId) {
            $barber = Barber::find($barberId);
            if ($barber) {
                $pivot = $barber->services()->where('service_id', $service->id)->first();
                if ($pivot && $pivot->pivot->commission_percentage !== null) {
                    return (float) $pivot->pivot->commission_percentage;
                }
            }
        }

        if ($service->commission_percentage !== null) {
            return (float) $service->commission_percentage;
        }

        if ($barberId && isset($barber) && $barber) {
            return (float) $barber->default_commission_percentage;
        }

        $tenant = app('tenant');
        $settings = is_string($tenant->settings ?? null) ? json_decode($tenant->settings, true) : ($tenant->settings ?? []);
        $tenantDefault = data_get($settings, 'financial.default_commission_percentage');

        return $tenantDefault !== null ? (float) $tenantDefault : null;
    }
}
