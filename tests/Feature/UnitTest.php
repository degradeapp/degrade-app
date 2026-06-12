<?php

namespace Tests\Feature;

use App\Enums\BillingPlan;
use App\Modules\Auth\Actions\RegisterTenantOwner;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Unit\Models\Unit;
use Tests\TestCase;

/**
 * Fundação do plano Rede (multiunidade). Segurança: unidade vive SEMPRE dentro de
 * um tenant e nunca vaza entre tenants. Multiunidade só é liberada no plano Rede.
 */
class UnitTest extends TestCase
{
    public function test_registration_creates_default_unit_and_links_owner_barber(): void
    {
        $owner = app(RegisterTenantOwner::class)('Dono Teste', 'dono@teste.com', 'password', '92999990000');
        $tenant = $owner->tenant;
        app()->instance('tenant', $tenant);

        // Toda barbearia nasce com exatamente 1 unidade.
        $this->assertSame(1, $tenant->units()->count());
        $unit = $tenant->units()->first();
        $this->assertSame('Unidade principal', $unit->name);

        // O barbeiro do dono nasce vinculado à unidade principal.
        $barber = $owner->barber;
        $this->assertNotNull($barber);
        $this->assertSame($unit->id, $barber->unit_id);

        // Dono vê todas as unidades (sem unidade fixa).
        $this->assertNull($owner->fresh()->unit_id);
    }

    public function test_can_add_unit_respects_plan(): void
    {
        // Rede: 1 unidade de até 10 → pode adicionar mais.
        $rede = Tenant::factory()->create(['plan' => BillingPlan::rede->value]);
        app()->instance('tenant', $rede);
        Unit::create(['tenant_id' => $rede->id, 'name' => 'U1', 'is_active' => true]);
        $this->assertTrue($rede->canAddUnit());

        // Barbearia: 1 unidade de 1 → não pode multiunidade.
        $barbearia = Tenant::factory()->create(['plan' => BillingPlan::barbearia->value]);
        app()->instance('tenant', $barbearia);
        Unit::create(['tenant_id' => $barbearia->id, 'name' => 'U1', 'is_active' => true]);
        $this->assertFalse($barbearia->canAddUnit());

        // Sem plano (trial) cai no limite Barbearia (1) → não libera multiunidade antes de assinar.
        $trial = Tenant::factory()->create(['plan' => null]);
        app()->instance('tenant', $trial);
        Unit::create(['tenant_id' => $trial->id, 'name' => 'U1', 'is_active' => true]);
        $this->assertFalse($trial->canAddUnit());
    }

    public function test_units_are_tenant_isolated(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        Unit::create(['tenant_id' => $a->id, 'name' => 'A1', 'is_active' => true]);
        Unit::create(['tenant_id' => $b->id, 'name' => 'B1', 'is_active' => true]);

        // No contexto do tenant A, o escopo global só enxerga a unidade de A.
        app()->instance('tenant', $a);
        $this->assertSame(1, Unit::count());
        $this->assertSame('A1', Unit::first()->name);
    }
}
