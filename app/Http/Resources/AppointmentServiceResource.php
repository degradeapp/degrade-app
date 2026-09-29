<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service' => [
                'id' => $this->service->id,
                'name' => $this->service->name,
                'duration_minutes' => $this->service->duration_minutes,
            ],
            // Sem celular do barbeiro (dado pessoal do colega); a comissão de cada um só
            // pra quem vê finanças — recepção e barbeiro não sabem quanto o colega ganha.
            'barber' => $this->barber ? [
                'id' => $this->barber->id,
                'name' => $this->barber->name,
            ] : null,
            'price_snapshot' => $this->price_snapshot,
            'commission_percentage_snapshot' => $this->when(
                (bool) $request->user()?->canSeeFinance(),
                $this->commission_percentage_snapshot,
            ),
        ];
    }
}
