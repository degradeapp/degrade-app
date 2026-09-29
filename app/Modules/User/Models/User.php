<?php

namespace App\Modules\User\Models;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Modules\User\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use BelongsToTenant, HasApiTokens, HasFactory;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'role' => UserRole::class,
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    // Dono e barbeiros-com-login têm um registro de Barber vinculado: é a MESMA
    // pessoa (perfil de conta = membro da equipe). Usado pra manter nome/telefone/foto
    // em sincronia entre "Meu perfil" e "Equipe".
    public function barber()
    {
        return $this->hasOne(Barber::class);
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? url('/media/'.$this->avatar_path) : null;
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::owner;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::manager;
    }

    public function isReceptionist(): bool
    {
        return $this->role === UserRole::receptionist;
    }

    public function isBarber(): bool
    {
        return $this->role === UserRole::barber;
    }

    /**
     * Quem vê dinheiro e dados pessoais da equipe: dono e gerente. Recepção e barbeiro
     * são operacionais (agenda + clientes) — não veem faturamento, quanto cada cliente
     * gastou, a comissão dos colegas nem o celular pessoal deles. Fonte única da regra
     * (dashboard, clientes, agenda, busca).
     */
    public function canSeeFinance(): bool
    {
        return $this->isOwner() || $this->isManager();
    }
}
