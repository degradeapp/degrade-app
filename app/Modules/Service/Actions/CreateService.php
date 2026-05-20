<?php

namespace App\Modules\Service\Actions;

use App\Modules\Service\Models\Service;

readonly class CreateService
{
    public function __invoke(
        string $name,
        int $durationMinutes,
        float $price,
        ?string $description = null,
        ?float $commissionPercentage = null,
    ): Service {
        return Service::create([
            'name' => $name,
            'description' => $description,
            'duration_minutes' => $durationMinutes,
            'price' => $price,
            'commission_percentage' => $commissionPercentage,
            'is_active' => true,
        ]);
    }
}
