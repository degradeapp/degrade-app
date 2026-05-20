<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Tests\TestCase;

class BarberTest extends TestCase
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

    public function test_owner_can_create_barber(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/barbers', [
            'name' => 'João Barbeiro',
            'phone' => '92991234567',
            'default_commission_percentage' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'id',
                'name',
                'phone',
                'default_commission_percentage',
                'is_active',
                'user_id',
                'schedules',
                'created_at',
            ]);

        $this->assertDatabaseHas('barbers', [
            'tenant_id' => $this->tenant->id,
            'name' => 'João Barbeiro',
            'phone' => '92991234567',
        ]);
    }

    public function test_manager_can_create_barber(): void
    {
        $this->actingAs($this->manager);

        $response = $this->postJson('/barbers', [
            'name' => 'Maria Barbeira',
            'phone' => '92988776655',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('barbers', [
            'name' => 'Maria Barbeira',
        ]);
    }

    public function test_receptionist_cannot_create_barber(): void
    {
        $this->actingAs($this->receptionist);

        $response = $this->postJson('/barbers', [
            'name' => 'Test Barber',
            'phone' => '92987654321',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_barber_initializes_defaults(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/barbers', [
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $barber = Barber::find($response->json('id'));

        $this->assertTrue($barber->is_active);
        $this->assertEquals(0, $barber->default_commission_percentage);
    }

    public function test_list_barbers_only_shows_active(): void
    {
        $this->actingAs($this->owner);

        $barber1 = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Active Barber',
            'phone' => '92991111111',
            'is_active' => true,
        ]);

        $barber2 = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Deleted Barber',
            'phone' => '92992222222',
            'is_active' => true,
        ]);

        $barber2->update(['deleted_at' => now(), 'deleted_by' => $this->owner->id]);

        $response = $this->getJson('/barbers');

        $response->assertStatus(200);
        $ids = $response->json('data.*.id');
        $this->assertContains($barber1->id, $ids);
        $this->assertNotContains($barber2->id, $ids);
    }

    public function test_update_barber(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Name',
            'phone' => '92991234567',
        ]);

        $response = $this->putJson("/barbers/{$barber->id}", [
            'name' => 'Updated Name',
            'phone' => '92999999999',
            'default_commission_percentage' => 25,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('barbers', [
            'id' => $barber->id,
            'name' => 'Updated Name',
            'phone' => '92999999999',
            'default_commission_percentage' => 25,
        ]);
    }

    public function test_delete_barber_soft_deletes(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'To Delete',
            'phone' => '92991234567',
        ]);

        $response = $this->deleteJson("/barbers/{$barber->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('barbers', [
            'id' => $barber->id,
            'deleted_by' => $this->owner->id,
        ]);

        $this->assertNotNull($barber->fresh()->deleted_at);
    }

    public function test_create_schedule_for_barber(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $response = $this->putJson("/barbers/{$barber->id}/schedule/1", [
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Horário atualizado com sucesso.');

        $this->assertDatabaseHas('barber_schedules', [
            'barber_id' => $barber->id,
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
    }

    public function test_schedule_validates_start_before_end(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $response = $this->putJson("/barbers/{$barber->id}/schedule/1", [
            'start_time' => '17:00',
            'end_time' => '09:00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_time']);
    }

    public function test_schedule_unique_per_day_of_week(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $this->putJson("/barbers/{$barber->id}/schedule/1", [
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->putJson("/barbers/{$barber->id}/schedule/1", [
            'start_time' => '08:00',
            'end_time' => '18:00',
        ]);

        $schedules = $barber->schedules()->where('day_of_week', 1)->get();
        $this->assertCount(1, $schedules);
        $this->assertEquals('08:00', $schedules->first()->start_time);
    }

    public function test_create_time_off(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $tomorrow = now()->addDay()->format('Y-m-d');

        $response = $this->postJson("/barbers/{$barber->id}/time-off", [
            'date' => $tomorrow,
            'reason' => 'Médico',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'date',
                'reason',
            ]);

        $this->assertDatabaseHas('barber_time_off', [
            'barber_id' => $barber->id,
            'date' => $tomorrow,
            'reason' => 'Médico',
        ]);
    }

    public function test_delete_time_off(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $tomorrow = now()->addDay()->format('Y-m-d');

        $this->postJson("/barbers/{$barber->id}/time-off", [
            'date' => $tomorrow,
            'reason' => 'Férias',
        ]);

        $response = $this->deleteJson("/barbers/{$barber->id}/time-off/{$tomorrow}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('barber_time_off', [
            'barber_id' => $barber->id,
            'date' => $tomorrow,
        ]);
    }

    public function test_tenancy_isolation_enforced(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant A Barber',
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

        $response = $this->getJson("/barbers/{$barber->id}");

        $response->assertStatus(403);
    }

    public function test_barber_with_user_relationship(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
            'user_id' => $this->manager->id,
        ]);

        $this->assertDatabaseHas('barbers', [
            'id' => $barber->id,
            'user_id' => $this->manager->id,
        ]);

        $this->assertEquals($this->manager->id, $barber->user_id);
    }
}
