<?php

namespace App\Jobs;

use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\Whatsapp\Services\WhatsappClient;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $window = '24h', // '24h' or '1h'
    ) {}

    public function handle(WhatsappClient $whatsapp): void
    {
        $now = Carbon::now();
        $target = $this->window === '24h' ? $now->copy()->addDay() : $now->copy()->addHour();
        $windowStart = $target->copy()->subMinutes(15);
        $windowEnd = $target->copy()->addMinutes(15);

        $appointments = Appointment::with(['tenant', 'customer', 'barber'])
            ->whereIn('status', [AppointmentStatus::scheduled, AppointmentStatus::confirmed])
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->whereNull('reminder_'.($this->window === '24h' ? '24h' : '1h').'_sent_at')
            ->get();

        foreach ($appointments as $appointment) {
            $this->sendOne($appointment, $whatsapp);
        }
    }

    private function sendOne(Appointment $appointment, WhatsappClient $whatsapp): void
    {
        $tenant = $appointment->tenant;
        if (! $tenant || ! $appointment->customer?->phone) {
            return;
        }

        app(TenantContext::class)->set($tenant);
        app()->instance('tenant', $tenant);

        $account = $tenant->whatsappAccount;
        if (! $account || ! $account->is_active) {
            Log::info('Reminder skipped: tenant has no active WhatsApp account', [
                'tenant_id' => $tenant->id,
                'appointment_id' => $appointment->id,
            ]);

            return;
        }

        $time = Carbon::parse($appointment->starts_at)->format('H:i');
        $date = Carbon::parse($appointment->starts_at)->format('d/m');
        $barber = $appointment->barber?->name ?? 'a equipe';

        $message = $this->window === '24h'
            ? "Oi {$appointment->customer->name}! 😊 Lembrete: você tem horário amanhã ({$date}) às {$time} com {$barber}. Responda CANCELAR se precisar remarcar."
            : "Olá {$appointment->customer->name}! Seu horário com {$barber} é em 1 hora ({$time}). Estamos te esperando!";

        $whatsapp->sendText($account, $appointment->customer->phone, $message);

        $field = $this->window === '24h' ? 'reminder_24h_sent_at' : 'reminder_1h_sent_at';
        $appointment->{$field} = Carbon::now();
        $appointment->save();
    }
}
