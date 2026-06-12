<?php

namespace App\Modules\Service\Models;

use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'price',
        'commission_percentage',
        'is_active',
        'deleted_by',
    ];

    protected $casts = [
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
