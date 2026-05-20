<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_register_creates_tenant_and_owner_in_transaction(): void
    {
        $response = $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'test-barbershop',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'email',
                'role',
                'role_label',
                'is_active',
                'tenant' => [
                    'id',
                    'name',
                    'slug',
                    'status',
                    'trial_ends_at',
                ],
            ]);

        $this->assertDatabaseHas('tenants', [
            'name' => 'Test Barbershop',
            'slug' => 'test-barbershop',
            'status' => 'trial',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'owner@example.com',
            'role' => 'owner',
            'is_active' => true,
        ]);

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user->tenant);
        $this->assertEquals('Test Barbershop', $user->tenant->name);
    }

    public function test_register_trial_period_is_14_days(): void
    {
        $beforeRegister = now();
        $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'test-barbershop',
        ]);

        $user = User::where('email', 'owner@example.com')->first();
        $trialEndsAt = $user->tenant->trial_ends_at;

        $this->assertNotNull($trialEndsAt);
        $this->assertTrue($trialEndsAt->diffInDays($beforeRegister) === 14);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop 1',
            'tenant_slug' => 'test-barbershop-1',
        ]);

        $response = $this->postJson('/auth/register', [
            'name' => 'Jane Owner',
            'email' => 'duplicate@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop 2',
            'tenant_slug' => 'test-barbershop-2',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_fails_with_duplicate_slug(): void
    {
        $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop 1',
            'tenant_slug' => 'duplicate-slug',
        ]);

        $response = $this->postJson('/auth/register', [
            'name' => 'Jane Owner',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop 2',
            'tenant_slug' => 'duplicate-slug',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_slug']);
    }

    public function test_register_fails_with_invalid_slug_format(): void
    {
        $response = $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'Invalid_Slug With Spaces',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tenant_slug']);
    }

    public function test_login_returns_user_and_token(): void
    {
        $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'test-barbershop',
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'role_label',
                    'is_active',
                    'tenant',
                ],
                'token',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'test-barbershop',
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Credenciais inválidas.');
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_logout_invalidates_token(): void
    {
        $registerResponse = $this->postJson('/auth/register', [
            'name' => 'John Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'tenant_name' => 'Test Barbershop',
            'tenant_slug' => 'test-barbershop',
        ]);

        $loginResponse = $this->postJson('/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password123',
        ]);

        $token = $loginResponse->json('token');

        $response = $this->withToken($token)->postJson('/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Logout realizado com sucesso.');

        $invalidTokenResponse = $this->withToken($token)->getJson('/auth/logout');
        $this->assertTrue(
            $invalidTokenResponse->status() === 401 || $invalidTokenResponse->status() === 403
        );
    }
}
