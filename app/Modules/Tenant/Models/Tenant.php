<?php

namespace App\Modules\Tenant\Models;

use App\Enums\BillingPlan;
use App\Modules\Barber\Models\Barber;
use App\Modules\User\Models\User;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): TenantFactory
    {
        return TenantFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'status',
        'trial_ends_at',
        'asaas_customer_id',
        'asaas_subscription_id',
        'plan',
        'settings',
        'onboarding_completed_at',
        'purge_scheduled_at',
    ];

    public function logoUrl(): ?string
    {
        return $this->logo_path ? url('/media/'.$this->logo_path) : null;
    }

    protected $casts = [
        'settings' => 'array',
        'trial_ends_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'purge_scheduled_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function barbers()
    {
        return $this->hasMany(Barber::class);
    }

    public function units()
    {
        return $this->hasMany(\App\Modules\Unit\Models\Unit::class);
    }

    public function whatsappAccount()
    {
        return $this->hasOne(WhatsappAccount::class);
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

    public function staffLimit(): int
    {
        return $this->currentPlan()?->staffLimit() ?? 0;
    }

    /**
     * Limite efetivo usado para bloquear criação: sem plano pago ativo
     * (trial ou ainda sem plano) cai no limite do Barbearia, para o usuário
     * conseguir explorar antes de assinar. Com plano, vale o limite do plano.
     */
    public function effectiveStaffLimit(): int
    {
        return ($this->currentPlan() ?? BillingPlan::barbearia)->staffLimit();
    }

    /**
     * Total de pessoas na barbearia: todo usuário com login (dono, gerente,
     * recepção, barbeiro-com-login) + barbeiros sem login (só na agenda).
     * Um barbeiro vinculado a um usuário conta uma vez (como usuário).
     */
    public function staffCount(): int
    {
        $users = $this->users()->count();
        $barbersWithoutLogin = $this->barbers()->where('is_active', true)->whereNull('user_id')->count();

        return $users + $barbersWithoutLogin;
    }

    public function canAddBarber(): bool
    {
        return $this->staffCount() < $this->effectiveStaffLimit();
    }

    /**
     * Limite de unidades do plano. Só o Rede tem várias unidades; Solo/Barbearia = 1.
     * Sem plano (trial) cai no Barbearia (1), pra não liberar multiunidade antes de assinar.
     */
    public function effectiveUnitLimit(): int
    {
        return ($this->currentPlan() ?? BillingPlan::barbearia)->unitLimit();
    }

    public function unitsCount(): int
    {
        return $this->units()->count();
    }

    public function canAddUnit(): bool
    {
        return $this->unitsCount() < $this->effectiveUnitLimit();
    }

    public function canAddTeamMember(): bool
    {
        return $this->staffCount() < $this->effectiveStaffLimit();
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
