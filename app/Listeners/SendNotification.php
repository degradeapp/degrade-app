<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentRescheduled;
use App\Modules\Notification\Models\NotificationSetting;
use App\Modules\Whatsapp\Services\WhatsappClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendNotification
{
    public function __construct(private WhatsappClient $whatsapp) {}

    public function handle(AppointmentCompleted|AppointmentCancelled|AppointmentRescheduled $event): void
    {
        $appointment = $event->appointment;
        $tenant = $appointment->tenant;

        if (! $tenant) {
            return;
        }

        $settings = NotificationSetting::firstWhere('tenant_id', $tenant->id);

        if ($settings) {
            $eventKey = match (true) {
                $event instanceof AppointmentCompleted => 'appointment_confirmed',
                $event instanceof AppointmentCancelled => 'appointment_cancelled',
                $event instanceof AppointmentRescheduled => 'appointment_confirmed',
            };

            if (! ($settings->{$eventKey} ?? true)) {
                return;
            }
        }

        $channels = $settings?->channels ?? ['whatsapp', 'email'];

        $customerPhone = $appointment->customer?->phone;
        $customerName = $appointment->customer?->name ?? 'Cliente';

        if (in_array('whatsapp', $channels, true) && $customerPhone) {
            $account = $tenant->whatsappAccount;
            if ($account && $account->is_active) {
                $message = $this->buildMessage($event, $customerName, $appointment);
                $this->whatsapp->sendText($account, $customerPhone, $message);
            }
        }

        // email/sms channels: Phase 3 wires real providers
        if (in_array('email', $channels, true)) {
            Log::info('Notification email (stub)', [
                'tenant_id' => $tenant->id,
                'event' => class_basename($event),
                'customer_id' => $appointment->customer_id,
            ]);
        }
    }

    private function buildMessage(object $event, string $customerName, $appointment): string
    {
        $time = $appointment->starts_at ? Carbon::parse($appointment->starts_at)->format('d/m \à\s H:i') : '';

        return match (true) {
            $event instanceof AppointmentCompleted => "Obrigado pela visita, {$customerName}! Volte sempre. ✂️",
            $event instanceof AppointmentCancelled => "{$customerName}, seu horário de {$time} foi cancelado. Se foi engano, agenda um novo!",
            $event instanceof AppointmentRescheduled => "{$customerName}, seu horário foi remarcado para {$time}. Te esperamos!",
            default => '',
        };
    }
}
