<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use Carbon\Carbon;

class AvailabilityService
{
    public function isAvailable(Barber $barber, Carbon $startTime, Carbon $endTime): bool
    {
        if (! $this->isWithinSchedule($barber, $startTime, $endTime)) {
            return false;
        }

        if ($this->isTimeOff($barber, $startTime, $endTime)) {
            return false;
        }

        return ! $this->hasConflict($barber, $startTime, $endTime);
    }

    public function getAvailableSlots(Barber $barber, Carbon $date, int $durationMinutes): array
    {
        $schedule = $barber->schedules()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if (! $schedule) {
            return [];
        }

        if ($this->isTimeOff($barber, $date, $date->endOfDay())) {
            return [];
        }

        $startTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->end_time);
        $slots = [];

        $current = clone $startTime;
        while ($current->copy()->addMinutes($durationMinutes) <= $endTime) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);

            if (! $this->hasConflict($barber, $current, $slotEnd)) {
                $slots[] = [
                    'start_time' => $current->toIso8601String(),
                    'end_time' => $slotEnd->toIso8601String(),
                ];
            }

            $current->addMinutes(15);
        }

        return $slots;
    }

    /**
     * Grade completa do dia para a UI de agendamento: a janela real de
     * trabalho do barbeiro, cada slot marcado como livre/ocupado (com quem
     * ocupa) e se o barbeiro atende neste dia. Diferente de getAvailableSlots,
     * que devolve só os slots vagos — aqui mostramos o dia inteiro para
     * permitir encaixe em horário ocupado.
     */
    public function getDaySchedule(Barber $barber, Carbon $date, int $durationMinutes): array
    {
        $schedule = $barber->schedules()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        $dayOff = $this->isTimeOff($barber, $date->copy()->startOfDay(), $date->copy()->endOfDay());

        // Folga tem precedência: dizer "de folga" é mais útil que "sem expediente".
        if ($dayOff) {
            return ['works_today' => false, 'reason' => 'time_off', 'window' => null, 'slots' => []];
        }

        if (! $schedule) {
            return ['works_today' => false, 'reason' => 'no_schedule', 'window' => null, 'slots' => []];
        }

        $startTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->start_time);
        $endTime = Carbon::parse($date->format('Y-m-d').' '.$schedule->end_time);

        // Carrega os agendamentos do dia uma única vez (evita N queries por slot).
        $appointments = $barber->appointments()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('starts_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->with('customer:id,name')
            ->get(['id', 'customer_id', 'starts_at', 'ends_at']);

        $slots = [];
        $current = clone $startTime;

        while ($current->copy()->addMinutes($durationMinutes) <= $endTime) {
            $slotEnd = $current->copy()->addMinutes($durationMinutes);

            $conflict = $appointments->first(function ($appt) use ($current, $slotEnd) {
                $apptStart = Carbon::parse($appt->starts_at);
                $apptEnd = $appt->ends_at
                    ? Carbon::parse($appt->ends_at)
                    : $apptStart->copy()->addMinutes(Appointment::DEFAULT_BLOCK_MINUTES);

                return $apptStart < $slotEnd && $apptEnd > $current;
            });

            $slots[] = [
                'time' => $current->format('H:i'),
                'start_time' => $current->toIso8601String(),
                'available' => ! $conflict,
                'occupant' => $conflict
                    ? ['customer' => $conflict->customer?->name ?? 'Ocupado']
                    : null,
            ];

            $current->addMinutes($durationMinutes);
        }

        return [
            'works_today' => true,
            'window' => ['start' => $startTime->format('H:i'), 'end' => $endTime->format('H:i')],
            'slots' => $slots,
        ];
    }

    private function isWithinSchedule(Barber $barber, Carbon $startTime, Carbon $endTime): bool
    {
        $schedule = $barber->schedules()
            ->where('day_of_week', $startTime->dayOfWeek)
            ->first();

        if (! $schedule) {
            return false;
        }

        $scheduleStart = Carbon::parse($startTime->format('Y-m-d').' '.$schedule->start_time);
        $scheduleEnd = Carbon::parse($startTime->format('Y-m-d').' '.$schedule->end_time);

        return $startTime >= $scheduleStart && $endTime <= $scheduleEnd;
    }

    private function isTimeOff(Barber $barber, Carbon $startTime, Carbon $endTime): bool
    {
        $from = $startTime->toDateString();
        $to = $endTime->toDateString();

        // Folga é um período [date .. (end_date ?? date)]. Há conflito se esse
        // período cruza [from .. to]: início da folga <= to E fim da folga >= from.
        return $barber->timeOffs()
            ->whereDate('date', '<=', $to)
            ->where(function ($q) use ($from) {
                $q->whereDate('end_date', '>=', $from)
                    ->orWhere(function ($q2) use ($from) {
                        $q2->whereNull('end_date')->whereDate('date', '>=', $from);
                    });
            })
            ->exists();
    }

    private function hasConflict(Barber $barber, Carbon $startTime, Carbon $endTime): bool
    {
        return $barber->appointments()
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($startTime, $endTime) {
                $q->whereBetween('starts_at', [$startTime, $endTime])
                    ->orWhereBetween('ends_at', [$startTime, $endTime])
                    ->orWhere(function ($q2) use ($startTime, $endTime) {
                        $q2->where('starts_at', '<', $startTime)
                            ->where('ends_at', '>', $endTime);
                    });
            })
            ->exists();
    }
}
