<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AppointmentPricer;
use App\Modules\Appointment\Services\AvailabilityService;
use App\Modules\Appointment\Services\ConflictChecker;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

readonly class CreateAppointment
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private ConflictChecker $conflictChecker,
        private AppointmentPricer $pricer,
    ) {}

    public function __invoke(
        int $customerId,
        array $serviceIds,
        Carbon $startsAt,
        AppointmentSource $source,
        ?array $barberIds = null,
        ?string $notes = null,
    ): Appointment {
        $services = Service::whereIn('id', $serviceIds)->get();
        $durationMinutes = $services->sum(fn ($s) => $s->duration_minutes);
        $endsAt = $startsAt->copy()->addMinutes($durationMinutes);
        $totalPrice = $this->pricer->calculateTotal($services);

        $barberIds = $barberIds ?? array_fill(0, count($serviceIds), null);

        $primaryBarber = collect($barberIds)->first(fn ($id) => $id !== null);
        if ($primaryBarber) {
            $barber = Barber::find($primaryBarber);
            if (! $this->availabilityService->isAvailable($barber, $startsAt, $endsAt)) {
                throw new \Exception('Barbeiro não está disponível neste horário.');
            }
        }

        return DB::transaction(function () use (
            $customerId,
            $startsAt,
            $endsAt,
            $source,
            $notes,
            $totalPrice,
            $services,
            $barberIds,
            $primaryBarber
        ) {
            $appointment = Appointment::create([
                'customer_id' => $customerId,
                'barber_id' => $primaryBarber,
                'status' => AppointmentStatus::scheduled,
                'source' => $source,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_price' => $totalPrice,
                'notes' => $notes,
            ]);

            $this->pricer->snapshotServices($appointment, $services, $barberIds);

            return $appointment->load('services');
        });
    }
}
