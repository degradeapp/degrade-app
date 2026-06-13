<?php

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AppointmentPricer;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\User\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Regressões de eficiência (auditoria do Fable 5). A garantia testada é que o
 * número de queries NÃO escala com o nº de itens (serviços / barbeiros).
 */
test('E3: AppointmentPricer nao faz N+1 de barbeiro por servico', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    app()->instance('tenant', $tenant);

    $barber = Barber::factory()->create(['tenant_id' => $tenant->id]);
    foreach (range(1, 3) as $i) {
        Service::factory()->create(['tenant_id' => $tenant->id, 'name' => "Servico {$i}", 'commission_percentage' => 20]);
    }
    $services = Service::where('tenant_id', $tenant->id)->get();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $appointment = Appointment::create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'status' => 'scheduled',
        'source' => 'walk_in',
        'starts_at' => Carbon::now(),
        'ends_at' => Carbon::now()->addMinutes(30),
        'total_price' => 0,
    ]);

    // Conta só as queries que ANTES eram N+1: barbeiros + pivot barber_service.
    $barberQueries = 0;
    DB::listen(function ($q) use (&$barberQueries) {
        if (str_contains($q->sql, '"barbers"') || str_contains($q->sql, 'barber_service')) {
            $barberQueries++;
        }
    });

    $pricer = app(AppointmentPricer::class);
    $pricer->snapshotServices($appointment, $services, [$barber->id, $barber->id, $barber->id]);

    // 1 load de barbeiros + 1 load do pivot = 2, independente do nº de serviços.
    expect($barberQueries)->toBeLessThanOrEqual(2)
        ->and($appointment->services()->count())->toBe(3);
});

test('E2: dashboard carrega folgas de hoje sem N+1 por barbeiro', function () {
    $tenant = Tenant::factory()->create(['status' => 'active']);
    app()->instance('tenant', $tenant);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // 3 barbeiros com expediente hoje: se a folga fosse N+1, seriam 3 queries.
    $dow = Carbon::now()->dayOfWeek;
    foreach (range(1, 3) as $i) {
        $b = Barber::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);
        DB::table('barber_schedules')->insert([
            'tenant_id' => $tenant->id, 'barber_id' => $b->id, 'day_of_week' => $dow,
            'start_time' => '09:00', 'end_time' => '18:00', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    $timeOffQueries = 0;
    DB::listen(function ($q) use (&$timeOffQueries) {
        if (str_contains($q->sql, 'barber_time_off')) {
            $timeOffQueries++;
        }
    });

    $this->actingAs($owner)->get('/')->assertOk();

    expect($timeOffQueries)->toBe(1); // 1 eager load, não 1 por barbeiro
});
