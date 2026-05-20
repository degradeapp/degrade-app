<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarberScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'day_of_week' => $this->day_of_week->value,
            'day_of_week_label' => $this->day_of_week->label(),
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
        ];
    }
}
