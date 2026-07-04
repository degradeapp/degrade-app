<?php

namespace App\Console\Commands;

use App\Enums\AppointmentSource;
use App\Modules\Appointment\Actions\CompleteAppointment;
use App\Modules\Appointment\Actions\CreateAppointment;
use App\Modules\Auth\Actions\RegisterTenantOwner;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tenant de demonstração pra mostrar o produto VIVO numa visita presencial,
 * sem digitar dados na frente do cliente: equipe, serviços, clientes,
 * histórico concluído (com comissões reais) e agenda de hoje/amanhã.
 *
 * Usa as MESMAS actions do produto (registro, agendamento, conclusão) pra
 * respeitar todos os invariantes (dono-barbeiro, snapshots, comissões).
 */
class SeedDemoTenant extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Cria um tenant de demonstração completo (equipe, serviços, clientes, agenda e comissões)';

    private const SLUG = 'demo-degrade';

    private const EMAIL = 'demo@degrade.test';

    private const PASSWORD = 'demo1234';

    public function handle(
        RegisterTenantOwner $register,
        CreateAppointment $createAppointment,
        CompleteAppointment $completeAppointment,
    ): int {
        if (app()->environment('production')) {
            $this->error('demo:seed não roda em produção.');

            return self::FAILURE;
        }

        if (Tenant::where('slug', self::SLUG)->exists()) {
            $this->info('O tenant demo já existe.');
            $this->printAccess();

            return self::SUCCESS;
        }

        DB::transaction(function () use ($register, $createAppointment, $completeAppointment) {
            // Registro real: nasce tenant (trial), dono e o barbeiro do dono.
            $owner = $register(
                name: 'João Barbosa',
                email: self::EMAIL,
                password: self::PASSWORD,
                phone: '92991234567',
            );

            $tenant = $owner->tenant;
            $tenant->update([
                'name' => 'Barbearia do João',
                'slug' => self::SLUG,
                'onboarding_completed_at' => now(),
            ]);

            // Equipe: dono + 2 barbeiros contratados, expediente seg-sáb.
            $ownerBarber = Barber::where('user_id', $owner->id)->firstOrFail();
            $carlos = Barber::create([
                'tenant_id' => $tenant->id,
                'name' => 'Carlos Tesoura',
                'phone' => '92991112222',
                'default_commission_percentage' => 50,
                'is_active' => true,
            ]);
            $pedro = Barber::create([
                'tenant_id' => $tenant->id,
                'name' => 'Pedro Navalha',
                'phone' => '92993334444',
                'default_commission_percentage' => 40,
                'is_active' => true,
            ]);

            foreach ([$ownerBarber, $carlos, $pedro] as $barber) {
                foreach (range(1, 6) as $dow) {
                    DB::table('barber_schedules')->insert([
                        'tenant_id' => $tenant->id,
                        'barber_id' => $barber->id,
                        'day_of_week' => $dow,
                        'start_time' => '09:00',
                        'end_time' => $dow === 6 ? '14:00' : '19:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Serviços com preço de mercado de Manaus.
            $services = collect([
                ['Corte degradê', 50.00],
                ['Corte social', 40.00],
                ['Corte + barba', 75.00],
                ['Barba completa', 35.00],
                ['Pezinho', 15.00],
                ['Sobrancelha', 10.00],
            ])->map(fn ($s) => Service::create([
                'tenant_id' => $tenant->id,
                'name' => $s[0],
                'price' => $s[1],
                'is_active' => true,
            ]));

            $customers = collect([
                ['Marcos Vinícius', '92988110001'],
                ['Rafael Cordeiro', '92988110002'],
                ['Thiago Nascimento', '92988110003'],
                ['Luan Pereira', '92988110004'],
                ['Felipe Castro', '92988110005'],
                ['Gabriel dos Santos', '92988110006'],
                ['André Souza', '92988110007'],
                ['Diego Ramos', '92988110008'],
                ['Bruno Lima', '92988110009'],
                ['Caio Ferreira', '92988110010'],
            ])->map(fn ($c) => Customer::create([
                'tenant_id' => $tenant->id,
                'name' => $c[0],
                'phone' => $c[1],
                'is_active' => true,
            ]));

            $barbers = collect([$ownerBarber, $carlos, $pedro]);

            // Histórico: última semana concluída (gera comissão real por serviço).
            $slot = 0;
            foreach (range(7, 1) as $daysAgo) {
                $day = Carbon::now()->subDays($daysAgo);
                if ($day->dayOfWeek === Carbon::SUNDAY) {
                    continue; // fechado
                }

                foreach ([9, 11, 14, 16] as $hour) {
                    $service = $services[$slot % $services->count()];
                    $barber = $barbers[$slot % $barbers->count()];
                    $customer = $customers[$slot % $customers->count()];
                    $slot++;

                    $appointment = $createAppointment(
                        customerId: $customer->id,
                        serviceIds: [$service->id],
                        startsAt: $day->copy()->setTime($hour, 0),
                        source: $slot % 3 === 0 ? AppointmentSource::customer : AppointmentSource::walk_in,
                        barberIds: [$barber->id],
                    );
                    $completeAppointment($appointment, $owner->id);
                }
            }

            // Agenda viva: horários de hoje/amanhã ainda por atender.
            $upcoming = [
                Carbon::now()->addHours(2),
                Carbon::now()->addHours(4),
                Carbon::tomorrow()->setTime(10, 0),
                Carbon::tomorrow()->setTime(15, 0),
            ];
            foreach ($upcoming as $i => $startsAt) {
                $createAppointment(
                    customerId: $customers[$i]->id,
                    serviceIds: [$services[$i % $services->count()]->id],
                    startsAt: $startsAt,
                    source: AppointmentSource::customer,
                    barberIds: [$barbers[$i % $barbers->count()]->id],
                );
            }
        });

        $this->info('Tenant demo criado.');
        $this->printAccess();

        return self::SUCCESS;
    }

    private function printAccess(): void
    {
        $this->line('Login: '.self::EMAIL.' | Senha: '.self::PASSWORD);
        $this->line('Link público de agendamento: /agendar/'.self::SLUG);
    }
}
