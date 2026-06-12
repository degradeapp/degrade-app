<?php

namespace App\Modules\Appointment\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToTenant, SoftDeletes;

    /**
     * Bloco padrão (min) de um agendamento na agenda. Serviços NÃO têm duração
     * (é variável — o barbeiro decide na hora); usamos este bloco só para
     * renderizar a timeline e checar disponibilidade. É estimativa, não regra.
     */
    public const DEFAULT_BLOCK_MINUTES = 30;

    protected $fillable = [
        'tenant_id',
        'unit_id',
        'customer_id',
        'barber_id',
        'status',
        'source',
        'starts_at',
        'ends_at',
        'total_price',
        'notes',
        'completed_at',
        'deleted_by',
    ];

    protected $casts = [
        'status' => AppointmentStatus::class,
        'source' => AppointmentSource::class,
        'total_price' => 'decimal:2',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function barber()
    {
        return $this->belongsTo(Barber::class);
    }

    public function unit()
    {
        return $this->belongsTo(\App\Modules\Unit\Models\Unit::class);
    }

    public function services()
    {
        return $this->hasMany(AppointmentService::class);
    }

    /**
     * Status EFETIVO (derivado do horário): in_progress/completed vêm de
     * starts_at+ends_at vs agora, nunca persistidos. Estados finais (completed/
     * cancelled/no_show) não derivam. Usado na agenda E no detalhe para ficarem
     * consistentes — antes a agenda mostrava o persistido e divergia do detalhe.
     */
    public function effectiveStatus(): AppointmentStatus
    {
        if (in_array($this->status, [
            AppointmentStatus::completed,
            AppointmentStatus::cancelled,
            AppointmentStatus::no_show,
        ], true)) {
            return $this->status;
        }

        $now = Carbon::now();
        $start = $this->starts_at ? Carbon::parse($this->starts_at) : null;
        $end = $this->ends_at ? Carbon::parse($this->ends_at) : null;

        if ($start && $end && $now->between($start, $end)) {
            return AppointmentStatus::in_progress;
        }

        // Horário já passou mas ninguém concluiu: NÃO é "Concluído" (isso só
        // acontece na conclusão explícita, que gera comissão). Fica "A concluir"
        // para sinalizar que precisa de ação.
        if ($end && $now->greaterThanOrEqualTo($end)) {
            return AppointmentStatus::awaiting_completion;
        }

        return $this->status;
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'completed']);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')->where('starts_at', '>=', now());
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
