<?php

namespace App\Modules\Barber\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BarberTimeOff extends Model
{
    use BelongsToTenant;

    protected $table = 'barber_time_off';

    protected $fillable = [
        'tenant_id',
        'barber_id',
        'date',
        'reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}
