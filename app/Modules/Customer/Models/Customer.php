<?php

namespace App\Modules\Customer\Models;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'notes',
        'is_active',
        'total_visits',
        'total_spent',
        'last_visit_at',
        'deleted_by',
    ];

    protected $casts = [
        'total_visits' => 'int',
        'total_spent' => 'decimal:2',
        'last_visit_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
