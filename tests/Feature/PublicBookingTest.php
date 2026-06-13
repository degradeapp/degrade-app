<?php

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Unit\Models\Unit;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Link público de agendamento (/api/public/agendar/{slug}).
 * Cobre: fluxo feliz, casamento de cliente existente, slug inválido, tenant
 * suspenso, passado, fora do expediente, serviço/barbeiro/unidade de outro
 * tenant (isolação), rate limit e não-vazamento de dados no catálogo.
 */
beforeEach(function () {
    // Congela o relógio numa segunda-feira 10:00 pra estabilidade dos testes.
    Carbon::setTestNow(Carbon::parse('2026-06-15 10:00:00'));

    $this->tenant = Tenant::factory()->create(['slug' => 'barbearia-teste', 'status' => 'active']);

    $this->unit = Unit::create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Unidade principal',
        'is_active' => true,
    ]);

    $this->barber = Barber::factory()->create([
        'tenant_id' => $this->tenant->id,
        'unit_id' => $this->unit->id,
        'is_active' => true,
    ]);

    // Expediente todos os dias 09:00-18:00 (cobre qualquer convenção de day_of_week).
    foreach (range(0, 6) as $dow) {
        DB::table('barber_schedules')->insert([
            'tenant_id' => $this->tenant->id,
            'barber_id' => $this->barber->id,
            'day_of_week' => $dow,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $this->service = Service::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
        'price' => 50,
    ]);

    $this->validPayload = fn (array $overrides = []) => array_merge([
        'name' => 'Cliente Público',
        'phone' => '(92) 99912-0760',
        'service_ids' => [$this->service->id],
        'barber_id' => $this->barber->id,
        'starts_at' => Carbon::now()->addDay()->setTime(14, 0)->format('Y-m-d\TH:i:s'),
    ], $overrides);
});

afterEach(fn () => Carbon::setTestNow());

it('mostra o catálogo público sem vazar dados sensíveis', function () {
    $response = $this->getJson('/api/public/agendar/barbearia-teste');

    $response->assertOk()
        ->assertJsonPath('data.name', $this->tenant->name)
        ->assertJsonFragment(['name' => $this->service->name]);

    // Catálogo nunca expõe telefone de barbeiro nem qualquer dado de cliente.
    expect($response->json('data.barbers.0'))->not->toHaveKey('phone');
    expect(json_encode($response->json()))->not->toContain('total_spent');
});

it('retorna 404 para slug inexistente', function () {
    $this->getJson('/api/public/agendar/nao-existe')->assertNotFound();
});

it('retorna 404 para tenant suspenso, vencido ou cancelado', function (string $status) {
    $this->tenant->update(['status' => $status]);

    $this->getJson('/api/public/agendar/barbearia-teste')->assertNotFound();
    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)())->assertNotFound();
})->with(['suspended', 'past_due', 'cancelled']);

it('aceita tenant em trial valido', function () {
    $this->tenant->update(['status' => 'trial', 'trial_ends_at' => now()->addDays(7)]);

    $this->getJson('/api/public/agendar/barbearia-teste')->assertOk();
});

it('lista horarios disponiveis do dia', function () {
    $date = Carbon::now()->addDay()->toDateString();

    $response = $this->getJson("/api/public/agendar/barbearia-teste/horarios?date={$date}&barber_id={$this->barber->id}");

    $response->assertOk();
    expect($response->json('data.slots'))->toContain('14:00');
});

it('cria agendamento publico no fluxo feliz', function () {
    $response = $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)());

    $response->assertCreated()
        ->assertJsonPath('data.barber_name', $this->barber->name);

    $appointment = Appointment::withoutGlobalScopes()->latest('id')->first();
    expect($appointment->tenant_id)->toBe($this->tenant->id)
        ->and($appointment->unit_id)->toBe($this->unit->id)
        ->and($appointment->source->value)->toBe('customer')
        ->and((float) $appointment->total_price)->toBe(50.0);

    $customer = Customer::withoutGlobalScopes()->where('phone', '92999120760')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->tenant_id)->toBe($this->tenant->id)
        ->and($appointment->customer_id)->toBe($customer->id);
});

it('casa cliente existente pelo telefone no mesmo tenant em vez de duplicar', function () {
    $existing = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'phone' => '92999120760',
        'name' => 'Giovane',
    ]);

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)())->assertCreated();

    expect(Customer::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('phone', '92999120760')->count())->toBe(1);
    expect(Appointment::withoutGlobalScopes()->latest('id')->first()->customer_id)->toBe($existing->id);
});

it('rejeita horario no passado', function () {
    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'starts_at' => Carbon::now()->subHour()->format('Y-m-d\TH:i:s'),
    ]))->assertUnprocessable();
});

it('rejeita horario alem do horizonte de 60 dias', function () {
    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'starts_at' => Carbon::now()->addDays(90)->setTime(14, 0)->format('Y-m-d\TH:i:s'),
    ]))->assertUnprocessable();
});

it('rejeita horario fora do expediente do barbeiro', function () {
    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'starts_at' => Carbon::now()->addDay()->setTime(23, 0)->format('Y-m-d\TH:i:s'),
    ]))->assertUnprocessable();

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('rejeita horario ja ocupado (link publico nao encaixa)', function () {
    $startsAt = Carbon::now()->addDay()->setTime(14, 0);

    DB::table('appointments')->insert([
        'tenant_id' => $this->tenant->id,
        'unit_id' => $this->unit->id,
        'customer_id' => Customer::factory()->create(['tenant_id' => $this->tenant->id])->id,
        'barber_id' => $this->barber->id,
        'status' => 'scheduled',
        'source' => 'walk_in',
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'total_price' => 50,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)())
        ->assertUnprocessable();
});

it('nao aceita servico de outro tenant (isolacao)', function () {
    $other = Tenant::factory()->create();
    $foreignService = Service::factory()->create(['tenant_id' => $other->id]);

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'service_ids' => [$foreignService->id],
    ]))->assertUnprocessable();

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('nao aceita barbeiro de outro tenant (isolacao)', function () {
    $other = Tenant::factory()->create();
    $foreignBarber = Barber::factory()->create(['tenant_id' => $other->id]);

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'barber_id' => $foreignBarber->id,
    ]))->assertUnprocessable();

    expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
});

it('nao aceita unidade de outro tenant (isolacao)', function () {
    $other = Tenant::factory()->create();
    $foreignUnit = Unit::create(['tenant_id' => $other->id, 'name' => 'Outra', 'is_active' => true]);

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'unit_id' => $foreignUnit->id,
    ]))->assertNotFound();
});

it('aplica rate limit na criacao publica', function () {
    // Payload inválido de propósito: o throttle conta a request antes da
    // validação, então dá pra estourar o limite sem criar agendamento.
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/public/agendar/barbearia-teste', [])->assertUnprocessable();
    }

    $this->postJson('/api/public/agendar/barbearia-teste', [])->assertStatus(429);
});

it('valida telefone brasileiro e nome', function () {
    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'phone' => '123',
    ]))->assertUnprocessable()->assertJsonValidationErrors('phone');

    $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'name' => 'A',
    ]))->assertUnprocessable()->assertJsonValidationErrors('name');
});

it('no modo qualquer barbeiro escolhe um disponivel da unidade', function () {
    $response = $this->postJson('/api/public/agendar/barbearia-teste', ($this->validPayload)([
        'barber_id' => null,
    ]));

    $response->assertCreated();
    expect(Appointment::withoutGlobalScopes()->latest('id')->first()->barber_id)->toBe($this->barber->id);
});
