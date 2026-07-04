<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            // Foto da equipe: usa a foto própria do barbeiro; se não tiver, cai na foto de
            // conta do usuário vinculado — assim atualizar o avatar em "Meu perfil" reflete aqui.
            'photo_url' => $this->photoUrl() ?? optional($this->user)->avatarUrl(),
            'default_commission_percentage' => $this->default_commission_percentage,
            'is_active' => $this->is_active,
            'user_id' => $this->user_id,
            'schedules' => BarberScheduleResource::collection($this->whenLoaded('schedules')),
            'time_offs' => $this->whenLoaded('timeOffs', fn () => $this->timeOffs->map(fn ($t) => [
                'date' => $t->date instanceof Carbon ? $t->date->toDateString() : $t->date,
                'end_date' => $t->end_date instanceof Carbon ? $t->end_date->toDateString() : $t->end_date,
                'reason' => $t->reason,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
