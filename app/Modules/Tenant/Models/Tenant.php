<?php

namespace App\Modules\Tenant\Models;

use App\Enums\BillingPlan;
use App\Modules\Barber\Models\Barber;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'status',
        'trial_ends_at',
        'asaas_customer_id',
        'asaas_subscription_id',
        'plan',
        'settings',
        'onboarding_completed_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function barbers()
    {
        return $this->hasMany(Barber::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function setSetting(string $key, mixed $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->settings = $settings;
        $this->save();
    }

    public function currentPlan(): ?BillingPlan
    {
        return $this->plan ? BillingPlan::from($this->plan) : null;
    }

    public function barberLimit(): int
    {
        return $this->currentPlan()?->barberLimit() ?? 0;
    }

    public function canAddBarber(): bool
    {
        if (! $this->currentPlan()) {
            return false;
        }

        $currentCount = $this->barbersCount();

        return $currentCount < $this->barberLimit();
    }

    public function barbersCount(): int
    {
        return $this->barbers()->where('is_active', true)->count();
    }

    public function isSubscriptionActive(): bool
    {
        return $this->status === 'active';
    }

    public function isTrialExpired(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isPast();
    }
}
