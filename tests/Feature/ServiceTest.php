<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private User $manager;

    private User $receptionist;

    private Barber $barber;

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

        $this->barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_owner_can_create_service(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/services', [
            'name' => 'Corte Simples',
            'description' => 'Corte de cabelo',
            'duration_minutes' => 30,
            'price' => 50.00,
            'commission_percentage' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'description',
                'price',
                'commission_percentage',
                'is_active',
                'barbers',
                'created_at',
            ]);

        $this->assertDatabaseHas('services', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Corte Simples',
        ]);
    }

    public function test_manager_can_create_service(): void
    {
        $this->actingAs($this->manager);

        $response = $this->postJson('/api/services', [
            'name' => 'Barba',
            'duration_minutes' => 20,
            'price' => 40.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('services', [
            'name' => 'Barba',
        ]);
    }

    public function test_receptionist_cannot_create_service(): void
    {
        $this->actingAs($this->receptionist);

        $response = $this->postJson('/api/services', [
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response->assertStatus(403);
    }

    public function test_create_service_fails_with_duplicate_name(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/services', [
            'name' => 'Corte Simples',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response = $this->postJson('/api/services', [
            'name' => 'Corte Simples',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_service_succeeds_with_duplicate_name_different_tenant(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/services', [
            'name' => 'Corte Simples',
            'duration_minutes' => 30,
            'price' => 50.00,
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

        $response = $this->postJson('/api/services', [
            'name' => 'Corte Simples',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response->assertStatus(201);
    }

    public function test_list_services_only_shows_active(): void
    {
        $this->actingAs($this->owner);

        $service1 = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Service',
            'duration_minutes' => 30,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $service2 = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deleted Service',
            'duration_minutes' => 30,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $service2->deleted_by = $this->owner->id;
        $service2->saveQuietly();
        $service2->delete();

        $response = $this->getJson('/api/services');

        $response->assertStatus(200);
        $ids = $response->json('data.*.id');
        $this->assertContains($service1->id, $ids);
        $this->assertNotContains($service2->id, $ids);
    }

    public function test_update_service(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response = $this->putJson("/api/services/{$service->id}", [
            'name' => 'Updated Name',
            'duration_minutes' => 45,
            'price' => 60.00,
            'commission_percentage' => 25,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Updated Name',
            'price' => 60.00,
        ]);
    }

    public function test_delete_service_soft_deletes(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'To Delete',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response = $this->deleteJson("/api/services/{$service->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'deleted_by' => $this->owner->id,
        ]);

        $this->assertNotNull($service->fresh()->deleted_at);
    }

    public function test_soft_deleted_services_not_returned_in_list(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Service',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $this->deleteJson("/api/services/{$service->id}");

        $response = $this->getJson('/api/services');
        $ids = $response->json('data.*.id');

        $this->assertNotContains($service->id, $ids);
    }

    public function test_attach_barber_to_service(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $response = $this->postJson("/api/services/{$service->id}/barbers/{$this->barber->id}", [
            'commission_percentage' => 15,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Barbeiro atribuído com sucesso.');

        $this->assertDatabaseHas('barber_service', [
            'service_id' => $service->id,
            'barber_id' => $this->barber->id,
            'commission_percentage' => 15,
        ]);
    }

    public function test_attach_barber_with_commission_override(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
            'commission_percentage' => 20,
        ]);

        $this->postJson("/api/services/{$service->id}/barbers/{$this->barber->id}", [
            'commission_percentage' => 25,
        ]);

        $pivot = $service->barbers()->find($this->barber->id)->pivot;

        $this->assertEquals(25, $pivot->commission_percentage);
    }

    public function test_detach_barber_from_service(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
        ]);

        $service->barbers()->attach($this->barber->id, ['commission_percentage' => 15]);

        $response = $this->deleteJson("/api/services/{$service->id}/barbers/{$this->barber->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('barber_service', [
            'service_id' => $service->id,
            'barber_id' => $this->barber->id,
        ]);
    }

    public function test_service_without_commission_uses_barber_default(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
            'commission_percentage' => null,
        ]);

        $this->postJson("/api/services/{$service->id}/barbers/{$this->barber->id}");

        $pivot = $service->barbers()->find($this->barber->id)->pivot;

        $this->assertNull($pivot->commission_percentage);
    }

    public function test_commission_hierarchy(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Service',
            'duration_minutes' => 30,
            'price' => 50.00,
            'commission_percentage' => 20,
        ]);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Senior Barber',
            'phone' => '92998765432',
            'default_commission_percentage' => 25,
        ]);

        $service->barbers()->attach($barber->id, ['commission_percentage' => 30]);

        $pivot = $service->barbers()->find($barber->id)->pivot;

        $this->assertEquals(30, $pivot->commission_percentage);
    }

    public function test_tenancy_isolation_enforced(): void
    {
        $this->actingAs($this->owner);

        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant A Service',
            'duration_minutes' => 30,
            'price' => 50.00,
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

        $response = $this->getJson("/api/services/{$service->id}");

        $response->assertStatus(404);
    }

    public function test_bulk_store_creates_multiple_services(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/services/bulk', [
            'services' => [
                ['name' => 'Corte Degradê', 'price' => 40],
                ['name' => 'Barba', 'price' => 30],
            ],
        ])->assertStatus(201);

        $this->assertDatabaseHas('services', ['tenant_id' => $this->tenant->id, 'name' => 'Corte Degradê', 'price' => 40]);
        $this->assertDatabaseHas('services', ['tenant_id' => $this->tenant->id, 'name' => 'Barba', 'price' => 30]);
    }

    public function test_bulk_store_updates_existing_service_price(): void
    {
        $this->actingAs($this->owner);

        Service::create(['tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 99, 'commission_percentage' => 50]);

        $this->postJson('/api/services/bulk', [
            'services' => [['name' => 'Corte', 'price' => 40]],
        ])->assertStatus(201);

        // Não duplica, ATUALIZA o preço, e preserva a comissão (não foi enviada)
        $this->assertSame(1, Service::where('tenant_id', $this->tenant->id)->where('name', 'Corte')->count());
        $service = Service::where('tenant_id', $this->tenant->id)->where('name', 'Corte')->first();
        $this->assertEquals(40, $service->price);
        $this->assertEquals(50, $service->commission_percentage);
    }

    public function test_bulk_store_requires_services(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/services/bulk', ['services' => []])->assertStatus(422);
    }
}
