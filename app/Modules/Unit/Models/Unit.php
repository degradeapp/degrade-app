<?php

namespace App\Modules\Unit\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unidade (local) de uma rede. Vive SEMPRE dentro de um tenant (BelongsToTenant);
 * a unidade nunca cruza tenant. Clientes e serviços são compartilhados na rede
 * (tenant-level); agenda e equipe são por unidade.
 */
class Unit extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function barbers()
    {
        return $this->hasMany(Barber::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
