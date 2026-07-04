<?php

namespace App\Http\Resources;

use App\Enums\BillingPlan;
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

    /**
     * Deriva do enum (fonte única): preço, limite e copy nunca divergem
     * entre a tela de cobrança e a regra de negócio.
     */
    private function getAvailablePlans(): array
    {
        return array_map(fn (BillingPlan $plan) => [
            'plan' => $plan->value,
            'label' => $plan->label(),
            'price' => $plan->price(),
            'staff_limit' => $plan->staffLimit(),
            'description' => $plan->description(),
        ], BillingPlan::cases());
    }
}
