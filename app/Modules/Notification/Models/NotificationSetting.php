<?php

namespace App\Modules\Notification\Models;

use App\Modules\Tenant\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'channels',
        'reminder_24h_before',
        'reminder_1h_before',
        'appointment_confirmed',
        'appointment_rescheduled',
        'appointment_cancelled',
        'email_from',
    ];

    protected $casts = [
        'channels' => 'array',
        'reminder_24h_before' => 'boolean',
        'reminder_1h_before' => 'boolean',
        'appointment_confirmed' => 'boolean',
        'appointment_rescheduled' => 'boolean',
        'appointment_cancelled' => 'boolean',
    ];
}
