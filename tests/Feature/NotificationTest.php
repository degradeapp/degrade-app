<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Events\AppointmentCancelled;
use App\Events\AppointmentCompleted;
use App\Events\AppointmentCreated;
use App\Events\AppointmentRescheduled;
use App\Listeners\SendNotification;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Customer\Models\Customer;
use App\Modules\Notification\Models\NotificationSetting;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use App\Modules\Whatsapp\Services\WhatsappClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

/**
 * Notificações transacionais (listener SendNotification). Cada chave de
 * NotificationSetting tem um toggle na tela do dono, então desligar uma
 * precisa parar exatamente aquela mensagem — nem menos, nem mais.
 */
class NotificationTest extends TestCase
{
    private Tenant $tenant;

    private Appointment $appointment;

    /** @var object{sent: string[]} */
    private object $spy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['status' => 'active']);
        app()->instance('tenant', $this->tenant);

        WhatsappAccount::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => '123456',
            'access_token' => 'test-token-abcdef',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '92991234567',
        ]);

        $this->appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'status' => AppointmentStatus::scheduled,
            'source' => AppointmentSource::walk_in,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(2)->addMinutes(30),
            'total_price' => 50.00,
        ]);

        $this->spy = new class extends WhatsappClient
        {
            /** @var string[] */
            public array $sent = [];

            public function sendText(WhatsappAccount $account, string $to, string $message): ?string
            {
                $this->sent[] = $message;

                return 'mock-msg-id';
            }
        };
        app()->instance(WhatsappClient::class, $this->spy);
    }

    private function settings(array $overrides = []): NotificationSetting
    {
        return NotificationSetting::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'channels' => ['whatsapp'],
            'appointment_confirmed' => true,
            'appointment_rescheduled' => true,
            'appointment_cancelled' => true,
        ], $overrides));
    }

    public function test_turning_off_reschedule_notification_actually_stops_it(): void
    {
        // O toggle "Agendamento remarcado" existe na tela e é persistido, mas o
        // listener lia appointment_confirmed para o evento de remarcação: desligar
        // não surtia efeito nenhum.
        $this->settings(['appointment_rescheduled' => false]);

        AppointmentRescheduled::dispatch($this->appointment);

        $this->assertSame([], $this->spy->sent, 'Remarcação foi notificada com o toggle desligado.');
    }

    public function test_reschedule_notification_is_sent_when_enabled(): void
    {
        $this->settings(['appointment_rescheduled' => true]);

        AppointmentRescheduled::dispatch($this->appointment);

        $this->assertCount(1, $this->spy->sent);
        $this->assertStringContainsString('remarcado', $this->spy->sent[0]);
    }

    public function test_confirmed_toggle_does_not_silence_reschedule(): void
    {
        // O inverso do bug: desligar "confirmado" não pode calar a remarcação,
        // que tem chave própria.
        $this->settings(['appointment_confirmed' => false, 'appointment_rescheduled' => true]);

        AppointmentRescheduled::dispatch($this->appointment);

        $this->assertCount(1, $this->spy->sent);
    }

    public function test_turning_off_cancellation_notification_stops_it(): void
    {
        $this->settings(['appointment_cancelled' => false]);

        AppointmentCancelled::dispatch($this->appointment);

        $this->assertSame([], $this->spy->sent);
    }

    public function test_without_settings_row_notifications_default_to_on(): void
    {
        // Tenant que nunca abriu a tela de notificações continua recebendo o
        // transacional (o listener usa ?? true).
        AppointmentRescheduled::dispatch($this->appointment);

        $this->assertCount(1, $this->spy->sent);
    }

    public function test_confirmation_is_sent_when_the_appointment_is_booked_remotely(): void
    {
        // O toggle "Agendamento confirmado — Quando o horário é marcado" disparava na
        // CONCLUSÃO, com um "Volte sempre" (nudge de retorno). Agora é na marcação.
        $this->settings();
        $this->appointment->update(['source' => AppointmentSource::customer]);

        AppointmentCreated::dispatch($this->appointment);

        $this->assertCount(1, $this->spy->sent);
        $this->assertStringContainsString('confirmado', $this->spy->sent[0]);
    }

    public function test_confirmation_respects_its_toggle(): void
    {
        $this->settings(['appointment_confirmed' => false]);
        $this->appointment->update(['source' => AppointmentSource::customer]);

        AppointmentCreated::dispatch($this->appointment);

        $this->assertSame([], $this->spy->sent);
    }

    public function test_no_confirmation_for_walk_in_or_bot_bookings(): void
    {
        $this->settings();

        foreach ([AppointmentSource::walk_in, AppointmentSource::whatsapp] as $source) {
            $this->appointment->update(['source' => $source]);
            AppointmentCreated::dispatch($this->appointment);
        }

        $this->assertSame([], $this->spy->sent);
    }

    public function test_completion_sends_no_marketing_message(): void
    {
        // LGPD: pós-atendimento ("obrigado, volte sempre") é marketing — removido.
        $this->settings();

        AppointmentCompleted::dispatch($this->appointment);

        $this->assertSame([], $this->spy->sent);
    }

    public function test_listener_is_queued_after_commit(): void
    {
        $listener = new \ReflectionClass(SendNotification::class);

        $this->assertTrue($listener->implementsInterface(ShouldQueue::class));
        $this->assertTrue($listener->newInstanceWithoutConstructor()->afterCommit);
    }
}
