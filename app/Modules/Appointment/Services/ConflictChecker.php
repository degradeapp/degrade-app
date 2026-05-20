<?php

namespace App\Modules\Appointment\Services;

use App\Modules\Barber\Models\Barber;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class ConflictChecker
{
    public function hasConflict(Barber $barber, Carbon $startTime, Carbon $endTime): bool
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

    public function findConflicts(Barber $barber, Carbon $startTime, Carbon $endTime): Collection
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
            ->get();
    }
}
