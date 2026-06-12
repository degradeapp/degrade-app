<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
        ]);
    }

    // Auth endpoints (token API)
    public function test_login_endpoint()
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['user', 'token']);
    }

    public function test_register_endpoint()
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '92991234567',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'email', 'role', 'tenant']);
    }

    // Customer endpoints
    public function test_list_customers()
    {
        Customer::factory(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->getJson('/api/customers');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['*' => ['id', 'name', 'phone']]]);
        $response->assertJsonCount(3, 'data');
    }

    public function test_create_customer()
    {
        $response = $this->actingAs($this->user)->postJson('/api/customers', [
            'name' => 'João Silva',
            'phone' => '(92) 99999-1111',
            'email' => 'joao@example.com',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['id', 'name', 'phone', 'email']);
        $this->assertDatabaseHas('customers', ['name' => 'João Silva']);
    }

    public function test_update_customer()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->putJson("/api/customers/{$customer->id}", [
            'name' => 'Updated Name',
            'phone' => '(92) 99999-2222',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['name' => 'Updated Name']);
    }

    public function test_delete_customer()
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // Dashboard (Inertia page)
    public function test_dashboard_page_loads_for_authenticated_user()
    {
        $response = $this->actingAs($this->user)->get('/');

        $response->assertStatus(200);
    }

    // Multi-tenancy isolation test
    public function test_customer_isolation_between_tenants()
    {
        $tenant2 = Tenant::factory()->create();
        $user2 = User::factory()->create(['tenant_id' => $tenant2->id, 'role' => 'owner']);

        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Customer 1']);
        Customer::factory()->create(['tenant_id' => $tenant2->id, 'name' => 'Customer 2']);

        $response1 = $this->actingAs($this->user)->getJson('/api/customers');
        $this->assertEquals(1, count($response1['data']));
        $this->assertEquals('Customer 1', $response1['data'][0]['name']);

        $response2 = $this->actingAs($user2)->getJson('/api/customers');
        $this->assertEquals(1, count($response2['data']));
        $this->assertEquals('Customer 2', $response2['data'][0]['name']);
    }

    // Web routes
    public function test_login_page()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertViewIs('app');
    }

    public function test_dashboard_page_requires_auth()
    {
        $response = $this->get('/');
        $response->assertStatus(302); // Redirect to login
    }

    public function test_authenticated_user_can_access_dashboard()
    {
        $response = $this->actingAs($this->user)->get('/');
        $response->assertStatus(200);
    }
}
