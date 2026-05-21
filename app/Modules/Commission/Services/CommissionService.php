<?php

namespace App\Modules\Commission\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\AppointmentService;
use App\Modules\Commission\Models\Commission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function generateForAppointment(Appointment $appointment): Collection
    {
        return DB::transaction(function () use ($appointment) {
            $commissions = [];

            foreach ($appointment->services as $appointmentService) {
                if ($appointmentService->barber_id === null) {
                    continue;
                }

                $commissionPercentage = $this->resolveCommissionPercentage($appointmentService);
                if ($commissionPercentage === null) {
                    continue;
                }

                $amount = $this->calculateCommissionAmount(
                    $appointmentService->price_snapshot,
                    $commissionPercentage
                );

                $commission = Commission::create([
                    'tenant_id' => $appointment->tenant_id,
                    'barber_id' => $appointmentService->barber_id,
                    'appointment_id' => $appointment->id,
                    'reference_type' => 'appointment',
                    'status' => 'pending',
                    'amount' => $amount,
                    'reference_date' => $appointment->completed_at->toDateString(),
                ]);

                $commissions[] = $commission;
            }

            return new Collection($commissions);
        });
    }

    public function resolveCommissionPercentage(AppointmentService $appointmentService): ?float
    {
        return (float) $appointmentService->commission_percentage_snapshot;
    }

    public function calculateCommissionAmount($price, float $percentage): float
    {
        return (float) ($price * $percentage / 100);
    }
}
