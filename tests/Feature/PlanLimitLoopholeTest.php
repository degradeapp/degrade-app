<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * O número de profissionais é o ÚNICO diferencial entre Solo e Barbearia, então o
 * teto precisa valer em todo caminho. Brecha fechada aqui: criar barbeiros no trial
 * (teto 10), desativar pra caber no Solo, assinar o Solo e continuar agendando nos
 * desativados — agenda e comissão por barbeiro pelo preço do Solo.
 */
class PlanLimitLoopholeTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Barber $deactivated;

    private Customer $customer;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['status' => 'active', 'plan' => 'solo', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $this->tenant);
        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
        Barber::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

        // Criado no trial e desativado pra caber no Solo.
        $this->deactivated = Barber::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente', 'phone' => '92990001111']);
        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function payload(array $barberIds): array
    {
        return [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => $barberIds,
            'starts_at' => now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ];
    }

    public function test_cannot_book_on_a_deactivated_barber(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/appointments', $this->payload([$this->deactivated->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('barber_ids.0');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_cannot_move_an_appointment_to_a_deactivated_barber(): void
    {
        $ownerBarber = Barber::where('user_id', $this->owner->id)->first();
        $id = $this->actingAs($this->owner)
            ->postJson('/api/appointments', $this->payload([$ownerBarber->id]))
            ->assertCreated()->json('id');

        $this->putJson("/api/appointments/{$id}", ['barber_ids' => [$this->deactivated->id]])
            ->assertStatus(422);
    }

    public function test_editing_an_old_appointment_of_a_since_deactivated_barber_still_works(): void
    {
        // Atendimento marcado quando o barbeiro estava ativo; ele sai depois.
        $this->deactivated->update(['is_active' => true]);
        $id = $this->actingAs($this->owner)
            ->postJson('/api/appointments', $this->payload([$this->deactivated->id]))
            ->assertCreated()->json('id');
        $this->deactivated->update(['is_active' => false]);

        // Mantendo o mesmo barbeiro, a edição passa.
        $this->putJson("/api/appointments/{$id}", ['barber_ids' => [$this->deactivated->id], 'notes' => 'ajuste'])
            ->assertOk();
    }

    public function test_solo_cannot_add_a_second_professional_by_any_path(): void
    {
        $this->actingAs($this->owner);

        // Novo barbeiro (sem login), novo membro da equipe (com login) e reativação.
        $this->postJson('/api/barbers', ['name' => 'Segundo Barbeiro'])->assertStatus(422);
        $this->postJson('/api/tenant/team', [
            'name' => 'Recepção', 'email' => 'recep@test.local', 'password' => 'password123', 'role' => 'receptionist',
        ])->assertStatus(403);
        $this->putJson("/api/barbers/{$this->deactivated->id}", ['is_active' => true])->assertStatus(422);

        $this->assertSame(1, $this->tenant->fresh()->staffCount());
    }
}
