<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    private Tenant $tenantA;

    private User $ownerA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        app()->instance('tenant', $this->tenantA);
        $this->ownerA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'owner']);
    }

    // ===== 1. MULTI-TENANT ISOLATION / IDOR =====

    public function test_cannot_read_another_tenants_customer_by_id(): void
    {
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($this->ownerA)
            ->getJson("/api/customers/{$customerB->id}")
            ->assertStatus(404);
    }

    public function test_cannot_update_another_tenants_customer(): void
    {
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($this->ownerA)
            ->putJson("/api/customers/{$customerB->id}", ['name' => 'Hijack'])
            ->assertStatus(404);

        $this->assertDatabaseHas('customers', ['id' => $customerB->id, 'name' => $customerB->name]);
    }

    public function test_cannot_delete_another_tenants_customer(): void
    {
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($this->ownerA)
            ->deleteJson("/api/customers/{$customerB->id}")
            ->assertStatus(404);
    }

    public function test_cannot_read_another_tenants_appointment_by_id(): void
    {
        $tenantB = Tenant::factory()->create();
        $customerB = Customer::factory()->create(['tenant_id' => $tenantB->id]);
        $appointmentB = Appointment::create([
            'tenant_id' => $tenantB->id,
            'customer_id' => $customerB->id,
            'barber_id' => null,
            'status' => AppointmentStatus::scheduled->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'total_price' => 50,
        ]);

        $this->actingAs($this->ownerA)
            ->getJson("/api/appointments/{$appointmentB->id}")
            ->assertStatus(404);
    }

    public function test_cannot_read_another_tenants_barber_or_service(): void
    {
        $tenantB = Tenant::factory()->create();
        $barberB = Barber::factory()->create(['tenant_id' => $tenantB->id]);
        $serviceB = Service::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAs($this->ownerA)->getJson("/api/barbers/{$barberB->id}")->assertStatus(404);
        $this->actingAs($this->ownerA)->getJson("/api/services/{$serviceB->id}")->assertStatus(404);
    }

    // ===== 4. MASS ASSIGNMENT / PRIVILEGE ESCALATION =====

    public function test_tenant_id_injection_on_create_is_ignored(): void
    {
        $tenantB = Tenant::factory()->create();

        $this->actingAs($this->ownerA)
            ->postJson('/api/customers', [
                'name' => 'Cliente Teste',
                'phone' => '92991110000',
                'tenant_id' => $tenantB->id, // tentativa de injeção
            ])
            ->assertStatus(201);

        // Cliente pertence ao tenant do usuário autenticado, não ao injetado
        $this->assertDatabaseHas('customers', ['name' => 'Cliente Teste', 'tenant_id' => $this->tenantA->id]);
        $this->assertDatabaseMissing('customers', ['name' => 'Cliente Teste', 'tenant_id' => $tenantB->id]);
    }

    public function test_role_escalation_via_profile_is_ignored(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)
            ->putJson('/api/profile', [
                'name' => 'Novo Nome',
                'role' => 'owner',          // tentativa de escalar privilégio
                'tenant_id' => 999999,      // tentativa de trocar tenant
            ])
            ->assertOk();

        $fresh = User::find($receptionist->id);
        $this->assertSame('receptionist', $fresh->role instanceof \BackedEnum ? $fresh->role->value : $fresh->role);
        $this->assertSame($this->tenantA->id, $fresh->tenant_id);
        $this->assertSame('Novo Nome', $fresh->name);
    }

    // ===== 3. AUTHORIZATION / ROLE-BASED ACCESS =====

    public function test_barber_cannot_access_financial_commissions(): void
    {
        $barber = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'barber']);

        $this->actingAs($barber)->getJson('/api/commissions')->assertStatus(403);
    }

    public function test_non_owner_cannot_access_billing(): void
    {
        $manager = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'manager']);
        $receptionist = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'receptionist']);

        $this->actingAs($manager)->getJson('/api/billing')->assertStatus(403);
        $this->actingAs($receptionist)->getJson('/api/billing')->assertStatus(403);
    }

    public function test_receptionist_cannot_manage_team(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)
            ->postJson('/api/tenant/team', [
                'name' => 'X', 'email' => 'x@x.com', 'role' => 'owner', 'password' => 'senha-12345',
            ])
            ->assertStatus(403);
    }

    // ===== 15. SECURITY HEADERS =====

    public function test_security_headers_present_on_responses(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
