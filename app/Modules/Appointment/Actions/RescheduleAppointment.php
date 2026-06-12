<?php

namespace App\Modules\Appointment\Actions;

use App\Modules\Appointment\Models\Appointment;
use Carbon\Carbon;

readonly class RescheduleAppointment
{
    public function __construct(private UpdateAppointment $updateAppointment) {}

    public function __invoke(Appointment $appointment, Carbon $startsAt): Appointment
    {
        return ($this->updateAppointment)($appointment, $startsAt);
    }
}
