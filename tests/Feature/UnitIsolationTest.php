<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\BillingPlan;
use App\Modules\Appointment\Actions\CreateAppointment;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\UnitContext;
use App\Modules\Unit\Models\Unit;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * SEGURANÇA da multiunidade. A fronteira dura é o tenant; DENTRO do tenant, barbeiro/
 * recepção ficam presos na própria unidade e NÃO podem ver/agir na agenda de outra.
 * Dono/gerente acessam qualquer unidade + consolidado.
 */
class UnitIsolationTest extends TestCase
{
    private function scenario(): array
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active', 'onboarding_completed_at' => now(), 'plan' => BillingPlan::rede->value,
        ]);
        app()->instance('tenant', $tenant);

        $unitA = Unit::create(['tenant_id' => $tenant->id, 'name' => 'Unidade A', 'is_active' => true]);
        $unitB = Unit::create(['tenant_id' => $tenant->id, 'name' => 'Unidade B', 'is_active' => true]);

        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Cliente']);
        $barberA = Barber::create(['tenant_id' => $tenant->id, 'unit_id' => $unitA->id, 'name' => 'BA', 'is_active' => true]);
        $barberB = Barber::create(['tenant_id' => $tenant->id, 'unit_id' => $unitB->id, 'name' => 'BB', 'is_active' => true]);

        $make = fn (Unit $u, Barber $b) => Appointment::create([
            'tenant_id' => $tenant->id, 'unit_id' => $u->id, 'customer_id' => $customer->id, 'barber_id' => $b->id,
            'status' => AppointmentStatus::scheduled->value, 'source' => AppointmentSource::walk_in->value,
            'starts_at' => Carbon::now()->setTime(10, 0), 'ends_at' => Carbon::now()->setTime(10, 30), 'total_price' => 50,
        ]);

        $apptA = $make($unitA, $barberA);
        $apptB = $make($unitB, $barberB);

        return compact('tenant', 'unitA', 'unitB', 'customer', 'barberA', 'barberB', 'apptA', 'apptB');
    }

    public function test_barber_cannot_view_or_act_on_other_unit_appointment(): void
    {
        ['tenant' => $t, 'unitA' => $unitA, 'apptA' => $apptA, 'apptB' => $apptB] = $this->scenario();
        $barber = User::factory()->create(['tenant_id' => $t->id, 'role' => 'barber', 'unit_id' => $unitA->id]);

        // Vê/age na própria unidade.
        $this->actingAs($barber)->getJson("/api/appointments/{$apptA->id}")->assertOk();

        // Bloqueado na outra unidade (mesmo tenant): ver, cancelar e concluir → 403.
        $this->actingAs($barber)->getJson("/api/appointments/{$apptB->id}")->assertStatus(403);
        $this->actingAs($barber)->postJson("/api/appointments/{$apptB->id}/cancel", ['reason' => 'x'])->assertStatus(403);
        $this->actingAs($barber)->postJson("/api/appointments/{$apptB->id}/complete")->assertStatus(403);
        $this->actingAs($barber)->postJson("/api/appointments/{$apptB->id}/no-show")->assertStatus(403);
    }

    public function test_owner_reaches_any_unit(): void
    {
        ['tenant' => $t, 'apptA' => $apptA, 'apptB' => $apptB] = $this->scenario();
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner', 'unit_id' => null]);

        $this->actingAs($owner)->getJson("/api/appointments/{$apptA->id}")->assertOk();
        $this->actingAs($owner)->getJson("/api/appointments/{$apptB->id}")->assertOk();
    }

    public function test_agenda_api_shows_only_own_unit_for_barber(): void
    {
        ['tenant' => $t, 'unitA' => $unitA, 'apptA' => $apptA] = $this->scenario();
        $barber = User::factory()->create(['tenant_id' => $t->id, 'role' => 'barber', 'unit_id' => $unitA->id]);

        $this->actingAs($barber)
            ->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $apptA->id);
    }

    public function test_dashboard_scopes_today_by_unit(): void
    {
        ['tenant' => $t, 'unitA' => $unitA] = $this->scenario();
        $barber = User::factory()->create(['tenant_id' => $t->id, 'role' => 'barber', 'unit_id' => $unitA->id]);
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner', 'unit_id' => null]);

        // Barbeiro: só a unidade dele (1 atendimento hoje).
        $this->actingAs($barber)->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.appointments_today', 1)->etc());

        // Dono consolidado: as duas unidades (2 atendimentos hoje).
        $this->actingAs($owner)->get('/')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('stats.appointments_today', 2)->etc());
    }

    public function test_creation_stamps_active_unit(): void
    {
        ['tenant' => $t, 'unitA' => $unitA, 'customer' => $customer, 'barberA' => $barberA] = $this->scenario();
        $barber = User::factory()->create(['tenant_id' => $t->id, 'role' => 'barber', 'unit_id' => $unitA->id]);
        $service = Service::create(['tenant_id' => $t->id, 'name' => 'Corte', 'price' => 40, 'is_active' => true]);

        // Simula a requisição do barbeiro (resolve a unidade ativa = unidade dele).
        $this->actingAs($barber);
        app(UnitContext::class)->resolve();

        $appt = app(CreateAppointment::class)(
            customerId: $customer->id,
            serviceIds: [$service->id],
            startsAt: Carbon::now()->addHour(),
            source: AppointmentSource::walk_in,
            barberIds: [$barberA->id],
        );

        $this->assertSame($unitA->id, $appt->unit_id);
    }
}
