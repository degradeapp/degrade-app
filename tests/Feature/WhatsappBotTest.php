<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Whatsapp\Enums\WhatsappBotState;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use App\Modules\Whatsapp\Models\WhatsappConversation;
use App\Modules\Whatsapp\Services\BotEngine;
use App\Modules\Whatsapp\Services\WhatsappClient;
use Carbon\Carbon;
use Tests\TestCase;

class WhatsappBotTest extends TestCase
{
    private Tenant $tenant;

    private BotEngine $bot;

    /** @var object{sent: string[]} */
    private object $spy;

    private string $phone = '5592999990000';

    private array $serviceIds = [];

    private array $barberIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // 08:00 garante que todos os slots (09:00+) estejam no futuro
        Carbon::setTestNow(Carbon::create(2026, 5, 28, 8, 0, 0, 'America/Manaus'));

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        WhatsappAccount::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => '123456',
            'access_token' => 'test-token-abcdef',
            'is_active' => true,
            'verified_at' => now(),
        ]);

        // Ordenados por nome: Barba < Corte
        $s1 = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Barba', 'price' => 35.00, 'is_active' => true]);
        $s2 = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 50.00, 'is_active' => true]);
        $this->serviceIds = [$s1->id, $s2->id];

        // Ordenados por nome: Carlos < Pedro
        $b1 = Barber::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos', 'is_active' => true]);
        $b2 = Barber::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pedro', 'is_active' => true]);
        $this->barberIds = [$b1->id, $b2->id];

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

        $this->bot = app(BotEngine::class);
    }

    private function send(string $text): void
    {
        $this->bot->handleIncoming($this->tenant, $this->phone, $text);
    }

    private function conversation(): WhatsappConversation
    {
        return WhatsappConversation::where('phone_number', $this->phone)->firstOrFail();
    }

    private function lastReply(): string
    {
        return (string) (end($this->spy->sent) ?: '');
    }

    public function test_new_conversation_lists_services(): void
    {
        $this->send('oi');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::choosing_service, $conv->state);
        $this->assertCount(2, $conv->session_data['services_offered']);
        $this->assertStringContainsString('Barba', $this->lastReply());
        $this->assertStringContainsString('Corte', $this->lastReply());
        $this->assertStringContainsString('Responda com o número', $this->lastReply());
    }

    public function test_apologizes_when_no_active_services(): void
    {
        Service::where('tenant_id', $this->tenant->id)->update(['is_active' => false]);

        $this->send('oi');

        $this->assertSame(WhatsappBotState::greeting, $this->conversation()->state);
        $this->assertStringContainsString('não cadastrou serviços', $this->lastReply());
    }

    public function test_choosing_service_advances_to_barber(): void
    {
        $this->send('oi');
        $this->send('1');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::choosing_barber, $conv->state);
        $this->assertContains($conv->session_data['service_id'], $this->serviceIds);
        $this->assertCount(2, $conv->session_data['barbers_offered']);
        $this->assertStringContainsString('Qualquer um', $this->lastReply());
        $this->assertStringContainsString('Carlos', $this->lastReply());
        $this->assertStringContainsString('Pedro', $this->lastReply());
    }

    public function test_invalid_service_number_stays_in_step(): void
    {
        $this->send('oi');
        $this->send('99');

        $this->assertSame(WhatsappBotState::choosing_service, $this->conversation()->state);
        $this->assertStringContainsString('Não entendi', $this->lastReply());
    }

    public function test_choosing_any_barber_sets_null(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3'); // count(barbers)+1 => "Qualquer um"

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::choosing_date, $conv->state);
        $this->assertArrayHasKey('barber_id', $conv->session_data);
        $this->assertNull($conv->session_data['barber_id']);
        $this->assertStringContainsString('Para qual dia', $this->lastReply());
    }

    public function test_choosing_specific_barber_sets_id(): void
    {
        $this->send('oi');
        $this->send('1');
        $offered = $this->conversation()->session_data['barbers_offered'];
        $this->send('1');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::choosing_date, $conv->state);
        $this->assertSame($offered[0], $conv->session_data['barber_id']);
    }

    public function test_choosing_today_lists_slots(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('1'); // hoje

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::choosing_slot, $conv->state);
        $this->assertSame('2026-05-28', $conv->session_data['date']);
        $this->assertSame('09:00', $conv->session_data['slots_offered'][0]);
        $this->assertStringContainsString('09:00', $this->lastReply());
    }

    public function test_tomorrow_picks_next_day(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('2'); // amanhã

        $this->assertSame('2026-05-29', $this->conversation()->session_data['date']);
    }

    public function test_invalid_date_stays_in_step(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('blá');

        $this->assertSame(WhatsappBotState::choosing_date, $this->conversation()->state);
        $this->assertStringContainsString('Não entendi', $this->lastReply());
    }

    public function test_choosing_slot_advances_to_confirming(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('1');
        $this->send('1'); // primeiro slot

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::confirming, $conv->state);
        $this->assertSame('09:00', $conv->session_data['slot']);
        $this->assertStringContainsString('Confirme', $this->lastReply());
        $this->assertStringContainsString('09:00', $this->lastReply());
    }

    public function test_declining_confirmation_resets(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('1');
        $this->send('1');
        $this->send('não');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::greeting, $conv->state);
        $this->assertSame([], $conv->session_data);
        $this->assertStringContainsString('cancelei', $this->lastReply());
    }

    public function test_confirming_creates_appointment(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3'); // qualquer barbeiro => sem checagem de disponibilidade
        $this->send('1');
        $this->send('1');
        $this->send('sim');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::done, $conv->state);
        $this->assertSame([], $conv->session_data);
        $this->assertNotNull($conv->customer_id);
        $this->assertStringContainsString('confirmado', $this->lastReply());

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'source' => 'whatsapp',
        ]);
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'phone' => $this->phone,
        ]);
    }

    public function test_cancelar_resets_from_any_state(): void
    {
        $this->send('oi');
        $this->send('1'); // choosing_barber
        $this->send('cancelar');

        $conv = $this->conversation();
        $this->assertSame(WhatsappBotState::greeting, $conv->state);
        $this->assertSame([], $conv->session_data);
        $this->assertStringContainsString('Bem-vindo', $this->lastReply());
    }

    public function test_humano_triggers_handoff(): void
    {
        $this->send('oi');
        $this->send('humano');

        $this->assertSame(WhatsappBotState::human_handoff, $this->conversation()->state);
        $this->assertStringContainsString('chamar alguém', $this->lastReply());
    }

    public function test_handoff_ignores_further_messages(): void
    {
        $this->send('oi');
        $this->send('humano');
        $countBefore = count($this->spy->sent);

        $this->send('ainda preciso de ajuda');

        $this->assertCount($countBefore, $this->spy->sent);
        $this->assertSame(WhatsappBotState::human_handoff, $this->conversation()->state);
    }

    public function test_idle_timeout_restarts_conversation(): void
    {
        $this->send('oi');
        $this->send('1');
        $this->send('3');
        $this->send('1');
        $this->send('1'); // confirming

        $conv = $this->conversation();
        $conv->idle_at = now()->subHour();
        $conv->save();

        // 'sim' deveria confirmar — mas como expirou, reinicia a conversa
        $this->send('sim');

        $this->assertSame(WhatsappBotState::choosing_service, $this->conversation()->state);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_bot_drops_messages_after_spam_threshold(): void
    {
        // 'menu' reseta e responda a cada vez; throttle corta após 15/min
        for ($i = 0; $i < 20; $i++) {
            $this->send('menu');
        }

        $this->assertCount(15, $this->spy->sent);
    }
}
