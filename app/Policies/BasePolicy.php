<?php

namespace App\Policies;

use App\Modules\User\Enums\UserRole;
use App\Modules\User\Models\User;

abstract class BasePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->role === UserRole::owner) {
            return true;
        }

        return null;
    }
}
