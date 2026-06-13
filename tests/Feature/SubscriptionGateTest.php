<?php

use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * B1 da auditoria: as rotas operacionais de /api/* passam por subscription.active.
 * Tenant suspenso/vencido/cancelado nao opera via API (so as paginas web barravam).
 * Conta/billing/auth continuam acessiveis pra ele poder regularizar.
 */
function gateOwner(string $status): User
{
    $t = Tenant::factory()->create(['status' => $status, 'trial_ends_at' => now()->addDays(7)]);

    return User::factory()->create(['tenant_id' => $t->id, 'role' => 'owner']);
}

it('bloqueia rota operacional de API com assinatura inativa (402)', function (string $status) {
    $owner = gateOwner($status);

    $this->actingAs($owner)
        ->getJson('/api/customers')
        ->assertStatus(402)
        ->assertJsonPath('subscription_inactive', true);
})->with(['suspended', 'past_due', 'cancelled']);

it('libera rota operacional para tenant ativo ou em trial valido', function (string $status) {
    $owner = gateOwner($status);

    $this->actingAs($owner)
        ->getJson('/api/customers')
        ->assertOk();
})->with(['active', 'trial']);

it('nao bloqueia endpoints de conta mesmo com assinatura suspensa', function () {
    $owner = gateOwner('suspended');

    // /api/user (sem subscription.active) tem que responder normal pro front
    // saber quem esta logado e rotear pra cobranca.
    $this->actingAs($owner)
        ->getJson('/api/user')
        ->assertOk();
});
