<?php

namespace App\Modules\Service\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;

readonly class DetachBarberService
{
    public function __invoke(Service $service, Barber $barber): bool
    {
        return (bool) $service->barbers()->detach($barber->id);
    }
}
