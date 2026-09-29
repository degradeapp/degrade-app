<?php

namespace App\Listeners;

use App\Enums\AppointmentSource;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentCreated;
use App\Events\AppointmentRescheduled;
use App\Modules\Notification\Models\NotificationSetting;
use App\Modules\Whatsapp\Services\WhatsappClient;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Mensagens TRANSACIONAIS ao cliente (base legal: execução do contrato). Nada de
 * "volte sempre"/pós-atendimento — marketing foi removido (LGPD) e só volta com opt-in.
 *
 * Na fila e depois do commit: a chamada à API do WhatsApp (até 10s) não segura mais o
 * request de marcar/cancelar/remarcar no 3G, e nunca notifica algo que sofreu rollback.
 */
class SendNotification implements ShouldQueue
{
    public bool $afterCommit = true;

    public function __construct(private WhatsappClient $whatsapp) {}

    public function handle(AppointmentCreated|AppointmentCancelled|AppointmentRescheduled $event): void
    {
        $appointment = $event->appointment;
        $tenant = $appointment->tenant;

        if (! $tenant) {
            return;
        }

        // Pelo bot o cliente já recebeu a confirmação na própria conversa; no balcão
        // (encaixe) ele está ali na frente. Confirmação só pra quem marcou à distância.
        if ($event instanceof AppointmentCreated
            && in_array($appointment->source, [AppointmentSource::whatsapp, AppointmentSource::walk_in], true)) {
            return;
        }

        $settings = NotificationSetting::firstWhere('tenant_id', $tenant->id);

        if ($settings) {
            // Cada evento lê a SUA chave. Remarcação usava 'appointment_confirmed':
            // o toggle "Agendamento remarcado" da tela não surtia efeito nenhum, e
            // desligar "confirmado" calava a remarcação junto, sem o dono entender.
            $eventKey = match (true) {
                $event instanceof AppointmentCreated => 'appointment_confirmed',
                $event instanceof AppointmentCancelled => 'appointment_cancelled',
                $event instanceof AppointmentRescheduled => 'appointment_rescheduled',
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
                if ($this->whatsapp->sendText($account, $customerPhone, $message) === null) {
                    Log::warning('Notificação WhatsApp não enviada', [
                        'tenant_id' => $tenant->id,
                        'appointment_id' => $appointment->id,
                        'event' => class_basename($event),
                    ]);
                }
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
            $event instanceof AppointmentCreated => "{$customerName}, seu horário está confirmado para {$time}. Te esperamos! ✂️",
            $event instanceof AppointmentCancelled => "{$customerName}, seu horário de {$time} foi cancelado. Se foi engano, agenda um novo!",
            $event instanceof AppointmentRescheduled => "{$customerName}, seu horário foi remarcado para {$time}. Te esperamos!",
            default => '',
        };
    }
}
