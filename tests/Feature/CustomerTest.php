<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private User $manager;

    private User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Barbershop',
            'slug' => 'test-barbershop',
            'status' => 'active',
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        $this->manager = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Manager',
            'email' => 'manager@test.local',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $this->receptionist = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Receptionist',
            'email' => 'receptionist@test.local',
            'password' => 'password',
            'role' => 'receptionist',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_owner_can_create_customer(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/customers', [
            'name' => 'João Silva',
            'phone' => '92991234567',
            'email' => 'joao@example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'phone',
                'email',
                'is_active',
                'total_visits',
                'total_spent',
                'last_visit_at',
                'created_at',
            ]);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'João Silva',
            'phone' => '92991234567',
        ]);
    }

    public function test_manager_can_create_customer(): void
    {
        $this->actingAs($this->manager);

        $response = $this->postJson('/api/customers', [
            'name' => 'Maria Santos',
            'phone' => '92988776655',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', [
            'name' => 'Maria Santos',
            'phone' => '92988776655',
        ]);
    }

    public function test_receptionist_can_create_customer(): void
    {
        // Recepcionista é front-desk: precisa cadastrar walk-in para agendar.
        $this->actingAs($this->receptionist);

        $response = $this->postJson('/api/customers', [
            'name' => 'Test Customer',
            'phone' => '92987654321',
        ]);

        $response->assertStatus(201);
    }

    public function test_create_customer_fails_with_duplicate_phone(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', [
            'name' => 'First Customer',
            'phone' => '92991234567',
        ]);

        $response = $this->postJson('/api/customers', [
            'name' => 'Second Customer',
            'phone' => '92991234567',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_customer_succeeds_with_duplicate_phone_different_tenant(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', [
            'name' => 'First Customer',
            'phone' => '92991234567',
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Other Barbershop',
            'slug' => 'other-barbershop',
            'status' => 'active',
        ]);

        $otherOwner = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Owner',
            'email' => 'other@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        app()->instance('tenant', $otherTenant);
        $this->actingAs($otherOwner);

        $response = $this->postJson('/api/customers', [
            'name' => 'Other Customer',
            'phone' => '92991234567',
        ]);

        $response->assertStatus(201);
    }

    public function test_list_customers_only_shows_active(): void
    {
        $this->actingAs($this->owner);

        $customer1 = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Customer',
            'phone' => '92991111111',
            'is_active' => true,
        ]);

        $customer2 = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deleted Customer',
            'phone' => '92992222222',
            'is_active' => true,
        ]);

        $customer2->deleted_by = $this->owner->id;
        $customer2->saveQuietly();
        $customer2->delete();

        $response = $this->getJson('/api/customers');

        $response->assertStatus(200);
        $ids = $response->json('data.*.id');
        $this->assertContains($customer1->id, $ids);
        $this->assertNotContains($customer2->id, $ids);
    }

    public function test_update_customer(): void
    {
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'phone' => '92991234567',
        ]);

        $response = $this->putJson("/api/customers/{$customer->id}", [
            'name' => 'Updated Name',
            'phone' => '92999999999',
            'email' => 'updated@example.com',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Name',
            'phone' => '92999999999',
        ]);
    }

    public function test_delete_customer_soft_deletes(): void
    {
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'To Delete',
            'phone' => '92991234567',
        ]);

        $response = $this->deleteJson("/api/customers/{$customer->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'deleted_by' => $this->owner->id,
        ]);

        $this->assertNotNull($customer->fresh()->deleted_at);
    }

    public function test_soft_deleted_customers_not_returned_in_list(): void
    {
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Customer',
            'phone' => '92991234567',
        ]);

        $this->deleteJson("/api/customers/{$customer->id}");

        $response = $this->getJson('/api/customers');
        $ids = $response->json('data.*.id');

        $this->assertNotContains($customer->id, $ids);
    }

    public function test_customer_creation_initializes_statistics(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/customers', [
            'name' => 'Test Customer',
            'phone' => '92991234567',
        ]);

        $customer = Customer::find($response->json('id'));

        $this->assertEquals(0, $customer->total_visits);
        $this->assertEquals(0, $customer->total_spent);
        $this->assertNull($customer->last_visit_at);
    }

    public function test_tenancy_isolation_enforced(): void
    {
        $this->actingAs($this->owner);

        $customer = Customer::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant A Customer',
            'phone' => '92991234567',
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Other Barbershop',
            'slug' => 'other-barbershop',
            'status' => 'active',
        ]);

        $otherOwner = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other Owner',
            'email' => 'other@test.local',
            'password' => 'password',
            'role' => 'owner',
        ]);

        app()->instance('tenant', $otherTenant);
        $this->actingAs($otherOwner);

        $response = $this->getJson("/api/customers/{$customer->id}");

        $response->assertStatus(404);
    }

    public function test_create_customer_rejects_incomplete_phone(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', ['name' => 'Fulano', 'phone' => '(92) 992'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_customer_rejects_phone_without_ddd(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', ['name' => 'Fulano', 'phone' => '99999-1111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_customer_rejects_10_digit_landline(): void
    {
        $this->actingAs($this->owner);

        // 10 dígitos (DDD + 8) — só aceitamos celular de 11 dígitos
        $this->postJson('/api/customers', ['name' => 'Fulano', 'phone' => '(92) 3333-4444'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_create_customer_without_phone_succeeds(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', ['name' => 'Sem Telefone'])
            ->assertStatus(201);

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Sem Telefone',
            'phone' => null,
        ]);
    }

    public function test_multiple_customers_without_phone_are_allowed(): void
    {
        $this->actingAs($this->owner);

        // Telefone nulo não dispara o índice único (vários clientes sem número são OK)
        $this->postJson('/api/customers', ['name' => 'Cliente A'])->assertStatus(201);
        $this->postJson('/api/customers', ['name' => 'Cliente B'])->assertStatus(201);
    }

    public function test_create_customer_normalizes_formatted_phone_to_digits(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', ['name' => 'Carlos', 'phone' => '(92) 99999-1111'])
            ->assertStatus(201);

        // Armazenado só com dígitos → uniqueness e busca ficam consistentes
        $this->assertDatabaseHas('customers', [
            'tenant_id' => $this->tenant->id,
            'phone' => '92999991111',
        ]);
    }

    public function test_formatted_and_raw_phone_count_as_duplicate(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/customers', ['name' => 'A', 'phone' => '92999991111'])->assertStatus(201);

        // Mesmo número, formatado diferente → deve bloquear como duplicado
        $this->postJson('/api/customers', ['name' => 'B', 'phone' => '(92) 99999-1111'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);
    }
}
