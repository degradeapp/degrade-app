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

    /**
     * Assinar/trocar de plano vale em QUALQUER status (trial, trial vencido, ativo,
     * vencido, cancelado). A versão antiga exigia isTrialing() e só funcionava porque
     * o atalho do dono no BasePolicy::before pula esta checagem: sem ele, ninguém
     * conseguiria assinar depois que o trial acabasse.
     */
    public function selectPlan(User $user, Tenant $tenant): bool
    {
        return $user->role->value === 'owner' && $user->tenant_id === $tenant->id;
    }

    public function cancelPlan(User $user, Tenant $tenant): bool
    {
        // Só o dono cancela, e só a própria barbearia.
        return $user->role->value === 'owner' && $user->tenant_id === $tenant->id;
    }
}
