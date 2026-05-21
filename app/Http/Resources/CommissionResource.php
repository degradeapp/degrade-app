<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barber' => [
                'id' => $this->barber->id,
                'name' => $this->barber->name,
            ],
            'amount' => $this->amount,
            'status' => $this->status,
            'reference_type' => $this->reference_type,
            'reference_date' => $this->reference_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'appointment_id' => $this->appointment_id,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
