<?php

namespace App\Policies;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;

class BillingPolicy extends BasePolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id;
    }

    public function selectPlan(User $user): bool
    {
        return $user->role->value === 'owner' && $user->tenant->isTrialing();
    }
}
