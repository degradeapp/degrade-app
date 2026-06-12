<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

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
}
