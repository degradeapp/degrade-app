<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ActivityLogger
{
    public static function log(
        int $tenantId,
        string $action,
        string $modelType,
        int $modelId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): void {
        $user = Auth::user();
        $request = request();

        DB::table('activity_log')->insert([
            'tenant_id' => $tenantId,
            'user_id' => $user?->id,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ? json_encode($metadata) : null,
            'created_at' => now(),
        ]);
    }

    public static function created(Model $model, int $tenantId): void
    {
        self::log(
            $tenantId,
            'created',
            $model::class,
            $model->id,
            null,
            $model->getAttributes()
        );
    }

    public static function updated(Model $model, int $tenantId): void
    {
        $changes = [];
        foreach ($model->getChanges() as $key => $value) {
            $changes[$key] = [
                'old' => $model->getOriginal($key),
                'new' => $value,
            ];
        }

        self::log(
            $tenantId,
            'updated',
            $model::class,
            $model->id,
            $model->getOriginal(),
            $model->getAttributes(),
            ['changes' => $changes]
        );
    }

    public static function deleted(Model $model, int $tenantId, ?string $reason = null): void
    {
        self::log(
            $tenantId,
            'deleted',
            $model::class,
            $model->id,
            $model->getAttributes(),
            null,
            $reason ? ['reason' => $reason] : null
        );
    }

    public static function custom(
        int $tenantId,
        string $action,
        string $modelType,
        int $modelId,
        ?array $metadata = null
    ): void {
        self::log(
            $tenantId,
            $action,
            $modelType,
            $modelId,
            null,
            null,
            $metadata
        );
    }
}
