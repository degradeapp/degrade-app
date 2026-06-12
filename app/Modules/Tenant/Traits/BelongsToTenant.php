<?php

namespace App\Modules\Tenant\Traits;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Scopes\TenantScope;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->getAttribute('tenant_id') && app()->has('tenant')) {
                $model->setAttribute('tenant_id', app('tenant')->id);
            }
        });

        static::updating(function ($model) {
            // tenant_id é imutável após criado (independente de ambiente).
            if ($model->isDirty('tenant_id')) {
                $original = $model->getOriginal('tenant_id');
                if ($original !== null && (int) $original !== (int) $model->getAttribute('tenant_id')) {
                    throw new \Exception('Cannot change tenant_id of existing record');
                }
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
