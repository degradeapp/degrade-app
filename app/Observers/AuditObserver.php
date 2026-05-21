<?php

namespace App\Observers;

use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class AuditObserver
{
    public function created(Model $model): void
    {
        $tenantId = $model->tenant_id ?? app('tenant')?->id;
        if ($tenantId) {
            ActivityLogger::created($model, $tenantId);
        }
    }

    public function updated(Model $model): void
    {
        if (! $model->isDirty()) {
            return;
        }

        $tenantId = $model->tenant_id ?? app('tenant')?->id;
        if ($tenantId) {
            ActivityLogger::updated($model, $tenantId);
        }
    }

    public function deleted(Model $model): void
    {
        $tenantId = $model->tenant_id ?? app('tenant')?->id;
        if ($tenantId) {
            $reason = $model->getAttribute('deletion_reason') ?? null;
            ActivityLogger::deleted($model, $tenantId, $reason);
        }
    }
}
