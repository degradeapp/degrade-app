<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = 1;
        $now = now();

        // Barbeiro user + barbeiro
        $barberUserId = DB::table('users')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => 'Pedro Souza',
            'email' => 'pedro@degrade.test',
            'password' => Hash::make('password'),
            'role' => 'barber',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $barberId = DB::table('barbers')->insertGetId([
            'tenant_id' => $tenantId,
            'user_id' => $barberUserId,
            'name' => 'Pedro Souza',
            'phone' => '92991234567',
            'default_commission_percentage' => 50,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Horário do barbeiro (seg-sex 9-18, sáb 9-14)
        foreach ([1, 2, 3, 4, 5] as $dow) {
            DB::table('barber_schedules')->insert([
                'tenant_id' => $tenantId,
                'barber_id' => $barberId,
                'day_of_week' => $dow,
                'start_time' => '09:00',
                'end_time' => '18:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::table('barber_schedules')->insert([
            'tenant_id' => $tenantId,
            'barber_id' => $barberId,
            'day_of_week' => 6,
            'start_time' => '09:00',
            'end_time' => '14:00',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Serviços
        $services = [
            ['Corte de cabelo', 45.00, 50],
            ['Corte degradê', 50.00, 60],
            ['Corte + barba', 75.00, 60],
            ['Barba', 35.00, 50],
            ['Corte social', 60.00, 60],
        ];
        foreach ($services as [$name, $price, $commission]) {
            DB::table('services')->insert([
                'tenant_id' => $tenantId,
                'name' => $name,
                'price' => $price,
                'commission_percentage' => $commission,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Clientes
        $customers = [
            ['Carlos Silva', '85999991111'],
            ['Marcus Oliveira', '85988882222'],
            ['Bruno Costa', '85966664444'],
            ['André Mendes', '85955555555'],
        ];
        $customerIds = [];
        foreach ($customers as [$name, $phone]) {
            $customerIds[] = DB::table('customers')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => $name,
                'phone' => $phone,
                'total_visits' => 0,
                'total_spent' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Demo appointments for today
        $serviceIds = DB::table('services')->where('tenant_id', $tenantId)->pluck('id', 'name')->toArray();
        $corteService = $serviceIds['Corte degradê'] ?? array_values($serviceIds)[0];
        $today = now()->setHour(9)->setMinute(0)->setSecond(0);

        foreach ([
            [0, 'scheduled'],
            [2, 'scheduled'],
            [4, 'confirmed'],
        ] as $i => [$offsetHours, $status]) {
            $start = $today->copy()->addHours($offsetHours);
            $end = $start->copy()->addMinutes(45);

            $apptId = DB::table('appointments')->insertGetId([
                'tenant_id' => $tenantId,
                'customer_id' => $customerIds[$i % count($customerIds)],
                'barber_id' => $barberId,
                'status' => $status,
                'source' => 'walk_in',
                'starts_at' => $start,
                'ends_at' => $end,
                'total_price' => 50.00,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('appointment_services')->insert([
                'tenant_id' => $tenantId,
                'appointment_id' => $apptId,
                'service_id' => $corteService,
                'barber_id' => $barberId,
                'price_snapshot' => 50.00,
                'commission_percentage_snapshot' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
