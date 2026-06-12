<?php

namespace Tests\Feature;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use App\Modules\Whatsapp\Enums\WhatsappBotState;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use App\Modules\Whatsapp\Models\WhatsappConversation;
use Carbon\Carbon;
use Tests\TestCase;

class WhatsappAccountTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    }

    public function test_upsert_account_creates_and_encrypts_token(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/whatsapp/account', [
                'phone_number_id' => '109876543210',
                'access_token' => 'EAA-super-secret-token',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone_number_id', '109876543210')
            ->assertJsonPath('data.is_active', true);

        $account = WhatsappAccount::where('tenant_id', $this->tenant->id)->firstOrFail();
        // Token é descriptografado pelo accessor, mas armazenado cifrado no banco
        $this->assertSame('EAA-super-secret-token', $account->access_token);
        $this->assertNotSame('EAA-super-secret-token', $account->getRawOriginal('access_token'));
    }

    public function test_upsert_account_updates_existing(): void
    {
        WhatsappAccount::create([
            'tenant_id' => $this->tenant->id,
            'phone_number_id' => 'old-id',
            'access_token' => 'old-token-xxxxx',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)
            ->putJson('/api/whatsapp/account', [
                'phone_number_id' => 'new-id',
                'access_token' => 'new-token-xxxxx',
            ])
            ->assertOk()
            ->assertJsonPath('data.phone_number_id', 'new-id');

        $this->assertSame(1, WhatsappAccount::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_upsert_validates_token_length(): void
    {
        $this->actingAs($this->owner)
            ->putJson('/api/whatsapp/account', ['phone_number_id' => 'x', 'access_token' => 'short'])
            ->assertStatus(422);
    }

    public function test_list_account_returns_null_when_none(): void
    {
        $this->actingAs($this->owner)
            ->getJson('/api/whatsapp/account')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    public function test_list_conversations_scoped_to_tenant(): void
    {
        WhatsappConversation::create([
            'tenant_id' => $this->tenant->id,
            'phone_number' => '5592999990000',
            'state' => WhatsappBotState::greeting,
            'session_data' => [],
            'last_interaction_at' => Carbon::now(),
        ]);

        $other = Tenant::factory()->create();
        WhatsappConversation::create([
            'tenant_id' => $other->id,
            'phone_number' => '5592888880000',
            'state' => WhatsappBotState::greeting,
            'session_data' => [],
            'last_interaction_at' => Carbon::now(),
        ]);

        $data = $this->actingAs($this->owner)
            ->getJson('/api/whatsapp/conversations')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $data);
        $this->assertSame('5592999990000', $data[0]['phone_number']);
    }

    public function test_show_conversation_from_other_tenant_is_404(): void
    {
        $other = Tenant::factory()->create();
        $conversation = WhatsappConversation::create([
            'tenant_id' => $other->id,
            'phone_number' => '5592888880000',
            'state' => WhatsappBotState::greeting,
            'session_data' => [],
            'last_interaction_at' => Carbon::now(),
        ]);

        $this->actingAs($this->owner)
            ->getJson("/api/whatsapp/conversations/{$conversation->id}")
            ->assertStatus(404);
    }

    public function test_account_management_forbidden_for_receptionist(): void
    {
        $receptionist = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'receptionist']);

        // 403 sem permissão, com mensagem em pt-BR.
        $this->actingAs($receptionist)->getJson('/api/whatsapp/account')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Você não tem permissão para esta ação.');
    }

    public function test_unauthenticated_api_returns_ptbr_message(): void
    {
        // Sessão perdida (sem login) numa chamada de API: 401 em pt-BR, não "Unauthenticated".
        $this->getJson('/api/whatsapp/account')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Sua sessão expirou. Faça login novamente.');
    }

    public function test_account_credentials_forbidden_for_manager(): void
    {
        $manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'manager']);

        // Credencial (Phone ID/token) é nível de conta: só o dono.
        $this->actingAs($manager)->getJson('/api/whatsapp/account')->assertStatus(403);
        $this->actingAs($manager)->putJson('/api/whatsapp/account', [
            'phone_number_id' => '123456789012345', 'access_token' => str_repeat('x', 40),
        ])->assertStatus(403);

        // ...mas a caixa de entrada (operacional) ele acessa.
        $this->actingAs($manager)->getJson('/api/whatsapp/conversations')->assertOk();
    }

    // ---- Webhook verification (Meta handshake) ----

    public function test_webhook_verify_returns_challenge_with_correct_token(): void
    {
        config(['services.whatsapp.verify_token' => 'meu-token-verify']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=meu-token-verify&hub_challenge=12345')
            ->assertOk()
            ->assertSee('12345');
    }

    public function test_webhook_verify_rejects_wrong_token(): void
    {
        config(['services.whatsapp.verify_token' => 'meu-token-verify']);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=errado&hub_challenge=12345')
            ->assertStatus(403);
    }
}
