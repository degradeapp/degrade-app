<?php

namespace Tests\Feature;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
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

        $response = $this->postJson('/api/barbers', [
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

        $response = $this->postJson('/api/barbers', [
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

        $response = $this->postJson('/api/barbers', [
            'name' => 'Test Barber',
            'phone' => '92987654321',
        ]);

        $response->assertStatus(403);
    }

    public function test_create_barber_initializes_defaults(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/barbers', [
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

        $barber2->deleted_by = $this->owner->id;
        $barber2->saveQuietly();
        $barber2->delete();

        $response = $this->getJson('/api/barbers');

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

        $response = $this->putJson("/api/barbers/{$barber->id}", [
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

        $response = $this->deleteJson("/api/barbers/{$barber->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('barbers', [
            'id' => $barber->id,
            'deleted_by' => $this->owner->id,
        ]);

        $this->assertNotNull($barber->fresh()->deleted_at);
    }

    public function test_barber_with_history_can_be_deleted_keeping_history(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Com Histórico',
            'phone' => '92991234567',
        ]);
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente']);
        $appt = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'barber_id' => $barber->id,
            'status' => AppointmentStatus::completed->value,
            'source' => AppointmentSource::walk_in,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addMinutes(30),
            'total_price' => 50,
        ]);

        // O dono CONSEGUE excluir mesmo com histórico (soft-delete).
        $this->deleteJson("/api/barbers/{$barber->id}")->assertNoContent();

        // Soft-delete: a linha fica guardada e o atendimento antigo ainda aponta pra ela.
        $this->assertSoftDeleted('barbers', ['id' => $barber->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appt->id, 'barber_id' => $barber->id]);
    }

    public function test_owner_barber_profile_cannot_be_deleted(): void
    {
        $this->actingAs($this->owner);

        // O dono é barbeiro por padrão; remover o próprio perfil criaria um beco
        // sem saída (sem perfil ele não pode se agendar). Bloqueado — desative em vez disso.
        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => 'Dono Barbeiro',
            'phone' => '92991234567',
        ]);

        $this->deleteJson("/api/barbers/{$barber->id}")->assertStatus(422);

        $this->assertNull($barber->fresh()->deleted_at);
    }

    public function test_barber_photo_syncs_to_linked_user_avatar(): void
    {
        // A foto do barbeiro-com-login É a foto de perfil dele: subir pela Equipe
        // reflete em "Meu perfil" (e remover também).
        \Illuminate\Support\Facades\Storage::fake('public');
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => 'Dono Barbeiro',
            'phone' => '92991234567',
        ]);

        $this->postJson("/api/barbers/{$barber->id}/photo", [
            'photo' => \Illuminate\Http\UploadedFile::fake()->create('foto.jpg', 200, 'image/jpeg'),
        ])->assertOk();

        $barber->refresh();
        $owner = User::find($this->owner->id);
        $this->assertNotNull($owner->avatar_path);
        $this->assertSame($barber->photo_path, $owner->avatar_path);

        $this->deleteJson("/api/barbers/{$barber->id}/photo")->assertOk();
        $this->assertNull(User::find($this->owner->id)->avatar_path);
    }

    public function test_create_schedule_for_barber(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $response = $this->putJson("/api/barbers/{$barber->id}/schedule/1", [
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

        $response = $this->putJson("/api/barbers/{$barber->id}/schedule/1", [
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

        $this->putJson("/api/barbers/{$barber->id}/schedule/1", [
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);

        $this->putJson("/api/barbers/{$barber->id}/schedule/1", [
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

        $response = $this->postJson("/api/barbers/{$barber->id}/time-off", [
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

        $this->postJson("/api/barbers/{$barber->id}/time-off", [
            'date' => $tomorrow,
            'reason' => 'Férias',
        ]);

        $response = $this->deleteJson("/api/barbers/{$barber->id}/time-off/{$tomorrow}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('barber_time_off', [
            'barber_id' => $barber->id,
            'date' => $tomorrow,
        ]);
    }

    public function test_create_time_off_with_period(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $start = now()->addDay()->format('Y-m-d');
        $end = now()->addDays(5)->format('Y-m-d');

        $this->postJson("/api/barbers/{$barber->id}/time-off", [
            'date' => $start,
            'end_date' => $end,
            'reason' => 'Férias',
        ])->assertStatus(201)->assertJsonStructure(['date', 'end_date', 'reason']);

        $this->assertDatabaseHas('barber_time_off', [
            'barber_id' => $barber->id,
            'date' => $start,
            'end_date' => $end,
        ]);
    }

    public function test_time_off_end_before_start_is_rejected_in_portuguese(): void
    {
        $this->actingAs($this->owner);

        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Barber',
            'phone' => '92991234567',
        ]);

        $response = $this->postJson("/api/barbers/{$barber->id}/time-off", [
            'date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'), // fim antes do início
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['end_date']);

        // Mensagem em pt-BR, sem o prefixo cru "validation."
        $msg = $response->json('errors.end_date.0');
        $this->assertStringNotContainsString('validation.', $msg);
        $this->assertStringContainsString('início', $msg);
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

        $response = $this->getJson("/api/barbers/{$barber->id}");

        $response->assertStatus(404);
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

    public function test_create_barber_rejects_name_over_100_chars(): void
    {
        $this->actingAs($this->owner);

        $this->postJson('/api/barbers', [
            'name' => str_repeat('a', 101),
            'phone' => '92999998888',
            'default_commission_percentage' => 50,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_staff_limit_blocks_new_barber(): void
    {
        $this->actingAs($this->owner);

        // setUp já tem 3 funcionários (dono, gerente, recepção). Sem plano → limite Barbearia (4).
        // 1º barbeiro ocupa a 4ª (última) vaga.
        $this->postJson('/api/barbers', [
            'name' => 'B1', 'phone' => '92991234567', 'default_commission_percentage' => 20,
        ])->assertStatus(201);

        // 5º funcionário → bloqueado
        $this->postJson('/api/barbers', [
            'name' => 'B2', 'phone' => '92991230002', 'default_commission_percentage' => 20,
        ])->assertStatus(403);
    }

    public function test_solo_plan_allows_only_the_owner(): void
    {
        // Cenário solo real: só o dono na barbearia.
        $this->manager->delete();
        $this->receptionist->delete();
        $this->tenant->update(['plan' => 'solo']); // limite 1 funcionário no total

        $this->actingAs($this->owner);

        // Dono = 1 funcionário = limite. Qualquer barbeiro a mais → bloqueado.
        $this->postJson('/api/barbers', [
            'name' => 'Extra', 'phone' => '92991234567', 'default_commission_percentage' => 20,
        ])->assertStatus(403);
    }

    public function test_new_barber_gets_default_weekly_schedule(): void
    {
        $this->actingAs($this->owner);

        $response = $this->postJson('/api/barbers', [
            'name' => 'Com Agenda',
            'phone' => '92991234567',
            'default_commission_percentage' => 20,
        ]);

        $response->assertStatus(201);

        // Sem horário de funcionamento definido → fallback Seg–Sáb (6 dias)
        $this->assertSame(6, Barber::find($response->json('id'))->schedules()->count());
    }
}
