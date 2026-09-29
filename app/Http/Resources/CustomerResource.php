<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'total_visits' => $this->total_visits,
            // Quanto o cliente gastou é faturamento: recepção e barbeiro não veem.
            'total_spent' => $this->when((bool) $request->user()?->canSeeFinance(), $this->total_spent),
            'last_visit_at' => $this->last_visit_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
