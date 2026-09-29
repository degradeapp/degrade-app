<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Proteções de produção que não são regra de negócio, mas seguram o estrago
 * quando algo der errado.
 */
class HardeningTest extends TestCase
{
    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        $payload = ['email' => 'attacker@test.local', 'password' => 'senha-errada'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', $payload)->assertStatus(401);
        }

        // 6ª tentativa no mesmo minuto/email/IP é bloqueada
        $this->postJson('/api/auth/login', $payload)->assertStatus(429);
    }

    public function test_appointment_create_page_caps_preloaded_customers(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        Customer::factory()->count(60)->create(['tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->get('/appointments/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Appointments/Create')
                ->has('customers', 50)
            );
    }

    // ===== Auditoria sênior de 29/09/2026 =====

    public function test_password_change_elsewhere_logs_out_open_sessions(): void
    {
        // Funcionário demitido com o app aberto no celular: o dono troca a senha dele
        // (ou ele é resetado) e a sessão aberta tem que cair.
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($user)->getJson('/api/customers')->assertOk();

        $user->forceFill(['password' => Hash::make('outra-senha-123')])->save();

        $this->getJson('/api/customers')->assertStatus(401);
    }

    public function test_own_password_change_keeps_the_current_session(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner', 'password' => 'senha-antiga-1']);

        $this->actingAs($user)->putJson('/api/profile/password', [
            'current_password' => 'senha-antiga-1',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertOk();

        $this->getJson('/api/customers')->assertOk();
    }

    public function test_tenant_scope_fails_closed_without_context_in_http(): void
    {
        // Rota pública nova que esquecesse de fixar o tenant: antes listava as
        // linhas de TODAS as barbearias; agora não vê nenhuma.
        Customer::factory()->count(3)->create(['tenant_id' => Tenant::factory()->create()->id]);

        Route::middleware('web')->get('/_probe/customers', fn () => ['count' => Customer::count()]);

        $this->getJson('/_probe/customers')->assertOk()->assertJsonPath('count', 0);
    }
}
