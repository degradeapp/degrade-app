<?php

namespace App\Listeners;

use App\Events\AppointmentCompleted;
use App\Modules\Commission\Services\CommissionService;

class GenerateCommission
{
    public function __construct(private CommissionService $commissionService) {}

    public function handle(AppointmentCompleted $event): void
    {
        $this->commissionService->generateForAppointment($event->appointment);
    }
}
