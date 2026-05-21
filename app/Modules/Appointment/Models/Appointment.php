<?php

namespace App\Modules\Appointment\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'barber_id',
        'status',
        'source',
        'starts_at',
        'ends_at',
        'total_price',
        'notes',
        'completed_at',
        'deleted_by',
    ];

    protected $casts = [
        'status' => AppointmentStatus::class,
        'source' => AppointmentSource::class,
        'total_price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function services()
    {
        return $this->hasMany(AppointmentService::class);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'completed']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')->where('starts_at', '>=', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
