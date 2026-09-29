<?php

namespace App\Modules\Tenant\Scopes;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = null;

        if (app()->has('tenant')) {
            $tenantId = app('tenant')->id;
        } elseif (auth()->hasUser()) {
            // hasUser() checa o usuário já resolvido SEM dispará-lo. Usar check()
            // aqui causaria recursão infinita: o provider de auth consulta o model
            // User (que tem este scope) → check() resolve o user → reaplica o scope…
            $tenantId = auth()->user()->tenant_id;
        }

        if ($tenantId) {
            $builder->where("{$model->getTable()}.tenant_id", $tenantId);

            return;
        }

        // Sem contexto de tenant numa requisição HTTP: FALHA FECHADO (nenhuma linha).
        // Antes caía sem filtro nenhum — uma rota pública nova que esquecesse de fixar
        // o tenant listaria dados de TODAS as barbearias. Console/fila (jobs, comandos,
        // scheduler) não têm usuário e filtram por tenant_id explicitamente. User fica
        // de fora: o login e o provider de auth PRECISAM achar o usuário (por id/email)
        // antes de existir contexto — é dele que o tenant sai.
        if ((! app()->runningInConsole() || app()->runningUnitTests()) && ! $model instanceof User) {
            $builder->whereRaw('1 = 0');
        }
    }
}
