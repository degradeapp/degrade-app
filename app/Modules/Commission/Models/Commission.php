<?php

namespace App\Modules\Commission\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'barber_id',
        'appointment_id',
        'reference_type',
        'status',
        'amount',
        'reference_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'reference_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid')->whereNotNull('paid_at');
    }

    public function scopeByBarber($query, int $barberId)
    {
        return $query->where('barber_id', $barberId);
    }

    public function scopeByMonth($query, int $year, int $month)
    {
        return $query->whereYear('reference_date', $year)
            ->whereMonth('reference_date', $month);
    }
}
