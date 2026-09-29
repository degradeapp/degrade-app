<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantSlug;
use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * O endereço do link público vai pra bio do Instagram e pro QR do balcão: precisa
 * ser legível (nome da barbearia), único e só mudar quando o dono decide.
 */
class BookingLinkSlugTest extends TestCase
{
    private function registerOwner(string $email = 'dono@test.local'): User
    {
        $this->postJson('/api/auth/register', [
            'name' => 'João Dono', 'email' => $email, 'phone' => '92991234567',
            'password' => 'password123', 'password_confirmation' => 'password123', 'terms' => true,
        ])->assertCreated();

        return User::where('email', $email)->first();
    }

    public function test_onboarding_turns_the_random_slug_into_the_business_name(): void
    {
        $owner = $this->registerOwner();
        $this->assertTrue(TenantSlug::isPlaceholder($owner->tenant->slug));

        $this->actingAs($owner)->postJson('/api/onboarding/business', [
            'name' => 'Barbearia do João', 'timezone' => 'America/Manaus',
        ])->assertOk();

        $this->assertSame('barbearia-do-joao', $owner->tenant->fresh()->slug);
    }

    public function test_same_name_gets_a_numbered_slug_including_deleted_accounts(): void
    {
        // Conta excluída ainda está na janela de 30 dias: o endereço dela fica reservado.
        $old = Tenant::factory()->create(['slug' => 'barbearia-do-joao']);
        $old->delete();

        $owner = $this->registerOwner();
        $this->actingAs($owner)->postJson('/api/onboarding/business', [
            'name' => 'Barbearia do João', 'timezone' => 'America/Manaus',
        ])->assertOk();

        $this->assertSame('barbearia-do-joao-2', $owner->tenant->fresh()->slug);
    }

    public function test_owner_changes_the_link_and_the_old_one_stops_working(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'slug' => 'antigo', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

        $this->actingAs($owner)->putJson('/api/tenant/settings', ['slug' => 'Navalha-Club'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'navalha-club');

        $this->getJson('/api/public/agendar/navalha-club')->assertOk();
        $this->getJson('/api/public/agendar/antigo')->assertNotFound();
    }

    public function test_invalid_reserved_or_taken_slugs_are_rejected(): void
    {
        Tenant::factory()->create(['slug' => 'ja-existe']);
        $tenant = Tenant::factory()->create(['status' => 'active', 'slug' => 'minha', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
        $this->actingAs($owner);

        foreach (['ja-existe', 'admin', 'ab', 'com espaço', 'acentuação', '-hifen-na-ponta-'] as $bad) {
            $this->putJson('/api/tenant/settings', ['slug' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('slug');
        }

        $this->assertSame('minha', $tenant->fresh()->slug);
    }

    public function test_manager_cannot_change_the_link(): void
    {
        $tenant = Tenant::factory()->create(['status' => 'active', 'slug' => 'da-loja', 'onboarding_completed_at' => now()]);
        app()->instance('tenant', $tenant);
        $manager = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'manager']);

        $this->actingAs($manager)->putJson('/api/tenant/settings', ['slug' => 'outro-endereco'])->assertStatus(403);

        // Mas salva o resto da tela normalmente (mandando o mesmo endereço).
        $this->putJson('/api/tenant/settings', ['name' => 'Novo Nome', 'slug' => 'da-loja'])->assertOk();
        $this->assertSame('da-loja', $tenant->fresh()->slug);
    }
}
