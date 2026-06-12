<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Customer $customer;

    private Barber $barber;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 5, 28, 12, 0, 0, 'America/Manaus'));

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->barber = Barber::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function appointment(string $status, float $price, Carbon $startsAt): Appointment
    {
        return Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => $status,
            'source' => AppointmentSource::walk_in,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'total_price' => $price,
        ]);
    }

    public function test_summary_aggregates_completed_revenue(): void
    {
        $this->appointment(AppointmentStatus::completed->value, 50, now()->subDays(5));
        $this->appointment(AppointmentStatus::completed->value, 100, now()->subDays(3));
        $this->appointment(AppointmentStatus::cancelled->value, 80, now()->subDays(2));
        $this->appointment(AppointmentStatus::no_show->value, 40, now()->subDay());

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(150.0, $data['revenue']);
        $this->assertSame(2, $data['completed_count']);
        $this->assertEquals(75.0, $data['avg_ticket']);
        $this->assertSame(1, $data['cancelled_count']);
        $this->assertSame(1, $data['no_show_count']);
    }

    public function test_summary_excludes_appointments_outside_range(): void
    {
        $this->appointment(AppointmentStatus::completed->value, 50, now()->subDays(5));
        $this->appointment(AppointmentStatus::completed->value, 999, now()->subDays(60)); // fora do range padrão (30d)

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(50.0, $data['revenue']);
        $this->assertSame(1, $data['completed_count']);
    }

    public function test_summary_honors_explicit_date_range(): void
    {
        $this->appointment(AppointmentStatus::completed->value, 50, now()->subDays(5));
        $this->appointment(AppointmentStatus::completed->value, 999, now()->subDays(60));

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary?from='.now()->subDays(90)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(1049.0, $data['revenue']);
        $this->assertSame(2, $data['completed_count']);
    }

    public function test_summary_counts_new_customers(): void
    {
        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $data['new_customers']);
    }

    public function test_summary_lists_top_barbers(): void
    {
        $this->appointment(AppointmentStatus::completed->value, 50, now()->subDays(5));
        $this->appointment(AppointmentStatus::completed->value, 100, now()->subDays(3));

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data['top_barbers']);
        $this->assertSame($this->barber->id, $data['top_barbers'][0]['barber_id']);
        $this->assertSame(2, $data['top_barbers'][0]['count']);
        $this->assertEquals(150.0, $data['top_barbers'][0]['revenue']);
    }

    public function test_summary_respects_tenant_isolation(): void
    {
        $this->appointment(AppointmentStatus::completed->value, 50, now()->subDays(5));

        $other = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $other->id]);
        Appointment::create([
            'tenant_id' => $other->id,
            'customer_id' => $otherCustomer->id,
            'barber_id' => null,
            'status' => AppointmentStatus::completed->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(5)->addMinutes(30),
            'total_price' => 5000,
        ]);

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertEquals(50.0, $data['revenue']);
    }

    public function test_summary_sums_commissions_on_last_day_of_range(): void
    {
        // Regressão: reference_date é coluna `date` mas o SQLite grava "YYYY-MM-DD 00:00:00".
        // Comparar com a string "YYYY-MM-DD" no limite superior descartava o dia inteiro —
        // comissões do último dia do período (caso comum: pagas hoje) sumiam virando R$ 0,00.
        $this->appointment(AppointmentStatus::completed->value, 100, now());

        Commission::create([
            'tenant_id' => $this->tenant->id,
            'barber_id' => $this->barber->id,
            'reference_type' => 'appointment',
            'status' => 'pending',
            'amount' => 20,
            'reference_date' => now()->toDateString(), // último dia do range
        ]);
        Commission::create([
            'tenant_id' => $this->tenant->id,
            'barber_id' => $this->barber->id,
            'reference_type' => 'appointment',
            'status' => 'paid',
            'amount' => 30,
            'reference_date' => now()->toDateString(),
            'paid_at' => now(),
        ]);

        $data = $this->actingAs($this->owner)
            ->getJson('/api/reports/summary?from='.now()->subDays(30)->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->json('data');

        $this->assertEquals(20.0, $data['commissions_pending']);
        $this->assertEquals(30.0, $data['commissions_paid']);
        // Fica pra barbearia = receita − comissões (a pagar + pagas).
        $this->assertEquals(50.0, $data['net_revenue']);
    }

    public function test_summary_forbidden_for_receptionist(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)
            ->getJson('/api/reports/summary')
            ->assertStatus(403);
    }

    public function test_summary_requires_authentication(): void
    {
        $this->getJson('/api/reports/summary')->assertStatus(401);
    }
}
