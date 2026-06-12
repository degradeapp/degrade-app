<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * As PÁGINAS (web/Inertia) são gateadas por papel: navegar direto numa URL sem
 * permissão redireciona pro /403 limpo, em vez de abrir uma tela quebrada.
 */
class PageAccessTest extends TestCase
{
    private function tenant(): Tenant
    {
        $t = Tenant::factory()->create(['status' => 'active', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $t);

        return $t;
    }

    public function test_receptionist_redirected_from_management_pages(): void
    {
        $t = $this->tenant();
        $recep = User::factory()->create(['tenant_id' => $t->id, 'role' => 'receptionist']);

        foreach (['/reports', '/commissions', '/services', '/barbers', '/settings/business', '/settings/team', '/billing'] as $url) {
            $this->actingAs($recep)->get($url)->assertRedirect('/403');
        }
    }

    public function test_receptionist_reaches_front_desk_pages(): void
    {
        $t = $this->tenant();
        $recep = User::factory()->create(['tenant_id' => $t->id, 'role' => 'receptionist']);

        $this->actingAs($recep)->get('/')->assertOk();
        $this->actingAs($recep)->get('/appointments')->assertOk();
        $this->actingAs($recep)->get('/customers')->assertOk();
        $this->actingAs($recep)->get('/settings')->assertOk();
        $this->actingAs($recep)->get('/settings/profile')->assertOk();
    }

    public function test_barber_reaches_front_desk_but_not_management(): void
    {
        $t = $this->tenant();
        $barber = User::factory()->create(['tenant_id' => $t->id, 'role' => 'barber']);

        // Barbeiro é operacional (como a recepção): vê a agenda, os clientes e seu perfil.
        $this->actingAs($barber)->get('/')->assertOk();
        $this->actingAs($barber)->get('/appointments')->assertOk();
        $this->actingAs($barber)->get('/customers')->assertOk();
        $this->actingAs($barber)->get('/settings')->assertOk();
        $this->actingAs($barber)->get('/settings/profile')->assertOk();

        // ...mas gestão, acessos e cobrança são fechados pra ele.
        foreach (['/reports', '/commissions', '/services', '/barbers', '/settings/business', '/settings/team', '/billing'] as $url) {
            $this->actingAs($barber)->get($url)->assertRedirect('/403');
        }
    }

    public function test_manager_blocked_from_owner_only_pages(): void
    {
        $t = $this->tenant();
        $manager = User::factory()->create(['tenant_id' => $t->id, 'role' => 'manager']);

        // Gerente entra no balcão, em toda a gestão e na caixa de entrada do WhatsApp...
        foreach (['/', '/appointments', '/customers', '/reports', '/commissions', '/barbers', '/services', '/settings/business', '/settings/hours', '/settings/notifications', '/whatsapp'] as $url) {
            $this->actingAs($manager)->get($url)->assertOk();
        }
        // ...mas Acessos, Cobrança e o SETUP do WhatsApp (credenciais) são só do dono.
        $this->actingAs($manager)->get('/settings/team')->assertRedirect('/403');
        $this->actingAs($manager)->get('/billing')->assertRedirect('/403');
        $this->actingAs($manager)->get('/whatsapp/setup')->assertRedirect('/403');
    }

    public function test_legal_pages_are_public(): void
    {
        // Termos e Política devem abrir com ou sem login (linkados no cadastro).
        $this->get('/terms')->assertOk();
        $this->get('/privacy')->assertOk();

        $t = $this->tenant();
        $u = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);
        $this->actingAs($u)->get('/terms')->assertOk();
        $this->actingAs($u)->get('/privacy')->assertOk();
    }

    public function test_owner_reaches_everything(): void
    {
        $t = $this->tenant();
        $owner = User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);

        foreach (['/reports', '/commissions', '/barbers', '/settings/business', '/settings/team', '/billing'] as $url) {
            $this->actingAs($owner)->get($url)->assertOk();
        }
    }
}
