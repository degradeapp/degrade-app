<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;

class UpdateCustomerStats
{
    public function handle(AppointmentCompleted $event): void
    {
        $event->appointment->customer->update([
            'total_visits' => $event->appointment->customer->total_visits + 1,
            'total_spent' => $event->appointment->customer->total_spent + $event->appointment->total_price,
            'last_visit_at' => $event->appointment->completed_at,
        ]);
    }
}
