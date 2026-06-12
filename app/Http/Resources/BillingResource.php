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
            'staff_limit' => $this->staffLimit(),
            'staff_count' => $this->staffCount(),
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
                'staff_limit' => 1,
                'description' => '1 profissional · agenda, lembrete no WhatsApp, comissões e caixa',
            ],
            [
                'plan' => 'barbearia',
                'label' => 'Barbearia',
                'price' => 119.00,
                'staff_limit' => 4,
                'description' => 'Até 4 profissionais · bot de WhatsApp 24h, relatórios completos e suporte prioritário',
            ],
            [
                'plan' => 'rede',
                'label' => 'Rede',
                'price' => 219.00,
                'staff_limit' => 10,
                'description' => 'Até 10 profissionais · várias unidades, relatório consolidado e suporte dedicado',
            ],
        ];
    }
}
