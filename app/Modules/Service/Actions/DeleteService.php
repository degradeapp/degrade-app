<?php

namespace App\Modules\Service\Actions;

use App\Modules\Service\Models\Service;

readonly class DeleteService
{
    public function __invoke(Service $service, int $userId): bool
    {
        $service->deleted_by = $userId;
        $service->saveQuietly();

        return (bool) $service->delete();
    }
}
