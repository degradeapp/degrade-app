<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantSeeder extends Seeder {
    public function run(): void {
        DB::table('tenants')->insertOrIgnore([
            [
                'id' => 1,
                'name' => 'Test Tenant',
                'slug' => 'test-tenant',
                'status' => 'active',
                'trial_ends_at' => null,
                'settings' => json_encode([
                    'timezone' => 'America/Manaus',
                    'locale' => 'pt_BR',
                    'financial' => [
                        'default_commission_percentage' => 15,
                    ],
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
