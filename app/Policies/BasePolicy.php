<?php

namespace App\Policies;

use App\Modules\User\Enums\UserRole;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    /**
     * Dono pode tudo — MAS só dentro do próprio tenant. O before() antigo
     * retornava true incondicional: como o route model binding já é filtrado
     * pelo TenantScope isso não era explorável pelas rotas normais, porém
     * qualquer chamada futura com withoutGlobalScopes()/withTrashed() viraria
     * um bypass cross-tenant silencioso. Defesa em profundidade: o atalho do
     * dono nunca atravessa a fronteira do tenant.
     */
    public function before(User $user, string $ability, mixed ...$arguments): ?bool
    {
        if ($user->role !== UserRole::owner) {
            return null;
        }

        $model = $arguments[0] ?? null;

        // Modelo com tenant_id: o atalho só vale se for do MESMO tenant.
        if ($model instanceof Model && $model->getAttribute('tenant_id') !== null) {
            return (int) $model->getAttribute('tenant_id') === (int) $user->tenant_id ? true : null;
        }

        return true;
    }
}
