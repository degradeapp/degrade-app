<?php

namespace Tests\Feature;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Smoke test exaustivo: monta uma barbearia mockada e bate em TODO endpoint do
 * sistema garantindo que nenhum responde com erro de servidor (5xx) e que o
 * caminho feliz devolve o status esperado. Qualquer 500 aqui é um bug real.
 */
class SystemSmokeTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Barber $barber;

    private Service $service;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 5, 28, 10, 0, 0, 'America/Manaus'));

        // Não bate na API real do Asaas.
        Http::fake([
            '*/customers' => Http::response(['id' => 'cust_smoke'], 200),
            '*/subscriptions' => Http::response(['id' => 'sub_smoke', 'status' => 'PENDING'], 200),
            '*' => Http::response([], 200),
        ]);

        $businessHours = collect(range(0, 6))->map(fn ($d) => [
            'day_of_week' => $d, 'start_time' => '08:00', 'end_time' => '20:00', 'closed' => false,
        ])->all();

        $this->tenant = Tenant::create([
            'name' => 'Barbearia Smoke',
            'slug' => 'barbearia-smoke',
            'status' => 'active',
            'plan' => 'barbearia',
            'trial_ends_at' => null,
            'onboarding_completed_at' => now(),
            'settings' => json_encode([
                'timezone' => 'America/Manaus',
                'locale' => 'pt_BR',
                'business_hours' => $businessHours,
                'financial' => ['default_commission_percentage' => 50],
            ]),
        ]);
        app()->instance('tenant', $this->tenant);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Dona Smoke',
            'email' => 'owner@smoke.test', 'password' => 'password', 'role' => 'owner',
        ]);

        $barberUser = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Barbeiro Smoke',
            'email' => 'barber@smoke.test', 'password' => 'password', 'role' => 'barber',
        ]);

        $this->barber = Barber::create([
            'tenant_id' => $this->tenant->id, 'user_id' => $barberUser->id,
            'name' => 'Barbeiro Smoke', 'phone' => '92991110000', 'default_commission_percentage' => 50,
        ]);
        foreach (range(0, 6) as $dow) {
            $this->barber->schedules()->create([
                'tenant_id' => $this->tenant->id, 'day_of_week' => $dow,
                'start_time' => '08:00', 'end_time' => '20:00',
            ]);
        }

        $this->service = Service::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Corte', 'price' => 50, 'commission_percentage' => 50,
        ]);

        $this->customer = Customer::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente Smoke', 'phone' => '92992220000',
        ]);
    }

    public function test_inertia_pages_load(): void
    {
        $this->actingAs($this->owner);

        $this->get('/')->assertOk();              // Dashboard
        $this->get('/appointments')->assertOk();  // Agenda
        $this->get('/appointments/create')->assertOk();
        $this->get('/reports')->assertOk();
        $this->get('/audit')->assertOk();
    }

    public function test_appointment_lifecycle_endpoints(): void
    {
        $this->actingAs($this->owner);

        $payload = fn (int $offsetHours) => [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => now()->addHours($offsetHours)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ];

        $this->getJson('/api/appointments')->assertOk();
        $this->getJson("/api/appointments/availability/barber/{$this->barber->id}?date=".now()->toDateString())->assertOk();
        $this->getJson("/api/appointments/availability/barber/{$this->barber->id}/day?date=".now()->toDateString())->assertOk();

        $a1 = $this->postJson('/api/appointments', $payload(2))->assertCreated()->json('id');
        $a2 = $this->postJson('/api/appointments', $payload(3))->assertCreated()->json('id');
        $a3 = $this->postJson('/api/appointments', $payload(4))->assertCreated()->json('id');

        $this->getJson("/api/appointments/{$a1}")->assertOk();
        $this->get("/appointments/{$a1}")->assertOk(); // showPage
        $this->putJson("/api/appointments/{$a1}", ['notes' => 'obs smoke'])->assertOk();
        $this->postJson("/api/appointments/{$a1}/complete")->assertOk();
        $this->postJson("/api/appointments/{$a2}/cancel", ['reason' => 'teste'])->assertOk();
        $this->putJson("/api/appointments/{$a3}/reschedule", [
            'starts_at' => now()->addHours(6)->format('Y-m-d\TH:i:s'),
        ])->assertOk();

        // Concluir gerou comissão → exercita commissions
        $commission = Commission::where('tenant_id', $this->tenant->id)->first();
        $this->assertNotNull($commission, 'Concluir agendamento deve gerar comissão');
        $this->getJson('/api/commissions')->assertOk();
        $this->getJson("/api/commissions/{$commission->id}")->assertOk();
        $this->postJson("/api/commissions/{$commission->id}/mark-as-paid")->assertOk()
            ->assertJsonPath('status', 'paid');
    }

    public function test_appointment_show_page_prop_is_unwrapped(): void
    {
        $this->actingAs($this->owner);

        $id = $this->postJson('/api/appointments', [
            'customer_id' => $this->customer->id,
            'service_ids' => [$this->service->id],
            'barber_ids' => [$this->barber->id],
            'starts_at' => now()->addHours(2)->format('Y-m-d\TH:i:s'),
            'source' => 'walk_in',
        ])->json('id');

        // Show.vue acessa appointment.customer.name / appointment.services direto (sem .data).
        // Se o Inertia embrulhar o Resource em "data", o componente quebra (tela preta).
        $this->get("/appointments/{$id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Appointments/Show')
            ->has('appointment.customer.name')
            ->has('appointment.services')
            ->has('appointment.status_label')
        );
    }

    public function test_past_appointment_show_page_has_full_data(): void
    {
        $this->actingAs($this->owner);

        // Agendamento NO PASSADO (status derivado vira "completed"). Criado direto no
        // model porque a API barra horário passado. Show.vue precisa renderizar mesmo assim.
        $appt = Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => 'scheduled',
            'source' => 'walk_in',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(3)->addMinutes(30),
            'total_price' => 30,
        ]);
        $appt->services()->create([
            'tenant_id' => $this->tenant->id,
            'service_id' => $this->service->id,
            'barber_id' => $this->barber->id,
            'price_snapshot' => 30,
            'commission_percentage_snapshot' => 50,
        ]);

        $this->get("/appointments/{$appt->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Appointments/Show')
            // Passado e nunca concluído explicitamente → "A concluir" (NÃO "Concluído";
            // concluir é ação manual que gera comissão). Libera o botão Concluir no detalhe.
            ->where('appointment.status', 'awaiting_completion')
            ->where('appointment.status_label', 'A concluir')
            ->has('appointment.customer.name')
            // services tem que ser ARRAY PURO (sem wrapper "data") — senão o Show.vue quebra
            ->has('appointment.services', 1)
            ->has('appointment.services.0.service.name')
            ->has('appointment.total_price')
        );
    }

    public function test_agenda_uses_derived_status_like_detail(): void
    {
        $this->actingAs($this->owner);

        Appointment::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'barber_id' => $this->barber->id,
            'status' => 'scheduled', // persistido scheduled, mas no passado
            'source' => 'walk_in',
            'starts_at' => now()->subHours(3),
            'ends_at' => now()->subHours(3)->addMinutes(30),
            'total_price' => 30,
        ]);

        // A agenda mostra o status DERIVADO, igual ao detalhe. Passado e não concluído
        // = 'awaiting_completion' ("A concluir"), não 'completed' — assim o filtro
        // "Concluídos" só pega atendimentos de fato concluídos (que geraram comissão).
        $this->get('/appointments')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Appointments/Index')
            ->where('appointments.0.status', 'awaiting_completion')
        );
    }

    public function test_barber_endpoints(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/barbers')->assertOk();
        $id = $this->postJson('/api/barbers', [
            'name' => 'Novo Barbeiro', 'phone' => '92993330000', 'default_commission_percentage' => 40,
        ])->assertCreated()->json('id');
        $this->getJson("/api/barbers/{$id}")->assertOk();
        $this->putJson("/api/barbers/{$id}", ['name' => 'Editado', 'phone' => '92993330000', 'default_commission_percentage' => 45])->assertOk();
        $this->putJson("/api/barbers/{$id}/schedule/3", ['start_time' => '09:00', 'end_time' => '18:00'])->assertOk();
        $date = now()->addDays(2)->toDateString();
        $this->postJson("/api/barbers/{$id}/time-off", ['date' => $date, 'reason' => 'folga'])->assertCreated();
        $this->deleteJson("/api/barbers/{$id}/time-off/{$date}")->assertNoContent();
        $this->deleteJson("/api/barbers/{$id}")->assertNoContent();
    }

    public function test_customer_endpoints(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/customers')->assertOk();
        $id = $this->postJson('/api/customers', ['name' => 'Fulano', 'phone' => '92994440000'])->assertCreated()->json('id');
        $this->getJson("/api/customers/{$id}")->assertOk();
        $this->putJson("/api/customers/{$id}", ['name' => 'Fulano Editado'])->assertOk();
        $this->deleteJson("/api/customers/{$id}")->assertNoContent();
    }

    public function test_service_endpoints(): void
    {
        $this->actingAs($this->owner);

        $this->getJson('/api/services')->assertOk();
        $id = $this->postJson('/api/services', ['name' => 'Barba', 'price' => 30])->assertCreated()->json('id');
        $this->getJson("/api/services/{$id}")->assertOk();
        $this->putJson("/api/services/{$id}", ['name' => 'Barba Premium', 'price' => 35])->assertOk();
        $this->postJson('/api/services/bulk', ['services' => [['name' => 'Pezinho', 'price' => 15]]])->assertCreated();
        $this->postJson("/api/services/{$id}/barbers/{$this->barber->id}")->assertSuccessful();
        $this->deleteJson("/api/services/{$id}/barbers/{$this->barber->id}")->assertSuccessful();
        $this->deleteJson("/api/services/{$id}")->assertNoContent();
    }

    public function test_settings_and_misc_endpoints(): void
    {
        $this->actingAs($this->owner);

        // Tenant settings / horários
        $this->getJson('/api/tenant/settings')->assertOk();
        $this->putJson('/api/tenant/settings', ['name' => 'Barbearia Nova', 'timezone' => 'America/Sao_Paulo'])->assertOk();
        $this->putJson('/api/tenant/business-hours', [
            'business_hours' => collect(range(0, 6))->map(fn ($d) => [
                'day_of_week' => $d, 'start_time' => '09:00', 'end_time' => '19:00', 'closed' => false,
            ])->all(),
        ])->assertOk();

        // Equipe / acessos
        $this->getJson('/api/tenant/team')->assertOk();
        $memberId = $this->postJson('/api/tenant/team', [
            'name' => 'Recepção', 'email' => 'recep@smoke.test', 'role' => 'receptionist', 'password' => 'senha-forte-1',
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/tenant/team/{$memberId}")->assertNoContent();

        // Perfil
        $this->getJson('/api/profile')->assertOk();
        $this->putJson('/api/profile', ['name' => 'Dona Editada'])->assertOk();

        // Notificações
        $this->getJson('/api/notification-settings')->assertOk();
        $this->putJson('/api/notification-settings', ['reminder_24h_before' => false])->assertOk();

        // Relatórios / busca / auditoria / health
        $this->getJson('/api/reports/summary?from='.now()->subDays(7)->toDateString().'&to='.now()->toDateString())->assertOk();
        $this->getJson('/api/search?q=Cliente')->assertOk();
        $this->getJson('/api/audit')->assertOk();
        $this->getJson('/api/health')->assertOk();

        // Billing (Asaas em fake)
        $this->getJson('/api/billing')->assertOk();

        // WhatsApp
        $this->getJson('/api/whatsapp/account')->assertOk();
        $this->putJson('/api/whatsapp/account', [
            'phone_number_id' => '123456789012345', 'access_token' => str_repeat('x', 40),
        ])->assertSuccessful();
        $this->getJson('/api/whatsapp/conversations')->assertOk();
    }
}
