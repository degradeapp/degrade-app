<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Models\AppointmentService;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * O que cada papel da EQUIPE enxerga. Recepção e barbeiro são operacionais: veem a
 * agenda e o cliente (nome, celular, observações — precisam pra atender), mas NÃO
 * veem dinheiro (quanto o cliente gastou, a comissão dos colegas) nem o celular
 * pessoal dos colegas. Dono e gerente veem tudo. Regra: User::canSeeFinance().
 */
class RoleConfidentialityTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private Appointment $appointment;

    private Barber $colleague;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'barbearia', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $this->tenant);

        $this->colleague = Barber::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Colega Barbeiro',
            'phone' => '92988887777',
            'default_commission_percentage' => 45,
        ]);
        $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Degradê', 'price' => 50]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Fiel',
            'phone' => '92991234567',
            'total_visits' => 12,
            'total_spent' => 987.65,
        ]);

        $this->appointment = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->colleague->id,
            'status' => AppointmentStatus::scheduled->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(2),
            'total_price' => 50,
        ]);
        AppointmentService::create([
            'tenant_id' => $this->tenant->id,
            'appointment_id' => $this->appointment->id,
            'service_id' => $service->id,
            'barber_id' => $this->colleague->id,
            'price_snapshot' => 50,
            'commission_percentage_snapshot' => 45,
        ]);
    }

    private function member(string $role): User
    {
        return User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => $role]);
    }

    public static function operationalRoles(): array
    {
        return [['barber'], ['receptionist']];
    }

    /** @dataProvider operationalRoles */
    public function test_operational_roles_do_not_see_money_or_colleagues_personal_data(string $role): void
    {
        $user = $this->member($role);

        // Ficha do cliente: nome e celular sim (atendimento), gasto não.
        $customer = $this->actingAs($user)->getJson("/api/customers/{$this->customer->id}")->assertOk();
        $customer->assertJsonPath('phone', '92991234567');
        $this->assertArrayNotHasKey('total_spent', $customer->json());
        $this->assertStringNotContainsString('987.65', $customer->getContent());

        // Lista de clientes: idem.
        $this->assertStringNotContainsString('987.65', $this->getJson('/api/customers')->getContent());

        // Agendamento: sem celular do colega e sem a comissão dele.
        $appointment = $this->getJson("/api/appointments/{$this->appointment->id}")->assertOk()->getContent();
        $this->assertStringNotContainsString('92988887777', $appointment);
        $this->assertStringNotContainsString('commission_percentage_snapshot', $appointment);

        // Busca global: colega sem celular e sem comissão; cliente sem gasto.
        $search = $this->getJson('/api/search?q=Colega')->assertOk()->getContent();
        $this->assertStringContainsString('Colega Barbeiro', $search);
        $this->assertStringNotContainsString('92988887777', $search);
        $this->assertStringNotContainsString('"commission"', $search);
        $this->assertStringNotContainsString('987.65', $this->getJson('/api/search?q=Cliente')->getContent());

        // Excluir cliente é gestão.
        $this->deleteJson("/api/customers/{$this->customer->id}")->assertStatus(403);
        $this->assertNotSoftDeleted($this->customer);

        // Telas/APIs de gestão continuam fechadas.
        $this->getJson('/api/barbers')->assertStatus(403);
        $this->getJson('/api/commissions')->assertStatus(403);
        $this->getJson('/api/customers/export')->assertStatus(403);
    }

    public function test_manager_sees_money_and_can_delete(): void
    {
        $manager = $this->member('manager');

        $this->actingAs($manager)->getJson("/api/customers/{$this->customer->id}")
            ->assertOk()
            ->assertJsonPath('total_spent', '987.65');

        $appointment = $this->getJson("/api/appointments/{$this->appointment->id}")->assertOk()->getContent();
        $this->assertStringContainsString('commission_percentage_snapshot', $appointment);

        $search = $this->getJson('/api/search?q=Colega')->assertOk()->getContent();
        $this->assertStringContainsString('"commission"', $search);

        $this->deleteJson("/api/customers/{$this->customer->id}")->assertNoContent();
    }

    public function test_search_cache_does_not_leak_between_roles(): void
    {
        // O cache é por barbearia: o gerente busca primeiro (enche o cache com a
        // comissão), o barbeiro busca o mesmo termo logo depois.
        $this->actingAs($this->member('manager'))->getJson('/api/search?q=Colega')->assertOk();

        $search = $this->actingAs($this->member('barber'))->getJson('/api/search?q=Colega')->assertOk()->getContent();

        $this->assertStringNotContainsString('92988887777', $search);
        $this->assertStringNotContainsString('"commission"', $search);
    }
}
