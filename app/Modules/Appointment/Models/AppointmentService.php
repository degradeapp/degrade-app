<?php

namespace App\Modules\Appointment\Models;

use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    use BelongsToTenant;

    protected $table = 'appointment_services';

    protected $fillable = [
        'tenant_id',
        'appointment_id',
        'service_id',
        'barber_id',
        'price_snapshot',
        'commission_percentage_snapshot',
    ];

    protected $casts = [
        'price_snapshot' => 'decimal:2',
        'commission_percentage_snapshot' => 'decimal:2',
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}
