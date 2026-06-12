<?php

namespace Tests\Feature;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'owner',
            'password' => 'senha-atual',
        ]);
    }

    // ---- Tenant settings ----

    public function test_get_tenant_settings_returns_defaults(): void
    {
        $data = $this->actingAs($this->owner)
            ->getJson('/api/tenant/settings')
            ->assertOk()
            ->json('data');

        $this->assertSame('America/Manaus', $data['timezone']);
        $this->assertSame(50, $data['default_commission_percentage']);
        $this->assertSame(24, $data['cancellation_policy_hours']);
    }

    public function test_update_tenant_settings_persists(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/tenant/settings', [
                'timezone' => 'America/Bahia',
                'cancellation_policy_hours' => 48,
                'default_commission_percentage' => 25,
            ])
            ->assertOk();

        $tenant = Tenant::find($this->tenant->id);
        $this->assertSame('America/Bahia', $tenant->setting('timezone'));
        $this->assertSame(48, $tenant->setting('cancellation_policy_hours'));
        $this->assertEquals(25.0, $tenant->setting('financial.default_commission_percentage'));
    }

    public function test_update_business_hours_persists(): void
    {
        $hours = [
            ['day_of_week' => 0, 'closed' => true, 'start_time' => null, 'end_time' => null],
            ['day_of_week' => 1, 'closed' => false, 'start_time' => '08:00', 'end_time' => '20:00'],
        ];

        $this->actingAs($this->owner)
            ->putJson('/api/tenant/business-hours', ['business_hours' => $hours])
            ->assertOk();

        $this->assertCount(2, Tenant::find($this->tenant->id)->setting('business_hours'));
    }

    public function test_tenant_settings_forbidden_for_receptionist(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)->getJson('/api/tenant/settings')->assertStatus(403);
    }

    // ---- Profile ----

    public function test_update_profile_name(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/profile', ['name' => 'Novo Nome'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Novo Nome');
    }

    public function test_profile_syncs_name_and_phone_to_linked_barber(): void
    {
        // Dono é a mesma pessoa que o barbeiro: editar em "Meu perfil" propaga pra Equipe.
        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => 'Antigo',
            'phone' => '92991110000',
        ]);

        $this->actingAs($this->owner)
            ->putJson('/api/profile', ['name' => 'Giovane Novo', 'phone' => '(92) 99999-8888'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Giovane Novo')
            ->assertJsonPath('data.phone', '92999998888')
            ->assertJsonPath('data.has_barber', true);

        $barber->refresh();
        $this->assertSame('Giovane Novo', $barber->name);
        $this->assertSame('92999998888', $barber->phone);
    }

    public function test_update_profile_never_changes_password(): void
    {
        // Mandar campos de senha no endpoint de perfil NÃO altera a senha (são endpoints
        // separados). Antes, esses campos eram silenciosamente ignorados e dava "ok".
        $this->actingAs($this->owner)
            ->putJson('/api/profile', [
                'name' => 'Novo Nome',
                'current_password' => 'senha-atual',
                'password' => 'tentando-trocar',
                'password_confirmation' => 'tentando-trocar',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('senha-atual', User::find($this->owner->id)->password));
    }

    public function test_update_password_requires_correct_current(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/profile/password', [
                'current_password' => 'errada',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        // Senha intacta.
        $this->assertTrue(Hash::check('senha-atual', User::find($this->owner->id)->password));
    }

    public function test_update_password_rejects_empty_or_unconfirmed(): void
    {
        // Tudo vazio → 422 (nada de no-op "atualizado").
        $this->actingAs($this->owner)
            ->putJson('/api/profile/password', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'password']);

        // Confirmação divergente → 422.
        $this->actingAs($this->owner)
            ->putJson('/api/profile/password', [
                'current_password' => 'senha-atual',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'diferente-456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_update_password_rejects_same_as_current(): void
    {
        // Senha atual correta, mas a "nova" é igual à atual → 422 (não faz sentido trocar).
        $this->actingAs($this->owner)
            ->putJson('/api/profile/password', [
                'current_password' => 'senha-atual',
                'password' => 'senha-atual',
                'password_confirmation' => 'senha-atual',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');

        // Senha intacta.
        $this->assertTrue(Hash::check('senha-atual', User::find($this->owner->id)->password));
    }

    public function test_update_password_succeeds_with_correct_current(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/profile/password', [
                'current_password' => 'senha-atual',
                'password' => 'nova-senha-123',
                'password_confirmation' => 'nova-senha-123',
            ])
            ->assertOk();

        $this->assertTrue(Hash::check('nova-senha-123', User::find($this->owner->id)->password));
    }

    // ---- Avatar / foto ----

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $data = $this->actingAs($this->owner)
            ->postJson('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('me.jpg', 200, 'image/jpeg'),
            ])
            ->assertOk()
            ->json('data');

        $this->assertNotNull($data['avatar_url']);
        $path = User::find($this->owner->id)->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->actingAs($this->owner)
            ->deleteJson('/api/profile/avatar')
            ->assertOk()
            ->assertJsonPath('data.avatar_url', null);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_avatar_rejects_non_image(): void
    {
        Storage::fake('public');

        $this->actingAs($this->owner)
            ->postJson('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');
    }

    public function test_owner_can_upload_tenant_logo(): void
    {
        Storage::fake('public');

        $data = $this->actingAs($this->owner)
            ->postJson('/api/tenant/logo', [
                'logo' => UploadedFile::fake()->create('logo.png', 200, 'image/png'),
            ])
            ->assertOk()
            ->json('data');

        $this->assertNotNull($data['logo_url']);
        $this->assertNotNull(Tenant::find($this->tenant->id)->logo_path);
    }

    public function test_avatar_syncs_to_linked_barber(): void
    {
        // Dono = barbeiro: a foto é uma só. Subir o avatar no perfil reflete na Equipe.
        Storage::fake('public');
        $barber = Barber::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->owner->id,
            'name' => 'Eu',
        ]);

        $this->actingAs($this->owner)
            ->postJson('/api/profile/avatar', [
                'avatar' => UploadedFile::fake()->create('me.jpg', 200, 'image/jpeg'),
            ])
            ->assertOk();

        $barber->refresh();
        $owner = User::find($this->owner->id);
        $this->assertNotNull($barber->photo_path);
        $this->assertSame($owner->avatar_path, $barber->photo_path);

        // Remover o avatar também limpa a foto da equipe.
        $this->actingAs($this->owner)->deleteJson('/api/profile/avatar')->assertOk();
        $this->assertNull($barber->refresh()->photo_path);
    }

    // ---- Excluir conta ----

    public function test_owner_can_delete_account(): void
    {
        $original = $this->owner->email;

        $this->actingAs($this->owner)
            ->deleteJson('/api/account', ['current_password' => 'senha-atual'])
            ->assertOk()
            ->assertJsonPath('redirect', '/login');

        // Some das consultas normais (soft delete), mas fica no banco até a purga.
        $this->assertNull(Tenant::find($this->tenant->id));
        $trashed = Tenant::withTrashed()->find($this->tenant->id);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertNotNull($trashed->purge_scheduled_at);
        $this->assertSame('cancelled', $trashed->status);

        // Email RESERVADO na janela: a linha do usuário fica intacta (recuperável no login).
        $this->assertTrue(User::where('email', $original)->exists());
    }

    public function test_deleted_account_reserves_email_during_grace(): void
    {
        $email = $this->owner->email;

        // Conta excluída (soft-delete na janela). A linha do usuário fica intacta.
        $this->tenant->update(['purge_scheduled_at' => now()->addDays(30)]);
        $this->tenant->delete();

        // Como visitante, não dá pra cadastrar com o email reservado enquanto a janela não vence.
        $this->postJson('/api/auth/register', [
            'name' => 'Outro Dono',
            'email' => $email,
            'phone' => '92988887777',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_delete_account_requires_correct_password(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson('/api/account', ['current_password' => 'errada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        // Nada foi excluído.
        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    public function test_delete_account_forbidden_for_non_owner(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        $this->actingAs($receptionist)
            ->deleteJson('/api/account', ['current_password' => 'password'])
            ->assertStatus(403);

        $this->assertNotNull(Tenant::find($this->tenant->id));
    }

    public function test_purge_command_erases_accounts_past_grace_window(): void
    {
        // Conta excluída cuja janela de recuperação (30 dias) já venceu.
        $this->tenant->update(['purge_scheduled_at' => now()->subDay()]);
        $this->tenant->delete();

        $this->artisan('accounts:purge')->assertExitCode(0);

        // Apagada de vez; o cascade levou os usuários junto (erasure completo).
        $this->assertNull(Tenant::withTrashed()->find($this->tenant->id));
        $this->assertFalse(User::where('id', $this->owner->id)->exists());
    }

    public function test_purge_command_keeps_accounts_within_grace_window(): void
    {
        // Excluída há pouco: ainda dentro da janela, não pode ser apagada.
        $this->tenant->update(['purge_scheduled_at' => now()->addDays(20)]);
        $this->tenant->delete();

        $this->artisan('accounts:purge')->assertExitCode(0);

        $this->assertNotNull(Tenant::withTrashed()->find($this->tenant->id));
    }

    // ---- Notification settings ----

    public function test_notification_settings_returns_defaults(): void
    {
        $data = $this->actingAs($this->owner)
            ->getJson('/api/notification-settings')
            ->assertOk()
            ->json('data');

        $this->assertEqualsCanonicalizing(['email', 'whatsapp'], $data['channels']);
        $this->assertTrue($data['reminder_24h_before']);
        // Transacionais (confirmação, remarcação, cancelamento) ligam por padrão.
        $this->assertTrue($data['appointment_rescheduled']);
        $this->assertTrue($data['appointment_cancelled']);
    }

    public function test_notification_settings_update(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/notification-settings', [
                'channels' => ['whatsapp'],
                'reminder_24h_before' => false,
                'appointment_cancelled' => false,
                'email_from' => 'contato@barbearia.com',
            ])
            ->assertOk()
            ->assertJsonPath('data.reminder_24h_before', false)
            ->assertJsonPath('data.channels', ['whatsapp'])
            ->assertJsonPath('data.appointment_cancelled', false)
            ->assertJsonPath('data.email_from', 'contato@barbearia.com');
    }

    // ---- Team ----

    public function test_owner_can_invite_team_member(): void
    {
        $this->actingAs($this->owner)
            ->postJson('/api/tenant/team', [
                'name' => 'Recepção',
                'email' => 'recepcao@barbearia.com',
                'role' => 'receptionist',
                'password' => 'senha-segura-1',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $this->tenant->id,
            'email' => 'recepcao@barbearia.com',
            'role' => 'receptionist',
        ]);
    }

    public function test_team_invite_blocked_at_staff_limit(): void
    {
        $this->tenant->update(['plan' => 'barbearia']); // 4 funcionários no total

        // setUp já tem o dono (1). Mais 3 = 4 (no limite).
        foreach (range(1, 3) as $i) {
            User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'role' => 'receptionist',
                'email' => "func{$i}@barbearia.com",
            ]);
        }

        // 5º funcionário — qualquer papel — é bloqueado (limite único, sem brecha por papel).
        $this->actingAs($this->owner)
            ->postJson('/api/tenant/team', [
                'name' => 'Excedente',
                'email' => 'excedente@barbearia.com',
                'role' => 'manager',
                'password' => 'senha-segura-1',
            ])
            ->assertStatus(403);
    }

    public function test_cannot_remove_last_owner(): void
    {
        $this->actingAs($this->owner)
            ->deleteJson("/api/tenant/team/{$this->owner->id}")
            ->assertStatus(422);
    }

    public function test_can_remove_non_owner_member(): void
    {
        $member = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        $this->actingAs($this->owner)
            ->deleteJson("/api/tenant/team/{$member->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('users', ['id' => $member->id]);
    }

    public function test_team_management_forbidden_for_manager(): void
    {
        $manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'manager']);

        $this->actingAs($manager)->getJson('/api/tenant/team')->assertStatus(403);
    }
}
