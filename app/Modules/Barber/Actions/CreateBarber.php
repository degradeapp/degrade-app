<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Services\UnitContext;
use App\Modules\Unit\Models\Unit;

readonly class CreateBarber
{
    public function __invoke(
        string $name,
        string $phone,
        ?int $userId = null,
        ?float $defaultCommissionPercentage = null,
        ?int $unitId = null,
    ): Barber {
        // Barbeiro trabalha numa unidade. Sem unidade explícita: a ativa da requisição
        // (fallback pra 1ª do tenant) — nunca fica órfão.
        $unitId = $unitId
            ?? app(UnitContext::class)->currentUnitId()
            ?? Unit::query()->orderBy('id')->value('id');

        return Barber::create([
            'unit_id' => $unitId,
            'name' => $name,
            'phone' => $phone,
            'user_id' => $userId,
            'default_commission_percentage' => $defaultCommissionPercentage ?? 0,
            'is_active' => true,
        ]);
    }
}
