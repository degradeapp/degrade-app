<?php

namespace App\Policies;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\User\Models\User;

class AppointmentPolicy extends BasePolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $this->sameTenant($user, $appointment);
    }

    /**
     * Mesmo tenant (a fronteira dura de segurança). Dentro do tenant, toda a
     * equipe opera a agenda inteira (balcão gerencia atendimentos de todos).
     */
    protected function sameTenant(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }
}
