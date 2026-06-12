<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Barber $ownerBarber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['onboarding_completed_at' => null]);
        app()->instance('tenant', $this->tenant);

        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

        // O dono já entra como barbeiro no registro (RegisterTenantOwner). No onboarding,
        // o passo de horários é que monta o expediente dele — por isso o perfil já existe aqui.
        $this->ownerBarber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => $this->owner->name,
            'default_commission_percentage' => 100,
            'is_active' => true,
        ]);
    }

    private function businessHours(): array
    {
        $hours = [['day_of_week' => 0, 'closed' => true, 'start_time' => null, 'end_time' => null]];
        foreach (range(1, 6) as $dow) {
            $hours[] = ['day_of_week' => $dow, 'closed' => false, 'start_time' => '09:00', 'end_time' => '18:00'];
        }

        return $hours;
    }

    public function test_owner_can_save_business(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/business', [
                'name' => 'Barbearia do Zé',
                'timezone' => 'America/Sao_Paulo',
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $tenant = Tenant::find($this->tenant->id);
        $this->assertSame('Barbearia do Zé', $tenant->name);
        // Confirma que settings é gravado como array utilizável (não JSON duplo-encodado)
        $this->assertSame('America/Sao_Paulo', $tenant->setting('timezone'));
    }

    public function test_save_hours_persists_seven_days(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/hours', ['business_hours' => $this->businessHours()])
            ->assertOk();

        $tenant = Tenant::find($this->tenant->id);
        $this->assertCount(7, $tenant->setting('business_hours'));
    }

    public function test_saving_hours_creates_owner_schedules(): void
    {
        // O passo de horários monta o expediente do barbeiro-dono direto do funcionamento.
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/hours', ['business_hours' => $this->businessHours()])
            ->assertOk();

        // 6 dias abertos (domingo fechado) => 6 horários
        $this->assertCount(6, $this->ownerBarber->fresh()->schedules);
    }

    public function test_save_services_creates_selected_services(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/service', [
                'services' => [
                    ['name' => 'Corte Degradê', 'price' => 50],
                    ['name' => 'Barba', 'price' => 35],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('services', ['tenant_id' => $this->tenant->id, 'name' => 'Corte Degradê', 'price' => 50]);
        $this->assertDatabaseHas('services', ['tenant_id' => $this->tenant->id, 'name' => 'Barba', 'price' => 35]);
    }

    public function test_save_services_requires_at_least_one(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/service', ['services' => []])
            ->assertStatus(422);
    }

    public function test_complete_marks_onboarding_done(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/complete', [])
            ->assertOk()
            ->assertJson(['ok' => true, 'redirect' => '/']);

        $this->assertNotNull(Tenant::find($this->tenant->id)->onboarding_completed_at);
    }

    public function test_non_owner_is_forbidden(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)
            ->postJson('/api/onboarding/business', ['name' => 'X', 'timezone' => 'America/Manaus'])
            ->assertStatus(403);
    }

    public function test_business_validation_fails_without_name(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/business', ['timezone' => 'America/Manaus'])
            ->assertStatus(422);
    }

    public function test_cannot_reaccess_onboarding_after_completed(): void
    {
        $this->tenant->update(['onboarding_completed_at' => now()]);

        // Página do wizard redireciona pro app
        $this->actingAs($this->owner)->get('/onboarding')->assertRedirect('/');

        // Endpoints recusam (409) — não reconfiguram nada depois de concluído
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/hours', ['business_hours' => $this->businessHours()])
            ->assertStatus(409);
    }

    public function test_resaving_hours_does_not_duplicate_owner_schedules(): void
    {
        // Reenviar o passo (voltar/retry) regenera, não duplica.
        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/hours', ['business_hours' => $this->businessHours()])
            ->assertOk();

        $this->actingAs($this->owner)
            ->postJson('/api/onboarding/hours', ['business_hours' => $this->businessHours()])
            ->assertOk();

        // 6 dias abertos, sem duplicar
        $this->assertCount(6, $this->ownerBarber->fresh()->schedules);
    }
}
