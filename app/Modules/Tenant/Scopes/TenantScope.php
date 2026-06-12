<?php

namespace App\Modules\Tenant\Scopes;

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
        }
    }
}
