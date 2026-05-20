<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;

readonly class CancelAppointment
{
    public function __invoke(Appointment $appointment, int $userId, ?string $reason = null): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::cancelled,
        ]);

        return $appointment;
    }
}
