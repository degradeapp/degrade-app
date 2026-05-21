<?php

namespace App\Modules\Customer\Models;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'is_active',
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
