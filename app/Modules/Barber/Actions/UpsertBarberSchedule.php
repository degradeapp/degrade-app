<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Barber\Models\BarberSchedule;

readonly class UpsertBarberSchedule
{
    public function __invoke(
        Barber $barber,
        int $dayOfWeek,
        string $startTime,
        string $endTime,
    ): BarberSchedule {
        return BarberSchedule::updateOrCreate(
            [
                'barber_id' => $barber->id,
                'day_of_week' => $dayOfWeek,
            ],
            [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ]
        );
    }
}
