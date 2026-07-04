<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Smoke do fluxo crítico de MVP, ponta a ponta pela API real:
 * registrar tenant → onboarding → criar barbeiro → criar serviço →
 * criar cliente → agendar → concluir → comissão gerada → dashboard reflete.
 *
 * Se este teste quebrar, o caminho principal de venda/uso do produto
 * está quebrado. Ele deve continuar passando em qualquer refatoração.
 */
class CriticalFlowTest extends TestCase
{
    public function test_full_critical_flow_from_register_to_dashboard(): void
    {
        // Fixa o relógio num horário comercial pra evitar virada de dia.
        Carbon::setTestNow(Carbon::create(2026, 5, 28, 10, 0, 0, 'America/Manaus'));

        // 1. Registro: nasce tenant (trial), dono e o barbeiro do dono.
        $this->postJson('/api/auth/register', [
            'name' => 'João Dono',
            'email' => 'dono@degrade.test',
            'phone' => '92991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(201);

        $owner = User::where('email', 'dono@degrade.test')->firstOrFail();
        $tenant = Tenant::findOrFail($owner->tenant_id);
        app()->instance('tenant', $tenant);

        $this->assertDatabaseHas('barbers', ['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

        // 2. Onboarding completo (negócio → horários → serviços → conclui).
        $this->actingAs($owner);

        $this->postJson('/api/onboarding/business', [
            'name' => 'Barbearia Degradê',
            'timezone' => 'America/Manaus',
        ])->assertOk();

        $hours = [['day_of_week' => 0, 'closed' => true, 'start_time' => null, 'end_time' => null]];
        foreach (range(1, 6) as $dow) {
            $hours[] = ['day_of_week' => $dow, 'closed' => false, 'start_time' => '09:00', 'end_time' => '18:00'];
        }
        $this->postJson('/api/onboarding/hours', ['business_hours' => $hours])->assertOk();

        $this->postJson('/api/onboarding/service', [
            'services' => [['name' => 'Corte Degradê', 'price' => 50]],
        ])->assertOk();

        $this->postJson('/api/onboarding/complete', [])->assertOk();
        $tenant = $tenant->fresh();
        app()->instance('tenant', $tenant);
        $this->assertNotNull($tenant->onboarding_completed_at);

        $serviceId = $this->getJson('/api/services')->json('0.id')
            ?? $this->getJson('/api/services')->json('data.0.id');
        $this->assertNotNull($serviceId);

        // 3. Barbeiro contratado (trial usa o limite do Barbearia: 10).
        $barberId = $this->postJson('/api/barbers', [
            'name' => 'Carlos Tesoura',
            'phone' => '92991112222',
            'default_commission_percentage' => 20,
        ])->assertStatus(201)->json('id');

        // 4. Cliente do balcão.
        $customerId = $this->postJson('/api/customers', [
            'name' => 'Cliente Fiel',
            'phone' => '(92) 99333-4444',
        ])->assertStatus(201)->json('id');

        // 5. Agendamento hoje às 11:00 com o barbeiro contratado.
        $appointmentId = $this->postJson('/api/appointments', [
            'customer_id' => $customerId,
            'service_ids' => [$serviceId],
            'barber_ids' => [$barberId],
            'starts_at' => '2026-05-28T11:00:00',
            'source' => 'walk_in',
        ])->assertStatus(201)->json('id');

        // 6. Conclusão explícita gera a comissão (20% de R$50 = R$10, pendente).
        $this->postJson("/api/appointments/{$appointmentId}/complete")->assertOk();

        $this->assertDatabaseHas('commissions', [
            'tenant_id' => $tenant->id,
            'barber_id' => $barberId,
            'appointment_id' => $appointmentId,
            'status' => 'pending',
        ]);

        // 7. Dashboard reflete o dia: R$50 de receita e 1 concluído.
        // (renova o user: a relation tenant fica cacheada no modelo entre requests do teste)
        $this->actingAs($owner->fresh());
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Dashboard/Index')
                ->where('stats.revenue_today', 50)
                ->where('stats.appointments_completed', 1)
            );
    }
}
