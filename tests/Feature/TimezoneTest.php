<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * O horário é guardado como hora local da loja (wall-clock). Fazer a requisição
 * rodar no fuso do tenant deixa now()/casts/janelas/emissão corretos pra qualquer
 * loja, independente do fuso do servidor. Aqui o app roda Manaus (−4) por padrão.
 */
class TimezoneTest extends TestCase
{
    public function test_tenant_context_applies_tenant_timezone(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'settings' => ['timezone' => 'America/Sao_Paulo'],
        ]);

        app(TenantContext::class)->set($tenant);

        $this->assertSame('America/Sao_Paulo', date_default_timezone_get());
    }

    public function test_invalid_tenant_timezone_does_not_break_request(): void
    {
        $original = date_default_timezone_get();

        $tenant = Tenant::factory()->create([
            'settings' => ['timezone' => 'Fuso/Invalido'],
        ]);

        app(TenantContext::class)->set($tenant); // não pode lançar

        // Fuso inválido é ignorado: continua no padrão do app.
        $this->assertSame($original, date_default_timezone_get());
    }

    public function test_appointment_time_emitted_in_tenant_timezone(): void
    {
        // App roda Manaus (−4); a loja é São Paulo (−3). O horário tem que sair −3.
        Carbon::setTestNow(Carbon::parse('2026-06-15 08:00:00', 'America/Sao_Paulo'));

        $tenant = Tenant::factory()->create([
            'status' => 'active',
            'onboarding_completed_at' => now(),
            'settings' => ['timezone' => 'America/Sao_Paulo'],
        ]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $barber = Barber::create(['tenant_id' => $tenant->id, 'name' => 'B', 'is_active' => true]);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'C']);

        // 20:00 local da loja, claramente no futuro → entra em "próximos".
        Appointment::create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'status' => AppointmentStatus::scheduled->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => '2026-06-15 20:00:00',
            'ends_at' => '2026-06-15 20:30:00',
            'total_price' => 50,
        ]);

        $this->actingAs($owner)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tenant.timezone', 'America/Sao_Paulo')
                ->has('upcoming_appointments', 1)
                ->where('upcoming_appointments.0.starts_at', fn ($iso) => str_contains((string) $iso, '-03:00'))
                ->etc()
            );

        Carbon::setTestNow();
    }
}
