<?php

namespace App\Policies;

use App\Modules\Service\Models\Service;
use App\Modules\User\Models\User;

class ServicePolicy extends BasePolicy
{
    public function view(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id;
    }

    public function update(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id;
    }

    public function delete(User $user, Service $service): bool
    {
        return $user->tenant_id === $service->tenant_id;
    }
}
