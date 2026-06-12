<?php

namespace App\Policies;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\User\Models\User;

class AppointmentPolicy extends BasePolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->sameTenantAndUnit($user, $appointment);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->sameTenantAndUnit($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->sameTenantAndUnit($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->sameTenantAndUnit($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->sameTenantAndUnit($user, $appointment);
    }

    /**
     * Mesmo tenant (fronteira dura) E unidade permitida. Barbeiro/recepção têm unidade
     * fixa (unit_id): só agem na própria unidade. Dono/gerente (unit_id null): qualquer
     * unidade do tenant. Isto impede um barbeiro da unidade A mexer no agendamento da B.
     */
    protected function sameTenantAndUnit(User $user, Appointment $appointment): bool
    {
        if ($user->tenant_id !== $appointment->tenant_id) {
            return false;
        }

        // Dono/gerente: qualquer unidade do tenant. Barbeiro/recepção COM unidade atribuída:
        // só a própria (isolação numa rede). Sem unidade (loja de 1 unidade): sem restrição.
        // Em rede de verdade, balcão sempre tem unidade (backfill + convite garantem).
        if (! $user->isOwner() && ! $user->isManager() && $user->unit_id !== null) {
            return (int) $appointment->unit_id === (int) $user->unit_id;
        }

        return true;
    }
}
