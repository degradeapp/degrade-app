<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;

readonly class DeleteBarber
{
    public function __invoke(Barber $barber, int $userId): bool
    {
        return (bool) $barber->update([
            'deleted_by' => $userId,
            'deleted_at' => now(),
        ]);
    }
}
