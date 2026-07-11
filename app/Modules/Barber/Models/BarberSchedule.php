<?php

namespace App\Modules\Barber\Models;

use App\Enums\DayOfWeek;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class BarberSchedule extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'barber_id',
        'day_of_week',
        'start_time',
        'end_time',
    ];

    protected $casts = [
        'day_of_week' => DayOfWeek::class,
    ];

    /**
     * Coluna TIME volta '08:00:00' no PostgreSQL e '08:00' no SQLite; o app
     * inteiro (telas, inputs type=time, validação H:i) fala 'H:i'.
     */
    protected function startTime(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : substr($value, 0, 5));
    }

    protected function endTime(): Attribute
    {
        return Attribute::get(fn (?string $value) => $value === null ? null : substr($value, 0, 5));
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}
