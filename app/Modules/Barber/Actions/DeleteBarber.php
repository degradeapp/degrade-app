<?php

namespace App\Modules\Barber\Actions;

use App\Modules\Barber\Models\Barber;

readonly class DeleteBarber
{
    public function __invoke(Barber $barber, int $userId): bool
    {
        $barber->deleted_by = $userId;
        $barber->saveQuietly();

        return (bool) $barber->delete();
    }
}
