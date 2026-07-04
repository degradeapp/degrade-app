<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;

readonly class UpdateBarber
{
    public function __invoke(
        Barber $barber,
        string $name,
        string $phone,
        ?float $defaultCommissionPercentage = null,
        ?bool $isActive = null,
    ): Barber {
        $barber->update([
            'name' => $name,
            'phone' => $phone,
            'default_commission_percentage' => $defaultCommissionPercentage ?? $barber->default_commission_percentage,
            'is_active' => $isActive ?? $barber->is_active,
        ]);

        return $barber;
    }
}
