<?php

namespace App\Modules\Whatsapp\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Traits\BelongsToTenant;
use App\Modules\Whatsapp\Enums\WhatsappBotState;
use Illuminate\Database\Eloquent\Model;

class WhatsappConversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'phone_number',
        'state',
        'session_data',
        'last_interaction_at',
        'idle_at',
    ];

    protected $casts = [
        'state' => WhatsappBotState::class,
        'session_data' => 'array',
        'last_interaction_at' => 'datetime',
        'idle_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id');
    }
}
