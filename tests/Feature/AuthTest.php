<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    /** Payload do novo cadastro: DONO (pessoa) + telefone. Barbearia vem no onboarding. */
    private function registerPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'João Dono',
            'email' => 'owner@example.com',
            'phone' => '92991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], $overrides);
    }

    public function test_register_creates_tenant_and_owner_in_transaction(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id', 'name', 'email', 'role', 'role_label', 'is_active',
                'tenant' => ['id', 'name', 'slug', 'status', 'trial_ends_at'],
            ]);

        // Tenant criado com nome placeholder (definido de verdade no onboarding)
        $this->assertDatabaseHas('tenants', [
            'name' => 'Minha Barbearia',
            'status' => 'trial',
        ]);

        // O nome informado é o do DONO, não da barbearia
        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'name' => 'João Dono',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user->tenant);
        $this->assertSame('João Dono', $user->name);
    }

    public function test_register_creates_owner_barber_profile(): void
    {
        // O dono já entra como barbeiro da equipe (atende clientes): o card dele
        // precisa existir desde o cadastro, sem precisar "adicionar a si mesmo".
        $this->postJson('/api/auth/register', $this->registerPayload())->assertStatus(201);

        $user = User::where('email', 'owner@example.com')->first();
        $barber = Barber::where('user_id', $user->id)->first();

        $this->assertNotNull($barber, 'o dono deve ter um perfil de barbeiro');
        $this->assertSame('João Dono', $barber->name);
        $this->assertSame('92991234567', $barber->phone);
        $this->assertTrue((bool) $barber->is_active);
        // Dono fica com 100% do próprio serviço por padrão.
        $this->assertEquals(100.0, (float) $barber->default_commission_percentage);
    }

    public function test_register_trial_period_is_14_days(): void
    {
        $beforeRegister = now();
        $this->postJson('/api/auth/register', $this->registerPayload());

        $user = User::where('email', 'owner@example.com')->first();
        $trialEndsAt = $user->tenant->trial_ends_at;

        $this->assertNotNull($trialEndsAt);
        $this->assertEquals(14, abs((int) round($trialEndsAt->diffInDays($beforeRegister))));
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'dup@example.com']));

        $response = $this->postJson('/api/auth/register', $this->registerPayload([
            'email' => 'dup@example.com',
            'name' => 'Outro Dono',
            'phone' => '92988887777',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_register_rejects_incomplete_phone(): void
    {
        $response = $this->postJson('/api/auth/register', $this->registerPayload(['phone' => '(92) 992']));

        $response->assertStatus(422)->assertJsonValidationErrors(['phone']);
    }

    public function test_two_registrations_get_unique_slugs(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'a@example.com']))->assertStatus(201);
        $this->postJson('/api/auth/register', $this->registerPayload(['email' => 'b@example.com', 'phone' => '92988887777']))->assertStatus(201);

        // Mesmo nome de barbearia não existe no cadastro — slugs são aleatórios e únicos
        $this->assertSame(2, Tenant::query()->distinct()->count('slug'));
    }

    public function test_login_returns_user_and_token(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload());

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role', 'role_label', 'is_active', 'tenant'],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload());

        $response = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        // Resposta genérica 401 — não revela se o email existe.
        $response->assertStatus(401)->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_logout_invalidates_token(): void
    {
        $this->postJson('/api/auth/register', $this->registerPayload());

        $token = $this->postJson('/api/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ])->json('token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
