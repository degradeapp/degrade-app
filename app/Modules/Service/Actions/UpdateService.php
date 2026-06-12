<?php

namespace App\Modules\Service\Actions;

use App\Modules\Service\Models\Service;

readonly class UpdateService
{
    public function __invoke(
        Service $service,
        string $name,
        float $price,
        ?string $description = null,
        ?float $commissionPercentage = null,
        ?bool $isActive = null,
    ): Service {
        $service->update([
            'name' => $name,
            'description' => $description,
            'price' => $price,
            'commission_percentage' => $commissionPercentage ?? $service->commission_percentage,
            'is_active' => $isActive ?? $service->is_active,
        ]);

        return $service;
    }
}
