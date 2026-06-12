<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\BillingPlan;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Unit\Models\Unit;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    private function tenant(string $plan): Tenant
    {
        $t = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now(), 'plan' => $plan]);
        app()->instance('tenant', $t);
        Unit::create(['tenant_id' => $t->id, 'name' => 'Principal', 'is_active' => true]);

        return $t;
    }

    public function test_owner_on_rede_can_create_second_unit(): void
    {
        $t = $this->tenant(BillingPlan::rede->value);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);

        $this->actingAs($owner)
            ->postJson('/api/units', ['name' => 'Filial Centro', 'address' => 'Rua X, 10'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Filial Centro');

        $this->assertSame(2, $t->units()->count());
    }

    public function test_non_rede_cannot_create_second_unit(): void
    {
        $t = $this->tenant(BillingPlan::barbearia->value);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);

        $this->actingAs($owner)
            ->postJson('/api/units', ['name' => 'Filial'])
            ->assertStatus(403)
            ->assertJsonPath('message', fn ($m) => str_contains((string) $m, 'Rede'));

        $this->assertSame(1, $t->units()->count());
    }

    public function test_manager_cannot_manage_units(): void
    {
        $t = $this->tenant(BillingPlan::rede->value);
        $manager = User::factory()->create(['tenant_id' => $t->id, 'role' => 'manager']);

        // Gestão de unidades é só do dono.
        $this->actingAs($manager)->postJson('/api/units', ['name' => 'X'])->assertStatus(403);
        $this->actingAs($manager)->getJson('/api/units')->assertStatus(403);
        // ...mas o gerente PODE trocar de unidade.
        $this->actingAs($manager)->postJson('/api/units/switch', ['unit_id' => 'all'])->assertOk();
    }

    public function test_cannot_delete_last_unit(): void
    {
        $t = $this->tenant(BillingPlan::rede->value);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);
        $unit = $t->units()->first();

        $this->actingAs($owner)
            ->deleteJson("/api/units/{$unit->id}")
            ->assertStatus(422);
    }

    public function test_reports_consolidated_and_scoped_per_unit(): void
    {
        $t = $this->tenant(BillingPlan::rede->value);
        $unitA = $t->units()->first();
        $unitB = Unit::create(['tenant_id' => $t->id, 'name' => 'B', 'is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);
        $customer = Customer::create(['tenant_id' => $t->id, 'name' => 'C']);

        $make = fn (Unit $u, float $price) => Appointment::create([
            'tenant_id' => $t->id, 'unit_id' => $u->id, 'customer_id' => $customer->id,
            'status' => AppointmentStatus::completed->value, 'source' => AppointmentSource::walk_in->value,
            'starts_at' => Carbon::now()->setTime(10, 0), 'ends_at' => Carbon::now()->setTime(10, 30), 'total_price' => $price,
        ]);
        $make($unitA, 100);
        $make($unitB, 60);

        // Consolidado (sem unidade ativa): receita somada + quebra com 2 unidades.
        $cons = $this->actingAs($owner)->getJson('/api/reports/summary')->assertOk();
        $this->assertSame(160.0, (float) $cons->json('data.revenue'));
        $this->assertCount(2, $cons->json('data.per_unit'));

        // Escopado na unidade A (via ?unit=): só a receita dela.
        $scoped = $this->actingAs($owner)->getJson('/api/reports/summary?unit='.$unitA->id)->assertOk();
        $this->assertSame(100.0, (float) $scoped->json('data.revenue'));
        $this->assertCount(1, $scoped->json('data.per_unit'));
    }

    public function test_owner_can_scope_dashboard_to_a_unit(): void
    {
        $t = $this->tenant(BillingPlan::rede->value);
        $unitA = $t->units()->first();
        $unitB = Unit::create(['tenant_id' => $t->id, 'name' => 'B', 'is_active' => true]);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);
        $customer = Customer::create(['tenant_id' => $t->id, 'name' => 'C']);

        $make = fn (Unit $u) => Appointment::create([
            'tenant_id' => $t->id, 'unit_id' => $u->id, 'customer_id' => $customer->id,
            'status' => AppointmentStatus::scheduled->value, 'source' => AppointmentSource::walk_in->value,
            'starts_at' => Carbon::now()->setTime(10, 0), 'ends_at' => Carbon::now()->setTime(10, 30), 'total_price' => 50,
        ]);
        $make($unitA);
        $make($unitB);

        // Consolidado (sem filtro): 2 atendimentos hoje.
        $this->actingAs($owner)->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.appointments_today', 2)->etc());

        // Escopado na unidade A (via ?unit=): 1 atendimento.
        $this->actingAs($owner)->get('/?unit='.$unitA->id)->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.appointments_today', 1)->etc());
    }
}
