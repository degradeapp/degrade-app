<?php

namespace App\Modules\Service\Models;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'commission_percentage',
        'is_active',
        'deleted_by',
    ];

    protected $casts = [
        'duration_minutes' => 'int',
        'price' => 'decimal:2',
        'commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function barbers()
    {
        return $this->belongsToMany(Barber::class, 'barber_service')
            ->withPivot('commission_percentage')
            ->withTimestamps();
    }
}
