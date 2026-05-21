<?php

namespace App\Policies;

use App\Modules\Commission\Models\Commission;
use App\Modules\User\Models\User;

class CommissionPolicy extends BasePolicy
{
    public function view(User $user, Commission $commission): bool
    {
        return $user->tenant_id === $commission->tenant_id;
    }
}
