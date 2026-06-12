<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentCreated;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AppointmentPricer;
use App\Modules\Appointment\Services\AvailabilityService;
use App\Modules\Appointment\Services\ConflictChecker;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Services\UnitContext;
use App\Modules\Unit\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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
        array $priceOverrides = [],
    ): Appointment {
        $services = Service::whereIn('id', $serviceIds)->get();
        $endsAt = $startsAt->copy()->addMinutes(Appointment::DEFAULT_BLOCK_MINUTES);
        $totalPrice = $this->pricer->calculateTotal($services, $priceOverrides);

        $barberIds = $barberIds ?? array_fill(0, count($serviceIds), null);

        // NÃO bloqueia por disponibilidade: numa barbearia o barbeiro decide o encaixe
        // e walk-in ("Atender agora") acontece a qualquer hora. A UI já avisa quando o
        // horário está ocupado ("Encaixar mesmo assim?"). Só o passado é barrado (no Request).
        $primaryBarber = collect($barberIds)->first(fn ($id) => $id !== null);

        // Unidade onde o agendamento é criado: unidade ativa da requisição; fallback pra
        // 1ª unidade do tenant (bot/jobs sem contexto de unidade) pra NUNCA ficar órfão.
        $unitId = app(UnitContext::class)->currentUnitId()
            ?? Unit::query()->orderBy('id')->value('id');

        return DB::transaction(function () use (
            $customerId,
            $startsAt,
            $endsAt,
            $source,
            $notes,
            $totalPrice,
            $services,
            $barberIds,
            $primaryBarber,
            $priceOverrides,
            $unitId
        ) {
            $appointment = Appointment::create([
                'unit_id' => $unitId,
                'customer_id' => $customerId,
                'barber_id' => $primaryBarber,
                'status' => AppointmentStatus::scheduled,
                'source' => $source,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'total_price' => $totalPrice,
                'notes' => $notes,
            ]);

            $this->pricer->snapshotServices($appointment, $services, $barberIds, $priceOverrides);

            $appointment->load('services');

            Event::dispatch(new AppointmentCreated($appointment));

            return $appointment;
        });
    }
}
