<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $currentPlan = $this->currentPlan();

        return [
            'current_plan' => $this->plan,
            'current_price' => $currentPlan?->price(),
            'barber_limit' => $this->barberLimit(),
            'barbers_count' => $this->barbersCount(),
            'status' => $this->status,
            'trial_ends_at' => $this->status === 'trial' ? $this->trial_ends_at?->toIso8601String() : null,
            'asaas_subscription_id' => $this->asaas_subscription_id,
            'available_plans' => $this->getAvailablePlans(),
        ];
    }

    private function getAvailablePlans(): array
    {
        return [
            [
                'plan' => 'solo',
                'label' => 'Solo',
                'price' => 59.00,
                'barber_limit' => 1,
                'description' => '1 barbeiro, WhatsApp lembretes, email support',
            ],
            [
                'plan' => 'barbearia',
                'label' => 'Barbearia ⭐',
                'price' => 119.00,
                'barber_limit' => 4,
                'description' => '4 barbeiros, bot 24h, múltiplos serviços, comissões, inbox WhatsApp',
            ],
            [
                'plan' => 'rede',
                'label' => 'Rede',
                'price' => 219.00,
                'barber_limit' => 10,
                'description' => '10 barbeiros, múltiplas unidades, API pública, onboarding dedicado',
            ],
        ];
    }
}
