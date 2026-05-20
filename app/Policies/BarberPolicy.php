<?php

namespace App\Policies;

use App\Modules\Barber\Models\Barber;
use App\Modules\User\Models\User;

class BarberPolicy extends BasePolicy
{
    public function view(User $user, Barber $barber): bool
    {
        return $user->tenant_id === $barber->tenant_id;
    }

    public function update(User $user, Barber $barber): bool
    {
        return $user->tenant_id === $barber->tenant_id;
    }

    public function delete(User $user, Barber $barber): bool
    {
        return $user->tenant_id === $barber->tenant_id;
    }
}
