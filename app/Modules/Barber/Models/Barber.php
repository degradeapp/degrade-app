<?php

namespace App\Modules\Barber\Models;

use App\Modules\Appointment\Models\Appointment;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Modules\User\Models\User;
use Database\Factories\BarberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Barber extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected static function newFactory(): BarberFactory
    {
        return BarberFactory::new();
    }

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'user_id',
        'name',
        'phone',
        'photo_path',
        'default_commission_percentage',
        'is_active',
        'deleted_by',
    ];

    public function photoUrl(): ?string
    {
        return $this->photo_path ? url('/media/'.$this->photo_path) : null;
    }

    protected $casts = [
        'default_commission_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unit()
    {
        return $this->belongsTo(\App\Modules\Unit\Models\Unit::class);
    }

    public function schedules()
    {
        return $this->hasMany(BarberSchedule::class);
    }

    public function timeOffs()
    {
        return $this->hasMany(BarberTimeOff::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'barber_service')
            ->withPivot('commission_percentage')
            ->withTimestamps();
    }
}
