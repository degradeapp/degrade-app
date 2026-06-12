<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Exercita o fluxo de autenticação REAL (login por sessão + provider), em vez de
 * actingAs(). actingAs injeta a instância do usuário direto e mascara bugs do
 * provider de auth — foi por isso que o model errado, a recursão do TenantScope
 * e o enum incompleto passaram pela suíte sem serem detectados.
 */
class AuthFlowTest extends TestCase
{
    public function test_auth_provider_points_to_module_user_model(): void
    {
        $this->assertSame(
            User::class,
            config('auth.providers.users.model'),
            'O provider de auth precisa usar o User do módulo (com tenant()/roles/BelongsToTenant)'
        );
    }

    public function test_appointment_status_enum_supports_all_persisted_values(): void
    {
        foreach (['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'] as $value) {
            $this->assertInstanceOf(AppointmentStatus::class, AppointmentStatus::from($value));
        }
    }

    public function test_session_login_then_dashboard_renders(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'password' => 'senha-teste-123',
        ]);
        $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

        // Inclui status 'confirmed' e 'in_progress' — os que quebravam o cast do enum
        foreach ([AppointmentStatus::confirmed, AppointmentStatus::in_progress, AppointmentStatus::completed] as $status) {
            Appointment::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $customer->id,
                'barber_id' => null,
                'status' => $status,
                'source' => AppointmentSource::walk_in,
                'starts_at' => now()->setTime(10, 0),
                'ends_at' => now()->setTime(10, 45),
                'total_price' => 50,
            ]);
        }

        // Login real via sessão (sem actingAs) — passa pelo provider de auth
        $this->post('/login', ['email' => $owner->email, 'password' => 'senha-teste-123'])
            ->assertRedirect('/');

        // Request autenticado seguinte resolve o usuário pelo provider → dashboard
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Dashboard/Index'));
    }

    public function test_login_recovers_soft_deleted_account_within_grace(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(7)]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'password' => 'senha-teste-123',
        ]);

        // Conta excluída: soft-delete + janela de 30 dias.
        $tenant->update(['status' => 'cancelled', 'purge_scheduled_at' => now()->addDays(30)]);
        $tenant->delete();
        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);

        // Logar dentro da janela recupera a barbearia (em vez de cair num estado quebrado).
        $this->post('/login', ['email' => $owner->email, 'password' => 'senha-teste-123'])
            ->assertRedirect('/');

        $restored = Tenant::find($tenant->id);
        $this->assertNotNull($restored, 'a barbearia deve voltar ao logar');
        $this->assertNull($restored->purge_scheduled_at);
        $this->assertSame('trial', $restored->status);
    }

    public function test_session_login_then_agenda_renders(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'password' => 'senha-teste-123',
        ]);

        $this->post('/login', ['email' => $owner->email, 'password' => 'senha-teste-123']);

        $this->get('/appointments')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Appointments/Index'));
    }
}
