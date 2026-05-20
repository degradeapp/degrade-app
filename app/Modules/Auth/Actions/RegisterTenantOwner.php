<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\User\Enums\UserRole;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

readonly class RegisterTenantOwner
{
    public function __invoke(
        string $name,
        string $email,
        string $password,
        string $tenantName,
        string $tenantSlug,
    ): User {
        return DB::transaction(function () use ($name, $email, $password, $tenantName, $tenantSlug) {
            $tenant = Tenant::create([
                'name' => $tenantName,
                'slug' => $tenantSlug,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'settings' => json_encode([
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                    'financial' => [
                        'default_commission_percentage' => 15,
                    ],
                ]),
            ]);

            app(TenantContext::class)->set($tenant);

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::owner,
                'is_active' => true,
            ]);

            return $user->load('tenant');
        });
    }
}
