<?php

namespace App\Policies;

use App\Modules\Customer\Models\Customer;
use App\Modules\User\Models\User;

class CustomerPolicy extends BasePolicy
{
    public function view(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id;
    }

    /**
     * Excluir cliente é gestão: só dono e gerente. Recepção e barbeiro cadastram e
     * corrigem (update), mas não somem com a base da barbearia.
     */
    public function delete(User $user, Customer $customer): bool
    {
        return $user->tenant_id === $customer->tenant_id
            && ($user->isOwner() || $user->isManager());
    }
}
