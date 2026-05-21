<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentRescheduled;

class SendNotification
{
    public function handle(AppointmentCompleted|AppointmentCancelled|AppointmentRescheduled $event): void
    {
        // Fase 10: implement notification dispatch (SMS, email, WhatsApp)
        // For now: stub
    }
}
