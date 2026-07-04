<?php

namespace Tests\Feature;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
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

        // Não bate na API real do Asaas durante os testes.
        Http::fake([
            '*/customers' => Http::response(['id' => 'cust_test_'.uniqid()], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_test_'.uniqid(), 'status' => 'PENDING'], 200),
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
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.current_plan', 'solo');
        // SEGURANÇA: selecionar plano NÃO ativa — só o webhook de pagamento ativa
        $response->assertJsonPath('data.status', 'trial');
    }

    public function test_select_plan_does_not_activate_until_payment_webhook(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/billing/select-plan', ['plan' => 'solo'])->assertStatus(201);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('trial', $tenant->status, 'plano selecionado não pode ativar sem pagamento');
        $this->assertNotNull($tenant->asaas_subscription_id);

        // Só o webhook de pagamento confirma a assinatura
        $this->postJson('/api/webhooks/asaas', [
            'event' => 'subscription.payment_received',
            'data' => ['customer' => $tenant->asaas_customer_id],
        ])->assertStatus(200);

        $this->assertEquals('active', Tenant::find($this->tenant->id)->status);
    }

    public function test_select_plan_creates_customer_and_subscription(): void
    {
        $this->actingAs($this->owner);

        $this->assertNull($this->tenant->asaas_customer_id);

        $response = $this->postJson('/api/billing/select-plan', [
            'plan' => 'barbearia',
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
            'asaas_customer_id' => 'cust_123',
        ]);

        $response = $this->postJson('/api/webhooks/asaas', [
            'event' => 'subscription.payment_received',
            'data' => ['customer' => 'cust_123'],
        ]);

        $response->assertStatus(200);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('active', $tenant->status);
    }

    public function test_webhook_payment_overdue(): void
    {
        $this->tenant->update([
            'status' => 'active',
            'asaas_customer_id' => 'cust_123',
        ]);

        $response = $this->postJson('/api/webhooks/asaas', [
            'event' => 'subscription.payment_overdue',
            'data' => ['customer' => 'cust_123'],
        ]);

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
            'asaas_customer_id' => 'cust_123',
        ]);

        $response = $this->postJson('/api/webhooks/asaas', [
            'event' => 'subscription.cancelled',
            'data' => ['customer' => 'cust_123'],
        ]);

        $response->assertStatus(200);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertEquals('cancelled', $tenant->status);
    }
}
