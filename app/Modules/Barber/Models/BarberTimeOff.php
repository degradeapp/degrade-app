<?php

namespace App\Modules\Barber\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class BarberTimeOff extends Model
{
    use BelongsToTenant;

    protected $table = 'barber_time_off';

    protected $fillable = [
        'tenant_id',
        'barber_id',
        'date',
        'end_date',
        'reason',
    ];

    /**
     * Armazena como DATE puro (Y-m-d) e lê como Carbon.
     * Evita o cast 'date' gravar '00:00:00' no SQLite e quebrar comparações.
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? Carbon::parse($value) : null,
            set: fn ($value) => $value ? Carbon::parse($value)->toDateString() : null,
        );
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }
}
