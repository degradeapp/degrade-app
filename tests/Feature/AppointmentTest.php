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
use Carbon\Carbon;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    private Tenant $tenant;

    private User $receptionist;

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
        ]);

        $this->receptionist = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Receptionist',
            'email' => 'receptionist@test.local',
            'password' => 'password',
            'role' => 'receptionist',
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
            'price' => 50.00,
            'commission_percentage' => 20,
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_receptionist_can_create_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'walk_in',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'customer',
                'barber',
                'services',
                'status',
                'source',
                'starts_at',
                'ends_at',
                'total_price',
            ]);

        $this->assertDatabaseHas('appointments', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => AppointmentStatus::scheduled->value,
        ]);
    }

    public function test_create_appointment_fails_with_past_time(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->subHours(1)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_fails_with_conflict(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2);
        $endsAt = $startsAt->copy()->addMinutes(30);

        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => AppointmentStatus::scheduled,
            'source' => AppointmentSource::walk_in,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_price' => 50.00,
        ]);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_sets_total_price(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $this->assertEquals(50.00, $response->json('total_price'));
    }

    public function test_create_appointment_snapshots_pricing(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');
        $appointment = Appointment::find($appointmentId);
        $appointmentService = $appointment->services->first();

        $this->assertEquals(50.00, $appointmentService->price_snapshot);
        $this->assertEquals(20, $appointmentService->commission_percentage_snapshot);
    }

    public function test_appointment_ends_at_calculated(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2);

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'phone',
        ]);

        $endsAt = Carbon::parse($response->json('ends_at'));
        $expectedEndsAt = $startsAt->copy()->addMinutes(30);

        $this->assertEquals($expectedEndsAt->timestamp, $endsAt->timestamp);
    }

    public function test_cancel_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');

        $cancelResponse = $this->postJson("/appointments/{$appointmentId}/cancel", [
            'reason' => 'Customer requested',
        ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('status', AppointmentStatus::cancelled->value);
    }

    public function test_complete_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');

        $completeResponse = $this->postJson("/appointments/{$appointmentId}/complete");

        $completeResponse->assertStatus(200)
            ->assertJsonPath('status', AppointmentStatus::completed->value)
            ->assertJsonStructure(['completed_at']);
    }

    public function test_reschedule_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');
        $newStartsAt = now()->addHours(3)->format('Y-m-d\TH:i:s');

        $rescheduleResponse = $this->putJson("/appointments/{$appointmentId}/reschedule", [
            'starts_at' => $newStartsAt,
        ]);

        $rescheduleResponse->assertStatus(200);
        $this->assertEquals($newStartsAt, $rescheduleResponse->json('starts_at'));
    }

    public function test_list_appointments_filtered_by_status(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $this->postJson('/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $response = $this->getJson('/appointments?status=scheduled');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_available_slots(): void
    {
        $this->actingAs($this->receptionist);

        $date = Carbon::now()->format('Y-m-d');

        $response = $this->getJson("/appointments/availability/barber/{$this->barber->id}?date={$date}&duration_minutes=30");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'barber_id',
                'date',
                'duration_minutes',
                'available_slots',
            ]);

        $this->assertNotEmpty($response->json('available_slots'));
    }

    public function test_available_slots_excludes_conflicts(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2);
        $endsAt = $startsAt->copy()->addMinutes(30);

        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => AppointmentStatus::scheduled,
            'source' => AppointmentSource::walk_in,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'total_price' => 50.00,
        ]);

        $date = $startsAt->format('Y-m-d');

        $response = $this->getJson("/appointments/availability/barber/{$this->barber->id}?date={$date}&duration_minutes=30");

        $slots = collect($response->json('available_slots'))->filter(function ($slot) use ($startsAt) {
            return Carbon::parse($slot['start_time'])->between(
                $startsAt->copy()->subMinutes(30),
                $startsAt->copy()->addMinutes(30)
            );
        });

        $this->assertCount(0, $slots);
    }
}
