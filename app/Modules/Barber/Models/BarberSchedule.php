<?php

namespace App\Modules\Barber\Models;

use App\Enums\DayOfWeek;
use Illuminate\Database\Eloquent\Model;

class BarberSchedule extends Model
{
    protected $fillable = [
        'barber_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'day_of_week' => DayOfWeek::class,
    ];

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}
