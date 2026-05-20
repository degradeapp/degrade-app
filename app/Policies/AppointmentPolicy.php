<?php

namespace App\Policies;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\User\Models\User;

class AppointmentPolicy extends BasePolicy
{
    public function view(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    public function cancel(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }

    public function complete(User $user, Appointment $appointment): bool
    {
        return $user->tenant_id === $appointment->tenant_id;
    }
}
