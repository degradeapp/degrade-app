<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effectiveStatus = $this->effectiveStatus();

        return [
            'id' => $this->id,
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ],
            'barber' => $this->barber ? [
                'id' => $this->barber->id,
                'name' => $this->barber->name,
                'phone' => $this->barber->phone,
            ] : null,
            // resolve() achata pra array puro — sem o wrapper "data" que uma resource
            // collection aninhada adicionaria e que quebrava o consumo no frontend.
            'services' => $this->whenLoaded('services', fn () => AppointmentServiceResource::collection($this->services)->resolve()),
            'status' => $effectiveStatus->value,
            'status_label' => $effectiveStatus->label(),
            'persisted_status' => $this->status->value,
            'source' => $this->source->value,
            'source_label' => $this->source->label(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'total_price' => $this->total_price,
            'notes' => $this->notes,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

}
