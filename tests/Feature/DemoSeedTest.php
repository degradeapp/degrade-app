<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * demo:seed é a ferramenta de venda presencial (produto vivo na frente do
 * cliente). Este smoke garante que o comando não apodrece com refatorações.
 */
class DemoSeedTest extends TestCase
{
    public function test_demo_seed_creates_a_living_tenant(): void
    {
        $this->artisan('demo:seed')->assertSuccessful();

        $tenant = Tenant::where('slug', 'demo-degrade')->firstOrFail();
        $this->assertNotNull($tenant->onboarding_completed_at);

        $count = fn (string $table) => DB::table($table)->where('tenant_id', $tenant->id)->count();

        $this->assertSame(3, $count('barbers'));
        $this->assertSame(6, $count('services'));
        $this->assertSame(10, $count('customers'));
        $this->assertGreaterThan(0, $count('commissions'));
        $this->assertGreaterThan(
            0,
            DB::table('appointments')->where('tenant_id', $tenant->id)->where('status', 'completed')->count()
        );

        // Idempotente: rodar de novo não duplica nada.
        $this->artisan('demo:seed')->assertSuccessful();
        $this->assertSame(3, $count('barbers'));
    }
}
