<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Barber\Models\BarberTimeOff;

readonly class CreateBarberTimeOff
{
    public function __invoke(
        Barber $barber,
        string $date,
        ?string $reason = null,
    ): BarberTimeOff {
        return BarberTimeOff::create([
            'barber_id' => $barber->id,
            'date' => $date,
            'reason' => $reason,
        ]);
    }
}
