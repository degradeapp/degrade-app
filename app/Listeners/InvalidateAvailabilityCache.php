<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentRescheduled;
use Illuminate\Support\Facades\Cache;

class InvalidateAvailabilityCache
{
    public function handle(AppointmentCompleted|AppointmentCancelled|AppointmentRescheduled $event): void
    {
        if ($event->appointment->barber_id) {
            Cache::forget("barber:{$event->appointment->barber_id}:availability");
        }
    }
}
