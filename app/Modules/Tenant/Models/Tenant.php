<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model {
    protected $fillable = [
        'name',
        'slug',
        'status',
        'trial_ends_at',
        'asaas_customer_id',
        'settings',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function users() {
        return $this->hasMany(\App\Modules\User\Models\User::class);
    }

    public function isTrialing(): bool {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isActive(): bool {
        return $this->status === 'active';
    }

    public function isPastDue(): bool {
        return $this->status === 'past_due';
    }

    public function isSuspended(): bool {
        return $this->status === 'suspended';
    }

    public function isCancelled(): bool {
        return $this->status === 'cancelled';
    }

    public function setting(string $key, mixed $default = null): mixed {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }
}
