<?php

namespace Tests\Feature;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Rules\CpfCnpj;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // Não bate na API real do Asaas. As respostas seguem o formato da doc oficial.
        Http::fake([
            '*/customers' => Http::response(['object' => 'customer', 'id' => 'cus_test_'.uniqid()], 200),
            '*/subscriptions' => Http::response(['object' => 'subscription', 'id' => 'sub_test_'.uniqid(), 'status' => 'ACTIVE', 'nextDueDate' => now()->addDays(14)->toDateString()], 200),
            '*/subscriptions/*/payments' => Http::response(['data' => [
                ['id' => 'pay_1', 'status' => 'PENDING', 'dueDate' => now()->addDays(14)->toDateString(), 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_1'],
            ]], 200),
            '*' => Http::response([], 200),
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Test Barbershop',
            'slug' => 'test-barbershop',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'settings' => json_encode([
                'timezone' => 'America/Manaus',
                'locale' => 'pt_BR',
            ]),
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_owner_can_view_billing_page(): void
    {
        $this->actingAs($this->owner);

        $response = $this->getJson('/api/billing');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'current_plan',
                'current_price',
                'staff_limit',
                'status',
                'trial_ends_at',
                'available_plans',
            ],
        ]);
    }

    public function test_trial_tenant_can_select_plan(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/billing/select-plan', [
            'plan' => 'solo',
            'document' => self::CPF,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.current_plan', 'solo');
        $response->assertJsonPath('data.payment_url', 'https://sandbox.asaas.com/i/pay_1');
        // SEGURANÇA: selecionar plano NÃO ativa — só o webhook de pagamento ativa
        $response->assertJsonPath('data.status', 'trial');
    }

    public function test_select_plan_does_not_activate_until_payment_webhook(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])->assertStatus(201);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('trial', $tenant->status, 'plano selecionado não pode ativar sem pagamento');
        $this->assertNotNull($tenant->asaas_subscription_id);

        // Só o webhook de pagamento confirma a assinatura
        $this->asaasWebhook('PAYMENT_CONFIRMED', ['payment' => $this->payment($tenant)])->assertStatus(200);

        $this->assertEquals('active', Tenant::find($this->tenant->id)->status);
    }

    public function test_select_plan_creates_customer_and_subscription(): void
    {
        $this->actingAs($this->owner);

        $this->assertNull($this->tenant->asaas_customer_id);

        $response = $this->postJson('/api/billing/select-plan', [
            'plan' => 'barbearia',
            'document' => self::CPF,
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertNotNull($tenant->asaas_customer_id);
        $this->assertNotNull($tenant->asaas_subscription_id);
        $this->assertEquals('barbearia', $tenant->plan);
        // SEGURANÇA: continua em trial até o webhook de pagamento confirmar
        $this->assertEquals('trial', $tenant->status);
    }

    public function test_select_plan_invalid_plan(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/billing/select-plan', [
            'plan' => 'invalid',
        ]);

        $response->assertStatus(422);
    }

    public function test_manager_cannot_select_plan(): void
    {
        $manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'email' => 'manager@test.local',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $this->actingAs($manager);

        $response = $this->postJson('/api/billing/select-plan', [
            'plan' => 'solo',
        ]);

        $response->assertStatus(403);
    }

    public function test_trial_expired_status_updated(): void
    {
        $this->tenant->update([
            'status' => 'trial',
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->artisan('trial:expire')
            ->assertExitCode(0);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('suspended', $tenant->status);
    }

    public function test_active_trial_not_expired(): void
    {
        $this->tenant->update([
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(7),
        ]);

        $this->artisan('trial:expire')
            ->assertExitCode(0);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('trial', $tenant->status);
    }

    /**
     * CONTRATO COMERCIAL dos planos. Se este teste quebrar, alguém mudou
     * pricing de propósito: revise a decisão antes de ajustar os números.
     * Solo = 1 profissional; Barbearia = até 10; o diferencial entre eles é
     * SÓ o número de profissionais (nenhuma funcionalidade é exclusiva).
     */
    public function test_billing_plan_commercial_contract(): void
    {
        // Exatamente dois planos: solo e barbearia. Rede foi extinto.
        $this->assertEqualsCanonicalizing(
            ['solo', 'barbearia'],
            array_column(BillingPlan::cases(), 'value'),
        );

        $solo = BillingPlan::solo;
        $this->assertEquals(59.00, $solo->price());
        $this->assertEquals(1, $solo->staffLimit());
        $this->assertEquals('Solo', $solo->label());

        $barbearia = BillingPlan::barbearia;
        $this->assertEquals(119.00, $barbearia->price());
        $this->assertEquals(10, $barbearia->staffLimit());
        $this->assertEquals('Barbearia', $barbearia->label());

        // O bot de WhatsApp 24h faz parte de TODOS os planos (a copy precisa dizer isso).
        foreach (BillingPlan::cases() as $plan) {
            $this->assertStringContainsString('bot de WhatsApp 24h', $plan->description());
        }
    }

    public function test_tenant_staff_limit(): void
    {
        $this->tenant->update(['plan' => 'solo']);
        $this->assertEquals(1, $this->tenant->staffLimit());

        $this->tenant->update(['plan' => 'barbearia']);
        $this->assertEquals(10, $this->tenant->staffLimit());
    }

    /**
     * R4: valor legado no banco (ex.: 'rede' antes da migração de dados) não
     * pode derrubar a request. currentPlan() cai no Barbearia com warning.
     */
    public function test_unknown_plan_value_falls_back_to_barbearia(): void
    {
        DB::table('tenants')
            ->where('id', $this->tenant->id)
            ->update(['plan' => 'rede']);

        $tenant = Tenant::find($this->tenant->id);

        $this->assertEquals(BillingPlan::barbearia, $tenant->currentPlan());
        $this->assertEquals(10, $tenant->staffLimit());
    }

    /**
     * A migração de dados converte todo tenant que estava no Rede (extinto)
     * para Barbearia: nenhum tenant fica com valor de enum inexistente.
     */
    public function test_data_migration_converts_rede_to_barbearia(): void
    {
        DB::table('tenants')
            ->where('id', $this->tenant->id)
            ->update(['plan' => 'rede']);

        $migration = require database_path('migrations/2026_07_03_100000_convert_rede_plan_to_barbearia.php');
        $migration->up();

        $this->assertSame('barbearia', DB::table('tenants')->where('id', $this->tenant->id)->value('plan'));
    }

    public function test_tenant_can_add_barber_within_limit(): void
    {
        // Barbearia (10) com apenas o dono cadastrado → ainda há vaga.
        $this->tenant->update(['plan' => 'barbearia', 'status' => 'active']);
        $this->assertTrue($this->tenant->canAddBarber());
    }

    public function test_webhook_payment_received(): void
    {
        $this->tenant->update([
            'status' => 'past_due',
            'asaas_customer_id' => 'cus_123',
            'asaas_subscription_id' => 'sub_123',
        ]);

        $response = $this->asaasWebhook('PAYMENT_RECEIVED', ['payment' => $this->payment($this->tenant->fresh())]);

        $response->assertStatus(200);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('active', $tenant->status);
    }

    public function test_webhook_payment_overdue(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'asaas_customer_id' => 'cus_123',
            'asaas_subscription_id' => 'sub_123',
        ]);

        $response = $this->asaasWebhook('PAYMENT_OVERDUE', ['payment' => $this->payment($this->tenant->fresh())]);

        $response->assertStatus(200);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('past_due', $tenant->status);
    }

    public function test_owner_can_cancel_subscription(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'plan' => 'solo',
            'asaas_subscription_id' => 'sub_123',
        ]);

        $this->actingAs($this->owner)
            ->postJson('/api/billing/cancel')
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertEquals('cancelled', Tenant::find($this->tenant->id)->status);
    }

    public function test_cancel_without_subscription_fails(): void
    {
        // Em trial, sem assinatura criada, não há o que cancelar.
        $this->actingAs($this->owner)
            ->postJson('/api/billing/cancel')
            ->assertStatus(422);
    }

    public function test_manager_cannot_cancel_subscription(): void
    {
        $this->tenant->update(['asaas_subscription_id' => 'sub_123']);

        $manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'email' => 'manager2@test.local',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $this->actingAs($manager)
            ->postJson('/api/billing/cancel')
            ->assertStatus(403);
    }

    public function test_webhook_subscription_cancelled(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'asaas_customer_id' => 'cus_123',
            'asaas_subscription_id' => 'sub_123',
        ]);

        $response = $this->asaasWebhook('SUBSCRIPTION_DELETED', ['subscription' => [
            'object' => 'subscription', 'id' => 'sub_123', 'customer' => 'cus_123', 'status' => 'INACTIVE',
        ]]);

        $response->assertStatus(200);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('cancelled', $tenant->status);
    }

    public function test_changing_plan_updates_the_same_subscription_instead_of_recreating(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])->assertCreated();
        $primeira = $this->tenant->fresh()->asaas_subscription_id;
        $this->assertNotNull($primeira);

        // Upgrade legítimo (ou segundo clique): muda o valor NA MESMA assinatura.
        // Apagar e recriar gerava SUBSCRIPTION_DELETED da antiga e cobrança dupla no mês.
        $this->postJson('/api/billing/select-plan', ['plan' => 'barbearia'])->assertCreated();

        $this->assertSame($primeira, $this->tenant->fresh()->asaas_subscription_id);
        $this->assertSame('barbearia', $this->tenant->fresh()->plan);

        Http::assertSent(fn ($r) => $r->method() === 'PUT'
            && str_ends_with($r->url(), '/subscriptions/'.$primeira)
            && $r['value'] == 119.0
            && $r['updatePendingPayments'] === true);
        Http::assertNotSent(fn ($r) => $r->method() === 'DELETE');
        $this->assertCount(1, collect(Http::recorded())->filter(
            fn ($pair) => $pair[0]->method() === 'POST' && str_ends_with($pair[0]->url(), '/subscriptions')
        ));
    }

    // ===== Contrato real da API do Asaas (docs.asaas.com) =====

    public function test_requests_follow_the_documented_asaas_contract(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => '529.982.247-25'])
            ->assertCreated();

        // Cliente: JSON, access_token, User-Agent e CPF/CNPJ real (sem pontuação).
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/customers')
            && $r->hasHeader('access_token')
            && $r->hasHeader('User-Agent', 'Degrade/1.0')
            && $r->isJson()
            && $r['cpfCnpj'] === '52998224725'
            && $r['email'] === 'owner@test.local');

        // Assinatura: o campo é `customer` (NÃO customerId); 1ª cobrança no fim do trial.
        Http::assertSent(fn ($r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/subscriptions')
            && str_starts_with((string) $r['customer'], 'cus_test_')
            && ! isset($r['customerId'])
            && $r['billingType'] === 'UNDEFINED'
            && $r['cycle'] === 'MONTHLY'
            && $r['value'] == 59.0
            && $r['nextDueDate'] === $this->tenant->trial_ends_at->toDateString());
    }

    public function test_first_charge_is_today_when_trial_already_ended(): void
    {
        $this->tenant->update(['status' => 'suspended', 'trial_ends_at' => now()->subDay()]);

        $this->actingAs($this->owner)
            ->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])
            ->assertCreated();

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/subscriptions')
            && $r['nextDueDate'] === now()->toDateString());
    }

    public function test_select_plan_requires_a_valid_cpf_or_cnpj(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/billing/select-plan', ['plan' => 'solo'])
            ->assertStatus(422)->assertJsonValidationErrors('document');
        $this->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => '111.111.111-11'])
            ->assertStatus(422)->assertJsonValidationErrors('document');
        $this->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => '00000000000000'])
            ->assertStatus(422)->assertJsonValidationErrors('document');

        Http::assertNothingSent();
    }

    public function test_cpf_cnpj_rule_accepts_real_and_alphanumeric_cnpj(): void
    {
        $rule = new CpfCnpj;
        $check = function (string $doc) use ($rule): bool {
            $ok = true;
            $rule->validate('document', $doc, function () use (&$ok) {
                $ok = false;
            });

            return $ok;
        };

        $this->assertTrue($check('529.982.247-25'));
        $this->assertTrue($check('11.222.333/0001-81'));
        // CNPJ alfanumérico (Receita, jul/2026) — exemplo oficial.
        $this->assertTrue($check('12.ABC.345/01DE-35'));
        $this->assertFalse($check('529.982.247-24'));
        $this->assertFalse($check('11.222.333/0001-80'));
        $this->assertFalse($check('12.ABC.345/01DE-36'));
    }

    public function test_billing_document_is_encrypted_and_never_serialized(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])
            ->assertCreated()
            ->assertJsonPath('data.billing_document_hint', '••• 4725')
            ->assertDontSee(self::CPF);

        $raw = DB::table('tenants')->where('id', $this->tenant->id)->value('billing_document');
        $this->assertNotSame(self::CPF, $raw);
        $this->assertSame(self::CPF, $this->tenant->fresh()->billing_document);
        $this->assertArrayNotHasKey('billing_document', $this->tenant->fresh()->toArray());
    }

    public function test_downgrade_is_blocked_when_team_exceeds_new_plan_limit(): void
    {
        User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'barber']);

        $this->actingAs($this->owner)
            ->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])
            ->assertStatus(422);

        Http::assertNothingSent();
    }

    public function test_asaas_error_message_is_shown_to_the_owner(): void
    {
        Http::swap(new HttpFactory);
        Http::fake(['*/customers' => Http::response([
            'errors' => [['code' => 'invalid_cpfCnpj', 'description' => 'O CPF/CNPJ informado é inválido.']],
        ], 400)]);

        $this->actingAs($this->owner)
            ->postJson('/api/billing/select-plan', ['plan' => 'solo', 'document' => self::CPF])
            ->assertStatus(422)
            ->assertJsonPath('message', 'O CPF/CNPJ informado é inválido.');
    }

    public function test_webhook_rejects_wrong_token_and_accepts_the_right_one(): void
    {
        config(['services.asaas.webhook_secret' => 'segredo-forte-do-webhook-com-32-chars!!']);
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'status' => 'past_due']);
        $body = ['id' => 'evt_1', 'event' => 'PAYMENT_CONFIRMED', 'payment' => $this->payment($this->tenant->fresh())];

        $this->postJson('/api/webhooks/asaas', $body)->assertStatus(401);
        $this->postJson('/api/webhooks/asaas', $body, ['asaas-access-token' => 'errado'])->assertStatus(401);
        $this->assertSame('past_due', $this->tenant->fresh()->status);

        $this->postJson('/api/webhooks/asaas', $body, ['asaas-access-token' => 'segredo-forte-do-webhook-com-32-chars!!'])->assertOk();
        $this->assertSame('active', $this->tenant->fresh()->status);
    }

    public function test_webhook_is_idempotent_by_event_id(): void
    {
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'status' => 'active']);
        $payment = $this->payment($this->tenant->fresh());

        $this->asaasWebhook('PAYMENT_OVERDUE', ['payment' => $payment], 'evt_dup')->assertOk();
        $this->tenant->fresh()->update(['status' => 'active']); // regularizou por fora
        $this->asaasWebhook('PAYMENT_OVERDUE', ['payment' => $payment], 'evt_dup')->assertOk(); // reenvio

        $this->assertSame('active', $this->tenant->fresh()->status);
        $this->assertSame(1, DB::table('asaas_webhook_events')->where('event_id', 'evt_dup')->count());
    }

    public function test_late_overdue_for_an_already_paid_payment_is_ignored(): void
    {
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'status' => 'past_due']);
        $payment = $this->payment($this->tenant->fresh());

        $this->asaasWebhook('PAYMENT_CONFIRMED', ['payment' => $payment])->assertOk();
        $this->asaasWebhook('PAYMENT_OVERDUE', ['payment' => $payment])->assertOk();

        $this->assertSame('active', $this->tenant->fresh()->status);
    }

    public function test_card_payment_activates_on_confirmed_without_waiting_for_received(): void
    {
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'payment_url' => 'https://x']);

        $this->asaasWebhook('PAYMENT_CONFIRMED', ['payment' => $this->payment($this->tenant->fresh())])->assertOk();

        $tenant = $this->tenant->fresh();
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->payment_url);
    }

    public function test_events_from_an_old_subscription_do_not_touch_the_tenant(): void
    {
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_novo', 'status' => 'active']);

        $this->asaasWebhook('SUBSCRIPTION_DELETED', ['subscription' => ['id' => 'sub_velho', 'customer' => 'cus_123']])->assertOk();
        $this->asaasWebhook('PAYMENT_OVERDUE', ['payment' => ['id' => 'pay_x', 'customer' => 'cus_123', 'subscription' => 'sub_velho']])->assertOk();

        $this->assertSame('active', $this->tenant->fresh()->status);
    }

    public function test_payment_created_stores_invoice_link_and_refund_blocks(): void
    {
        $this->tenant->update(['asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'status' => 'active']);
        $payment = ['invoiceUrl' => 'https://sandbox.asaas.com/i/pay_9', 'dueDate' => '2026-11-10'] + $this->payment($this->tenant->fresh());

        $this->asaasWebhook('PAYMENT_CREATED', ['payment' => $payment])->assertOk();
        $this->assertSame('https://sandbox.asaas.com/i/pay_9', $this->tenant->fresh()->payment_url);
        $this->assertSame('2026-11-10', $this->tenant->fresh()->next_due_date->toDateString());

        $this->asaasWebhook('PAYMENT_REFUNDED', ['payment' => $payment])->assertOk();
        $this->assertSame('past_due', $this->tenant->fresh()->status);
    }

    public function test_unknown_event_or_customer_still_returns_200(): void
    {
        // 4xx/5xx faz o Asaas re-tentar e, após 15 falhas, pausar a fila inteira.
        $this->asaasWebhook('PAYMENT_BANK_SLIP_VIEWED', ['payment' => ['id' => 'pay_z', 'customer' => 'cus_nao_existe']])->assertOk();
        $this->postJson('/api/webhooks/asaas', [])->assertOk();
    }

    public function test_cancel_clears_subscription_so_a_new_one_can_be_created(): void
    {
        $this->tenant->update(['status' => 'active', 'plan' => 'solo', 'asaas_customer_id' => 'cus_123', 'asaas_subscription_id' => 'sub_123', 'billing_document' => self::CPF]);

        $this->actingAs($this->owner)->postJson('/api/billing/cancel')->assertOk();
        $this->assertNull($this->tenant->fresh()->asaas_subscription_id);

        $this->actingAs($this->owner)->postJson('/api/billing/select-plan', ['plan' => 'solo'])->assertCreated();
        $this->assertNotNull($this->tenant->fresh()->asaas_subscription_id);
    }

    private const CPF = '52998224725';

    private function payment(Tenant $tenant): array
    {
        return [
            'object' => 'payment',
            'id' => 'pay_080225913252',
            'customer' => $tenant->asaas_customer_id,
            'subscription' => $tenant->asaas_subscription_id,
            'value' => 59.0,
            'status' => 'CONFIRMED',
            'billingType' => 'PIX',
        ];
    }

    private function asaasWebhook(string $event, array $object, ?string $id = null)
    {
        return $this->postJson('/api/webhooks/asaas', [
            'id' => $id ?? 'evt_'.uniqid(),
            'event' => $event,
            'dateCreated' => now()->format('Y-m-d H:i:s'),
        ] + $object);
    }
}
