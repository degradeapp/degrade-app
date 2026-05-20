<?php

namespace App\Modules\Service\Actions;

use App\Modules\Service\Models\Service;

readonly class DeleteService
{
    public function __invoke(Service $service, int $userId): bool
    {
        return (bool) $service->update([
            'deleted_by' => $userId,
            'deleted_at' => now(),
        ]);
    }
}
