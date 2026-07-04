<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;

readonly class CreateBarber
{
    public function __invoke(
        string $name,
        string $phone,
        ?int $userId = null,
        ?float $defaultCommissionPercentage = null,
    ): Barber {
        return Barber::create([
            'name' => $name,
            'phone' => $phone,
            'user_id' => $userId,
            'default_commission_percentage' => $defaultCommissionPercentage ?? 0,
            'is_active' => true,
        ]);
    }
}
