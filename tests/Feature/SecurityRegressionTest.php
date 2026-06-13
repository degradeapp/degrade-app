<?php

use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regressões dos achados da auditoria de segurança (ver RELATORIO_SEGURANCA.md):
 *  A1: exists sem escopo de tenant em StoreAppointmentRequest (IDOR de escrita)
 *  A2: webhook do WhatsApp sem verificação de assinatura
 *  A3: BasePolicy::before liberava o dono cross-tenant (defesa em profundidade)
 */
beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->ownerA = User::factory()->create(['tenant_id' => $this->tenantA->id, 'role' => 'owner']);
});

// ---------- A1: IDOR de escrita via exists global ----------

it('rejeita customer_id de outro tenant ao criar agendamento interno', function () {
    $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);
    $service = Service::factory()->create(['tenant_id' => $this->tenantA->id]);

    $this->actingAs($this->ownerA)
        ->postJson('/api/appointments', [
            'customer_id' => $foreignCustomer->id,
            'service_ids' => [$service->id],
            'starts_at' => Carbon::now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');
});

it('rejeita barber_ids e service_ids de outro tenant ao criar agendamento interno', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);
    $foreignService = Service::factory()->create(['tenant_id' => $this->tenantB->id]);
    $foreignBarber = Barber::factory()->create(['tenant_id' => $this->tenantB->id]);

    $this->actingAs($this->ownerA)
        ->postJson('/api/appointments', [
            'customer_id' => $customer->id,
            'service_ids' => [$foreignService->id],
            'barber_ids' => [$foreignBarber->id],
            'starts_at' => Carbon::now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ])
        ->assertUnprocessable();
});

it('rejeita user_id de outro tenant ao criar barbeiro', function () {
    $foreignUser = User::factory()->create(['tenant_id' => $this->tenantB->id, 'role' => 'barber']);

    $this->actingAs($this->ownerA)
        ->postJson('/api/barbers', [
            'name' => 'Novo Barbeiro',
            'phone' => '(92) 99912-0760',
            'user_id' => $foreignUser->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

// ---------- A2: assinatura do webhook do WhatsApp ----------

it('rejeita webhook do whatsapp com assinatura invalida quando o app_secret esta configurado', function () {
    config(['services.whatsapp.app_secret' => 'segredo-de-teste']);

    $body = json_encode(['entry' => []]);

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X-Hub-Signature-256' => 'sha256=assinatura-falsa',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(401);
});

it('aceita webhook do whatsapp com assinatura valida', function () {
    config(['services.whatsapp.app_secret' => 'segredo-de-teste']);

    $body = json_encode(['entry' => []]);
    $signature = 'sha256='.hash_hmac('sha256', $body, 'segredo-de-teste');

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'HTTP_X-Hub-Signature-256' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertOk();
});

it('mantem o comportamento de dev: sem app_secret o webhook aceita (paridade com asaas)', function () {
    config(['services.whatsapp.app_secret' => null]);

    $this->postJson('/webhooks/whatsapp', ['entry' => []])->assertOk();
});

// ---------- A3: atalho do dono nunca cruza tenant ----------

it('nao deixa o before do dono autorizar recurso de outro tenant', function () {
    $foreignCustomer = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

    // Pelas rotas o TenantScope já dá 404; aqui forçamos o Gate direto pra
    // provar que a Policy, sozinha, também nega (defesa em profundidade).
    expect($this->ownerA->can('view', $foreignCustomer))->toBeFalse();
});

it('mantem o atalho do dono dentro do proprio tenant', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenantA->id]);

    expect($this->ownerA->can('view', $customer))->toBeTrue();
});
