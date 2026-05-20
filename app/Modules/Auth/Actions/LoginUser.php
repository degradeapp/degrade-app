<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Tenant\Services\TenantContext;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Hash;

readonly class LoginUser
{
    public function __invoke(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        app(TenantContext::class)->set($user->tenant);

        return $user->load('tenant');
    }
}
