<?php

namespace App\Modules\Billing\Services;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Log;

class BillingService
{
    private string $apiKey;

    private bool $sandbox;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key', '');
        $this->sandbox = config('services.asaas.sandbox', true);
    }

    public function createCustomer(Tenant $tenant): string
    {
        Log::info('BillingService: Creating Asaas customer (STUB)', [
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
        ]);

        // Stub: return fake customer ID
        return 'cust_stub_'.uniqid();
    }

    public function createSubscription(Tenant $tenant, BillingPlan $plan): array
    {
        Log::info('BillingService: Creating Asaas subscription (STUB)', [
            'tenant_id' => $tenant->id,
            'plan' => $plan->value,
            'price' => $plan->price(),
        ]);

        // Stub: return mock subscription data
        return [
            'id' => 'sub_stub_'.uniqid(),
            'status' => 'ACTIVE',
            'next_due_date' => now()->addMonth()->toDateString(),
            'created_at' => now()->toIso8601String(),
        ];
    }

    public function updateSubscriptionStatus(Tenant $tenant, string $status): void
    {
        $mappedStatus = $this->mapAsaasStatusToTenantStatus($status);

        $tenant->update([
            'status' => $mappedStatus,
        ]);

        Log::info('BillingService: Updated subscription status', [
            'tenant_id' => $tenant->id,
            'asaas_status' => $status,
            'tenant_status' => $mappedStatus,
        ]);
    }

    public function cancelSubscription(Tenant $tenant): void
    {
        Log::info('BillingService: Cancelling Asaas subscription (STUB)', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $tenant->asaas_subscription_id,
        ]);
    }

    private function mapAsaasStatusToTenantStatus(string $asaasStatus): string
    {
        return match ($asaasStatus) {
            'ACTIVE' => 'active',
            'PENDING' => 'active',
            'OVERDUE' => 'past_due',
            'CANCELLED' => 'cancelled',
            default => 'past_due',
        };
    }
}
