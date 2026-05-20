<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use Illuminate\Database\Eloquent\Collection;

class AppointmentPricer
{
    public function calculateTotal(Collection $services): float
    {
        return $services->sum(fn ($service) => $service->price);
    }

    public function snapshotServices(
        Appointment $appointment,
        Collection $services,
        array $barberIds = []
    ): void {
        foreach ($services as $index => $service) {
            $barberId = $barberIds[$index] ?? null;

            $commissionPercentage = $this->resolveCommission($service, $barberId);

            $appointment->services()->create([
                'service_id' => $service->id,
                'barber_id' => $barberId,
                'price_snapshot' => $service->price,
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

        if ($barberId) {
            $barber = Barber::find($barberId);
            if ($barber) {
                return (float) $barber->default_commission_percentage;
            }
        }

        return null;
    }
}
