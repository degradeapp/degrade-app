<?php

namespace App\Modules\Appointment\Services;

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
        return $barber->timeOffs()
            ->whereBetween('date', [$startTime->toDateString(), $endTime->toDateString()])
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
