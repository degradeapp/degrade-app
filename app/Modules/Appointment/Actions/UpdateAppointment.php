<?php

namespace App\Modules\Appointment\Actions;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AppointmentPricer;
use App\Modules\Appointment\Services\AvailabilityService;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

readonly class UpdateAppointment
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private AppointmentPricer $pricer,
    ) {}

    public function __invoke(
        Appointment $appointment,
        ?Carbon $startsAt = null,
        ?array $serviceIds = null,
        ?array $barberIds = null,
    ): Appointment {
        if ($appointment->status->value !== 'scheduled') {
            throw new \Exception('Apenas agendamentos podem ser atualizados.');
        }

        $startsAt = $startsAt ?? $appointment->starts_at;
        $serviceIds = $serviceIds ?? $appointment->services->pluck('service_id')->toArray();

        $services = Service::whereIn('id', $serviceIds)->get();
        $durationMinutes = $services->sum(fn ($s) => $s->duration_minutes);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $totalPrice = $this->pricer->calculateTotal($services);

        $barberIds = $barberIds ?? array_fill(0, count($serviceIds), null);

        $primaryBarber = collect($barberIds)->first(fn ($id) => $id !== null);
        if ($primaryBarber) {
            $barber = $appointment->barber ?? Barber::find($primaryBarber);
            if (! $this->availabilityService->isAvailable($barber, $startsAt, $endsAt)) {
                throw new \Exception('Barbeiro não está disponível neste horário.');
            }
        }

        return DB::transaction(function () use (
            $appointment,
            $startsAt,
            $endsAt,
            $totalPrice,
            $services,
            $barberIds,
            $primaryBarber
        ) {
            $appointment->update([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_price' => $totalPrice,
                'barber_id' => $primaryBarber,
            ]);

            $appointment->services()->delete();
            $this->pricer->snapshotServices($appointment, $services, $barberIds);

            return $appointment->load('services');
        });
    }
}
