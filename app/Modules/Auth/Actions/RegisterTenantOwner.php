<?php

namespace App\Modules\Auth\Actions;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\User\Enums\UserRole;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class RegisterTenantOwner
{
    public function __invoke(
        string $name,
        string $email,
        string $password,
        ?string $phone = null,
    ): User {
        return DB::transaction(function () use ($name, $email, $password, $phone) {
            // Nome real da barbearia é definido no onboarding (step 1). Aqui usamos
            // um placeholder e um slug aleatório único (slug não é informado pelo
            // usuário, então duas barbearias com o mesmo nome não colidem).
            $tenant = Tenant::create([
                'name' => 'Minha Barbearia',
                'slug' => $this->uniqueSlug(),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
                'settings' => [
                    'timezone' => config('app.timezone'),
                    'locale' => config('app.locale'),
                    'financial' => [
                        'default_commission_percentage' => 50,
                    ],
                    'contact_phone' => $phone,
                ],
            ]);

            app(TenantContext::class)->set($tenant);
            app()->instance('tenant', $tenant);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => UserRole::owner,
                'is_active' => true,
            ]);

            // O dono já entra como barbeiro da própria equipe: ele atende clientes e
            // precisa de um perfil pra aparecer na agenda. Nome e telefone vêm do próprio
            // registro — por isso o onboarding NÃO pede isso de novo. Comissão do dono é
            // 100% por padrão: ele é o dono, fica com tudo do próprio serviço (e o
            // CommissionService nem gera comissão pra ele).
            Barber::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'name' => $name,
                'phone' => $phone ? preg_replace('/\D/', '', $phone) : null,
                'default_commission_percentage' => 100,
                'is_active' => true,
            ]);

            return $user->load('tenant');
        });
    }

    private function uniqueSlug(): string
    {
        do {
            $slug = 'barbearia-'.Str::lower(Str::random(8));
        } while (Tenant::where('slug', $slug)->exists());

        return $slug;
    }
}
