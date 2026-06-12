<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Barber\Models\BarberSchedule;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_occupation_is_booked_hours_over_available_hours(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $barber = Barber::create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'name' => 'Dono',
            'is_active' => true,
            'default_commission_percentage' => 100,
        ]);

        // Expediente de hoje: 09:00–17:00 = 8h disponíveis.
        BarberSchedule::create([
            'tenant_id' => $tenant->id,
            'barber_id' => $barber->id,
            'day_of_week' => Carbon::now()->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Cliente']);

        // 1 atendimento concluído de 30min, R$80 → ocupado 0,5h de 8h ≈ 6%; ticket médio 80.
        Appointment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'status' => AppointmentStatus::completed->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => Carbon::now()->startOfDay()->setTime(10, 0),
            'ends_at' => Carbon::now()->startOfDay()->setTime(10, 30),
            'total_price' => 80,
        ]);

        $this->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/Index')
                ->where('stats.occupation_available_hours', 8)
                ->where('stats.occupation_booked_hours', 0.5)
                ->where('stats.occupation_rate', 6)
                ->where('stats.avg_ticket_today', 80)
                ->where('stats.revenue_today', 80)
            );
    }

    public function test_occupation_excludes_no_show_and_cancelled(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $barber = Barber::create([
            'tenant_id' => $tenant->id, 'user_id' => $owner->id, 'name' => 'Dono',
            'is_active' => true, 'default_commission_percentage' => 100,
        ]);
        BarberSchedule::create([
            'tenant_id' => $tenant->id, 'barber_id' => $barber->id,
            'day_of_week' => Carbon::now()->dayOfWeek, 'start_time' => '09:00', 'end_time' => '17:00', // 8h
        ]);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'C']);

        $make = function (string $start, string $end, string $status) use ($tenant, $barber, $customer) {
            Appointment::create([
                'tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'barber_id' => $barber->id,
                'status' => $status, 'source' => AppointmentSource::walk_in,
                'starts_at' => Carbon::now()->startOfDay()->setTimeFromTimeString($start),
                'ends_at' => Carbon::now()->startOfDay()->setTimeFromTimeString($end),
                'total_price' => 50,
            ]);
        };

        $make('10:00', '10:30', AppointmentStatus::completed->value); // conta (0,5h)
        $make('11:00', '11:30', AppointmentStatus::no_show->value);   // cadeira vazia, NÃO conta
        $make('12:00', '12:30', AppointmentStatus::cancelled->value); // liberou, NÃO conta

        $this->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.occupation_available_hours', 8)
                ->where('stats.occupation_booked_hours', 0.5) // só o concluído
                ->where('stats.occupation_rate', 6)
                ->etc()
            );
    }

    public function test_receptionist_does_not_see_finance(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $receptionist = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'receptionist']);

        $barber = Barber::create(['tenant_id' => $tenant->id, 'name' => 'B', 'is_active' => true]);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'C']);
        // Atendimento concluído de R$50 hoje: o dono veria 50, a recepção tem que ver 0.
        Appointment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'status' => AppointmentStatus::completed->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => Carbon::now()->startOfDay()->setTime(10, 0),
            'ends_at' => Carbon::now()->startOfDay()->setTime(10, 30),
            'total_price' => 50,
        ]);

        $this->actingAs($receptionist)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_see_finance', false)
                ->where('stats.revenue_today', 0)
                ->where('stats.avg_ticket_today', 0)
                ->where('pending_commissions', 0)
                ->where('revenue_week', [])
                // operacional continua visível
                ->where('stats.appointments_today', 1)
            );
    }

    public function test_barber_does_not_see_finance(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $barberUser = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'barber']);

        $barber = Barber::create(['tenant_id' => $tenant->id, 'name' => 'B', 'is_active' => true]);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'C']);
        Appointment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'status' => AppointmentStatus::completed->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => Carbon::now()->startOfDay()->setTime(10, 0),
            'ends_at' => Carbon::now()->startOfDay()->setTime(10, 30),
            'total_price' => 50,
        ]);

        // Barbeiro vê o operacional (1 atendimento hoje), mas nada de dinheiro.
        $this->actingAs($barberUser)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can_see_finance', false)
                ->where('stats.revenue_today', 0)
                ->where('stats.avg_ticket_today', 0)
                ->where('pending_commissions', 0)
                ->where('revenue_week', [])
                ->where('stats.appointments_today', 1)
            );
    }

    public function test_dashboard_splits_a_fazer_from_a_concluir(): void
    {
        // Fixa o "agora" no meio do dia pra ter passado e futuro dentro de hoje.
        Carbon::setTestNow(Carbon::now()->startOfDay()->setTime(12, 0));

        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $barber = Barber::create(['tenant_id' => $tenant->id, 'name' => 'B', 'is_active' => true]);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'C']);

        $make = function (string $start, string $end, string $status) use ($tenant, $barber, $customer) {
            Appointment::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'barber_id' => $barber->id,
                'status' => $status,
                'source' => AppointmentSource::walk_in,
                'starts_at' => Carbon::now()->startOfDay()->setTimeFromTimeString($start),
                'ends_at' => Carbon::now()->startOfDay()->setTimeFromTimeString($end),
                'total_price' => 50,
            ]);
        };

        $make('09:00', '09:30', AppointmentStatus::completed->value); // concluído
        $make('14:00', '14:30', AppointmentStatus::scheduled->value); // a fazer (futuro)
        $make('10:00', '10:30', AppointmentStatus::scheduled->value); // a concluir (passou, em aberto)

        $this->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.appointments_today', 3)
                ->where('stats.appointments_completed', 1)
                ->where('stats.appointments_pending', 1)   // só o futuro 14:00
                ->where('stats.appointments_awaiting', 1)  // só o 10:00 que passou
                ->has('awaiting_appointments', 1)
                ->has('upcoming_appointments', 1)          // só o futuro
                ->etc()
            );

        Carbon::setTestNow();
    }

    public function test_occupation_zero_when_no_schedule_today(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        // Sem barbeiro com horário hoje → sem expediente → 0% e 0h disponíveis.
        $this->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.occupation_rate', 0)
                ->where('stats.occupation_available_hours', 0)
            );
    }
}
