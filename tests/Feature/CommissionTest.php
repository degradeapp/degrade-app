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

        $this->barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92998765432',
            'default_commission_percentage' => 15,
        ]);

        $this->barber->schedules()->create([
            'day_of_week' => Carbon::now()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

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

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

        $this->assertDatabaseHas('commissions', [
            'tenant_id' => $this->tenant->id,
            'barber_id' => $this->barber->id,
            'appointment_id' => $appointmentId,
            'reference_type' => 'appointment',
            'status' => 'pending',
        ]);
    }

    public function test_commission_amount_calculated_correctly(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

        $commission = Commission::where('appointment_id', $appointmentId)->first();

        $expectedAmount = 100.00 * 20 / 100;
        $this->assertEquals($expectedAmount, $commission->amount);
    }

    public function test_commission_status_set_to_pending(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

        $commission = Commission::where('appointment_id', $appointmentId)->first();

        $this->assertEquals('pending', $commission->status);
        $this->assertNull($commission->paid_at);
    }

    public function test_commission_with_null_barber_not_generated(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

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

        $barber2 = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Second Barber',
            'phone' => '92987654321',
            'default_commission_percentage' => 12,
        ]);

        $barber2->schedules()->create([
            'day_of_week' => Carbon::now()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id, $service2->id],
            'barber_ids' => [$this->barber->id, $barber2->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

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

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

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

            $response = $this->postJson('/appointments', [
                'customer_id' => $this->customer->id,
                'service_ids' => [$this->service->id],
                'barber_ids' => [$this->barber->id],
                'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
                'source' => 'walk_in',
            ]);

            $appointmentId = $response->json('id');
            $this->postJson("/appointments/{$appointmentId}/complete");
        }

        $updatedCustomer = Customer::find($this->customer->id);

        $this->assertEquals(3, $updatedCustomer->total_visits);
        $this->assertEquals(300.00, $updatedCustomer->total_spent);
    }

    public function test_list_commissions_filtered_by_status(): void
    {
        $this->actingAs($this->owner);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

        $response = $this->getJson('/commissions?status=pending');

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

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ]);

        $appointmentId = $response->json('id');

        $this->postJson("/appointments/{$appointmentId}/complete");

        $response = $this->getJson('/commissions');

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

        $response = $this->getJson('/commissions');

        $response->assertStatus(403);
    }
}
