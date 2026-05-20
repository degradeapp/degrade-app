<?php

namespace App\Modules\Appointment\Actions;

use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;

readonly class CompleteAppointment
{
    public function __invoke(Appointment $appointment, int $userId): Appointment
    {
        $appointment->update([
            'status' => AppointmentStatus::completed,
            'completed_at' => now(),
        ]);

        return $appointment;
    }
}
