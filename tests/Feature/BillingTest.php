<?php

namespace Tests\Feature;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class BillingTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

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

        $response = $this->getJson('/billing');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'current_plan',
                'current_price',
                'barber_limit',
                'status',
                'trial_ends_at',
                'available_plans',
            ],
        ]);
    }

    public function test_trial_tenant_can_select_plan(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/billing/select-plan', [
            'plan' => 'solo',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.current_plan', 'solo');
        $response->assertJsonPath('data.status', 'active');
    }

    public function test_select_plan_creates_customer_and_subscription(): void
    {
        $this->actingAs($this->owner);

        $this->assertNull($this->tenant->asaas_customer_id);

        $response = $this->postJson('/billing/select-plan', [
            'plan' => 'barbearia',
        ]);

        $response->assertStatus(201);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertNotNull($tenant->asaas_customer_id);
        $this->assertNotNull($tenant->asaas_subscription_id);
        $this->assertEquals('barbearia', $tenant->plan);
        $this->assertEquals('active', $tenant->status);
    }

    public function test_select_plan_invalid_plan(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/billing/select-plan', [
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

        $response = $this->postJson('/billing/select-plan', [
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

    public function test_billing_plan_enum(): void
    {
        $solo = BillingPlan::solo;
        $this->assertEquals(59.00, $solo->price());
        $this->assertEquals(1, $solo->barberLimit());
        $this->assertEquals('Solo', $solo->label());

        $barbearia = BillingPlan::barbearia;
        $this->assertEquals(119.00, $barbearia->price());
        $this->assertEquals(4, $barbearia->barberLimit());

        $rede = BillingPlan::rede;
        $this->assertEquals(219.00, $rede->price());
        $this->assertEquals(10, $rede->barberLimit());
    }

    public function test_tenant_barber_limit(): void
    {
        $this->tenant->update(['plan' => 'solo']);
        $this->assertEquals(1, $this->tenant->barberLimit());

        $this->tenant->update(['plan' => 'barbearia']);
        $this->assertEquals(4, $this->tenant->barberLimit());

        $this->tenant->update(['plan' => 'rede']);
        $this->assertEquals(10, $this->tenant->barberLimit());
    }

    public function test_tenant_can_add_barber_within_limit(): void
    {
        $this->tenant->update(['plan' => 'solo', 'status' => 'active']);
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
