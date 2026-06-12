<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Unit\Models\Unit;

readonly class UpdateBarber
{
    public function __invoke(
        Barber $barber,
        string $name,
        string $phone,
        ?float $defaultCommissionPercentage = null,
        ?bool $isActive = null,
        ?int $unitId = null,
    ): Barber {
        $data = [
            'name' => $name,
            'phone' => $phone,
            'default_commission_percentage' => $defaultCommissionPercentage ?? $barber->default_commission_percentage,
            'is_active' => $isActive ?? $barber->is_active,
        ];

        // Mover de unidade: só aceita unidade do próprio tenant (Unit é tenant-scoped).
        if ($unitId !== null && Unit::where('id', $unitId)->exists()) {
            $data['unit_id'] = $unitId;
        }

        $barber->update($data);

        return $barber;
    }
}
