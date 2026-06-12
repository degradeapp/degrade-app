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

        Carbon::setTestNow(Carbon::create(2026, 5, 28, 10, 0, 0, 'America/Manaus'));

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
            'price' => 50.00,
            'commission_percentage' => 20,
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_receptionist_can_create_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/api/appointments', [
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

    public function test_create_appointment_honors_price_override(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        // Catálogo do serviço é 50; barbeiro ajusta para 30 só neste atendimento.
        $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'prices' => [$this->service->id => 30.00],
            'starts_at' => $startsAt,
            'source' => 'walk_in',
        ])->assertStatus(201);

        $this->assertDatabaseHas('appointments', [
            'customer_id' => $this->customer->id,
            'total_price' => 30.00,
        ]);
        $this->assertDatabaseHas('appointment_services', [
            'service_id' => $this->service->id,
            'price_snapshot' => 30.00,
        ]);
    }

    public function test_create_appointment_fails_with_past_time(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->subHours(1)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $response->assertStatus(422);
    }

    public function test_create_appointment_allows_now_walk_in(): void
    {
        $this->actingAs($this->receptionist);

        // "Atender agora": horário = agora truncado ao minuto (alguns segundos no passado)
        $startsAt = now()->startOfMinute()->format('Y-m-d\TH:i:s');

        $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'walk_in',
        ])->assertStatus(201);
    }

    public function test_create_appointment_allows_overbook_encaixe(): void
    {
        // Regra de barbearia: o app NUNCA bloqueia encaixe/walk-in — o barbeiro decide.
        // Marcar em cima de outro agendamento é PERMITIDO (a UI avisa "Encaixar mesmo assim?").
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

        $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt->format('Y-m-d\TH:i:s'),
            'source' => 'phone',
        ])->assertStatus(201);

        // Os dois agendamentos coexistem no mesmo horário.
        $this->assertSame(2, Appointment::where('barber_id', $this->barber->id)
            ->whereBetween('starts_at', [$startsAt->copy()->subMinute(), $startsAt->copy()->addMinute()])
            ->count());
    }

    public function test_create_appointment_sets_total_price(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/api/appointments', [
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

        $response = $this->postJson('/api/appointments', [
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

        $response = $this->postJson('/api/appointments', [
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

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');

        $cancelResponse = $this->postJson("/api/appointments/{$appointmentId}/cancel", [
            'reason' => 'Customer requested',
        ]);

        $cancelResponse->assertStatus(200)
            ->assertJsonPath('status', AppointmentStatus::cancelled->value);
    }

    public function test_complete_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');

        $completeResponse = $this->postJson("/api/appointments/{$appointmentId}/complete");

        $completeResponse->assertStatus(200)
            ->assertJsonPath('status', AppointmentStatus::completed->value)
            ->assertJsonStructure(['completed_at']);
    }

    public function test_no_show_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $appointmentId = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i:s'),
            'source' => 'phone',
        ])->json('id');

        $this->postJson("/api/appointments/{$appointmentId}/no-show")
            ->assertStatus(200)
            ->assertJsonPath('status', AppointmentStatus::no_show->value);

        // "Não compareceu" não gera comissão.
        $this->assertDatabaseMissing('commissions', ['appointment_id' => $appointmentId]);
    }

    public function test_reschedule_appointment(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $response = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $appointmentId = $response->json('id');
        $newStartsAt = now()->addHours(3)->format('Y-m-d\TH:i:s');

        $rescheduleResponse = $this->putJson("/api/appointments/{$appointmentId}/reschedule", [
            'starts_at' => $newStartsAt,
        ]);

        $rescheduleResponse->assertStatus(200);
        $this->assertEquals(
            Carbon::parse($newStartsAt)->timestamp,
            Carbon::parse($rescheduleResponse->json('starts_at'))->timestamp,
        );
    }

    public function test_list_appointments_filtered_by_status(): void
    {
        $this->actingAs($this->receptionist);

        $startsAt = now()->addHours(2)->format('Y-m-d\TH:i:s');

        $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => $startsAt,
            'source' => 'phone',
        ]);

        $response = $this->getJson('/api/appointments?status=scheduled');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_get_available_slots(): void
    {
        $this->actingAs($this->receptionist);

        $date = Carbon::now()->format('Y-m-d');

        $response = $this->getJson("/api/appointments/availability/barber/{$this->barber->id}?date={$date}&duration_minutes=30");

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

        $response = $this->getJson("/api/appointments/availability/barber/{$this->barber->id}?date={$date}&duration_minutes=30");

        $slots = collect($response->json('available_slots'))->filter(function ($slot) use ($startsAt) {
            return Carbon::parse($slot['start_time'])->between(
                $startsAt->copy()->subMinutes(30),
                $startsAt->copy()->addMinutes(30)
            );
        });

        $this->assertCount(0, $slots);
    }

    public function test_day_schedule_returns_full_grid_with_occupied_slots(): void
    {
        $this->actingAs($this->receptionist);

        $date = Carbon::now()->format('Y-m-d');

        // Ocupa o slot das 09:00
        $start = Carbon::parse($date.' 09:00');
        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => AppointmentStatus::scheduled,
            'source' => AppointmentSource::walk_in,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'total_price' => 50.00,
        ]);

        $response = $this->getJson("/api/appointments/availability/barber/{$this->barber->id}/day?date={$date}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'barber_id',
                'date',
                'works_today',
                'window' => ['start', 'end'],
                'slots' => [['time', 'start_time', 'available', 'occupant']],
            ])
            ->assertJsonPath('works_today', true);

        $slots = collect($response->json('slots'));

        $nine = $slots->firstWhere('time', '09:00');
        $this->assertNotNull($nine);
        $this->assertFalse($nine['available']);
        $this->assertSame('Test Customer', $nine['occupant']['customer']);

        // Slot vizinho continua livre
        $eight = $slots->firstWhere('time', '08:00');
        $this->assertTrue($eight['available']);
    }

    public function test_day_schedule_reports_not_working_without_schedule(): void
    {
        $this->actingAs($this->receptionist);

        $this->barber->schedules()->delete();

        $date = Carbon::now()->format('Y-m-d');
        $response = $this->getJson("/api/appointments/availability/barber/{$this->barber->id}/day?date={$date}");

        $response->assertStatus(200)
            ->assertJsonPath('works_today', false);

        $this->assertEmpty($response->json('slots'));
    }

    public function test_day_schedule_blocks_vacation_period(): void
    {
        $this->actingAs($this->receptionist);

        // Férias de hoje até +3 dias
        $this->barber->timeOffs()->create([
            'tenant_id' => $this->tenant->id,
            'date' => Carbon::now()->format('Y-m-d'),
            'end_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
            'reason' => 'Férias',
        ]);

        // Um dia DENTRO do período → sem expediente
        $mid = Carbon::now()->addDays(2)->format('Y-m-d');
        $this->getJson("/api/appointments/availability/barber/{$this->barber->id}/day?date={$mid}")
            ->assertStatus(200)
            ->assertJsonPath('works_today', false);

        // Um dia FORA do período (depois) → tem horários normalmente
        $after = Carbon::now()->addDays(10)->format('Y-m-d');
        $this->getJson("/api/appointments/availability/barber/{$this->barber->id}/day?date={$after}")
            ->assertStatus(200)
            ->assertJsonPath('works_today', true);
    }
}
