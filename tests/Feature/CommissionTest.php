<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class CommissionTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Barber $barber;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 5, 28, 10, 0, 0, 'America/Manaus'));

        $this->tenant = Tenant::create([
            'name' => 'Test Barbershop',
            'slug' => 'test-barbershop',
            'status' => 'active',
            'settings' => json_encode([
                'timezone' => 'America/Manaus',
                'locale' => 'pt_BR',
                'financial' => [
                    'default_commission_percentage' => 10,
                ],
            ]),
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Customer',
            'phone' => '92991234567',
        ]);

        $barberUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber User',
            'email' => 'barber@test.local',
            'password' => 'password',
            'role' => 'barber',
        ]);

        $this->barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $barberUser->id,
            'name' => 'Test Barber',
            'phone' => '92998765432',
            'default_commission_percentage' => 15,
        ]);

        foreach (range(0, 6) as $dow) {
            $this->barber->schedules()->create([
                'tenant_id' => $this->tenant->id,
                'day_of_week' => $dow,
                'start_time' => '00:00',
                'end_time' => '23:59',
            ]);
        }

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Corte Simples',
            'duration_minutes' => 30,
            'price' => 100.00,
            'commission_percentage' => 20,
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_completing_appointment_generates_commission(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $this->assertDatabaseHas('commissions', [
            'tenant_id' => $this->tenant->id,
            'barber_id' => $this->barber->id,
            'appointment_id' => $appointmentId,
            'reference_type' => 'appointment',
            'status' => 'pending',
        ]);
    }

    public function test_owner_barber_does_not_generate_commission(): void
    {
        $this->actingAs($this->owner);

        // Barbeiro vinculado ao DONO: ele não recebe comissão (fica com a receita).
        $ownerBarber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => 'Dono Barbeiro',
            'phone' => '92990000000',
            'default_commission_percentage' => 50,
        ]);
        foreach (range(0, 6) as $dow) {
            $ownerBarber->schedules()->create([
                'tenant_id' => $this->tenant->id,
                'day_of_week' => $dow, 'start_time' => '00:00', 'end_time' => '23:59',
            ]);
        }

        $appointmentId = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$ownerBarber->id],
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ])->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete")->assertOk();

        $this->assertDatabaseMissing('commissions', ['barber_id' => $ownerBarber->id]);
    }

    public function test_pay_barber_settles_all_pending_at_once(): void
    {
        $this->actingAs($this->owner);

        foreach ([20, 30] as $amount) {
            Commission::create([
                'tenant_id' => $this->tenant->id,
                'barber_id' => $this->barber->id,
                'reference_type' => 'appointment',
                'status' => 'pending',
                'amount' => $amount,
                'reference_date' => now()->toDateString(),
            ]);
        }

        $res = $this->postJson('/api/commissions/pay-barber', ['barber_id' => $this->barber->id]);
        $res->assertOk();
        $this->assertEquals(2, $res->json('paid_count'));
        $this->assertEquals(50, $res->json('paid_total'));

        $this->assertEquals(0, Commission::where('barber_id', $this->barber->id)->where('status', 'pending')->count());
        $this->assertEquals(2, Commission::where('barber_id', $this->barber->id)->where('status', 'paid')->count());
    }

    public function test_pending_summary_groups_by_barber(): void
    {
        $this->actingAs($this->owner);

        foreach ([20, 30] as $amount) {
            Commission::create([
                'tenant_id' => $this->tenant->id,
                'barber_id' => $this->barber->id,
                'reference_type' => 'appointment',
                'status' => 'pending',
                'amount' => $amount,
                'reference_date' => now()->toDateString(),
            ]);
        }

        $res = $this->getJson('/api/commissions/pending-summary');
        $res->assertOk();
        $this->assertEquals($this->barber->id, $res->json('data.0.barber_id'));
        $this->assertEquals(2, $res->json('data.0.count'));
        $this->assertEquals(50, $res->json('data.0.total'));
    }

    public function test_commission_amount_calculated_correctly(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $commission = Commission::where('appointment_id', $appointmentId)->first();

        $expectedAmount = 100.00 * 20 / 100;
        $this->assertEquals($expectedAmount, $commission->amount);
    }

    public function test_commission_status_set_to_pending(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $commission = Commission::where('appointment_id', $appointmentId)->first();

        $this->assertEquals('pending', $commission->status);
        $this->assertNull($commission->paid_at);
    }

    public function test_commission_with_null_barber_not_generated(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $commissions = Commission::where('appointment_id', $appointmentId)->get();

        $this->assertCount(0, $commissions);
    }

    public function test_multiple_services_generate_multiple_commissions(): void
    {
        $this->actingAs($this->owner);

        $service2 = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Barba',
            'duration_minutes' => 15,
            'price' => 50.00,
            'commission_percentage' => 15,
        ]);

        $barber2User = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Barber User',
            'email' => 'barber2@test.local',
            'password' => 'password',
            'role' => 'barber',
        ]);

        $barber2 = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $barber2User->id,
            'name' => 'Second Barber',
            'phone' => '92987654321',
            'default_commission_percentage' => 12,
        ]);

        foreach (range(0, 6) as $dow) {
            $barber2->schedules()->create([
                'tenant_id' => $this->tenant->id,
                'day_of_week' => $dow,
                'start_time' => '00:00',
                'end_time' => '23:59',
            ]);
        }

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id, $service2->id],
            'barber_ids' => [$this->barber->id, $barber2->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $commissions = Commission::where('appointment_id', $appointmentId)->get();

        $this->assertCount(2, $commissions);
    }

    public function test_completing_appointment_updates_customer_stats(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $this->assertEquals(0, $this->customer->total_visits);
        $this->assertEquals(0, $this->customer->total_spent);
        $this->assertNull($this->customer->last_visit_at);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $updatedCustomer = Customer::find($this->customer->id);

        $this->assertEquals(1, $updatedCustomer->total_visits);
        $this->assertEquals(100.00, $updatedCustomer->total_spent);
        $this->assertNotNull($updatedCustomer->last_visit_at);
    }

    public function test_multiple_appointments_increment_stats(): void
    {
        $this->actingAs($this->owner);

        for ($i = 0; $i < 3; $i++) {
            $startsAt = now()->addHours(2 + ($i * 2));

            $response = $this->postJson('/api/appointments', [
                'customer_id' => $this->customer->id,
                'service_ids' => [$this->service->id],
                'barber_ids' => [$this->barber->id],
                'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
                'source' => 'walk_in',
            ]);

            $appointmentId = $response->json('id');
            $this->postJson("/api/appointments/{$appointmentId}/complete");
        }

        $updatedCustomer = Customer::find($this->customer->id);

        $this->assertEquals(3, $updatedCustomer->total_visits);
        $this->assertEquals(300.00, $updatedCustomer->total_spent);
    }

    public function test_list_commissions_filtered_by_status(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $response = $this->getJson('/api/commissions?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_manager_can_view_commissions(): void
    {
        $manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'email' => 'manager@test.local',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $this->actingAs($manager);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/complete");

        $response = $this->getJson('/api/commissions');

        $response->assertStatus(200);
    }

    public function test_receptionist_cannot_view_commissions(): void
    {
        $receptionist = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Receptionist',
            'email' => 'receptionist@test.local',
            'password' => 'password',
            'role' => 'receptionist',
        ]);

        $this->actingAs($receptionist);

        $response = $this->getJson('/api/commissions');

        $response->assertStatus(403);
    }

    public function test_pending_summary_keeps_name_of_deleted_barber(): void
    {
        $this->actingAs($this->owner);

        // Gera uma comissão pendente concluindo um atendimento do barbeiro.
        $id = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ])->json('id');
        $this->postJson("/api/appointments/{$id}/complete");

        // Exclui o barbeiro (soft-delete).
        $this->deleteJson("/api/barbers/{$this->barber->id}")->assertNoContent();

        // A comissão pendente ainda mostra o nome (withTrashed), não vira "—".
        $this->getJson('/api/commissions/pending-summary')
            ->assertOk()
            ->assertJsonPath('data.0.barber_name', 'Test Barber');
    }
}
