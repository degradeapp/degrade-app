<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration_minutes' => $this->duration_minutes,
            'price' => $this->price,
            'commission_percentage' => $this->commission_percentage,
            'is_active' => $this->is_active,
            'barbers' => BarberServiceResource::collection($this->whenLoaded('barbers')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
