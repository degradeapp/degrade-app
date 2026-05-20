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
            if (config('app.env') !== 'testing') {
                if ($model->isDirty('tenant_id') && $model->getOriginal('tenant_id') !== app('tenant')->id) {
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
