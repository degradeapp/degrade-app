<?php

namespace App\Modules\Service\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;

readonly class AttachBarberService
{
    public function __invoke(
        Service $service,
        Barber $barber,
        ?float $commissionPercentage = null,
    ): Service {
        $service->barbers()->syncWithoutDetaching([
            $barber->id => [
                'commission_percentage' => $commissionPercentage,
            ],
        ]);

        return $service->load('barbers');
    }
}
