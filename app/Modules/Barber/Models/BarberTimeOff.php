<?php

namespace App\Modules\Barber\Models;

use Illuminate\Database\Eloquent\Model;

class BarberTimeOff extends Model
{
    protected $table = 'barber_time_off';

    protected $fillable = [
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
