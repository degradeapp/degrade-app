<?php

namespace App\Modules\Appointment\Actions;

use App\Events\AppointmentRescheduled;
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

        $servicesProvided = $serviceIds !== null;
        $barbersProvided = $barberIds !== null;

        $startsAt = $startsAt ?? $appointment->starts_at;
        $serviceIds = $serviceIds ?? $appointment->services->pluck('service_id')->toArray();

        $services = Service::whereIn('id', $serviceIds)->get();
        $endsAt = $startsAt->copy()->addMinutes(Appointment::DEFAULT_BLOCK_MINUTES);
        $totalPrice = $this->pricer->calculateTotal($services);

        // Barbeiros alinhados à ORDEM de $services (whereIn pode reordenar). Sem barber_ids
        // e sem trocar serviços, PRESERVA os barbeiros atuais — antes zerava todos, perdendo
        // quem atende e impedindo a geração de comissão ao concluir.
        if ($barbersProvided) {
            $map = [];
            foreach ($serviceIds as $i => $sid) {
                $map[$sid] = $barberIds[$i] ?? null;
            }
            $barberIds = $services->map(fn ($s) => $map[$s->id] ?? null)->toArray();
        } elseif (! $servicesProvided) {
            $existing = $appointment->services->pluck('barber_id', 'service_id');
            $barberIds = $services->map(fn ($s) => $existing[$s->id] ?? null)->toArray();
        } else {
            $barberIds = array_fill(0, $services->count(), null);
        }

        // Não bloqueia por disponibilidade (mesma regra do create: o app nunca bloqueia
        // encaixe/remarcação — o barbeiro decide). Só o passado é barrado no Request.
        $primaryBarber = collect($barberIds)->first(fn ($id) => $id !== null);

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

            $appointment->load('services');
            AppointmentRescheduled::dispatch($appointment);

            return $appointment;
        });
    }
}
