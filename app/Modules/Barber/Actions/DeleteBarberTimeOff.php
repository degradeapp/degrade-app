<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;

readonly class DeleteBarberTimeOff
{
    public function __invoke(Barber $barber, string $date): bool
    {
        return (bool) $barber->timeOffs()->whereDate('date', $date)->delete();
    }
}
