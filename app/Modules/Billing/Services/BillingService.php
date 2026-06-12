<?php

namespace App\Modules\Billing\Services;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingService
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key') ?? '';
        $this->baseUrl = config('services.asaas.sandbox') ? 'https://sandbox.asaas.com/api/v3' : 'https://api.asaas.com/api/v3';
    }

    public function createCustomer(Tenant $tenant): string
    {
        try {
            $owner = $tenant->users()->where('role', 'owner')->first();

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'access_token' => $this->apiKey,
            ])->asForm()->post($this->baseUrl.'/customers', [
                'name' => $tenant->name,
                'email' => $owner?->email ?? 'noemail@example.com',
                'phone' => '11999999999', // Placeholder: should be from owner profile
                'cpfCnpj' => '00000000000000', // Placeholder: should be from tenant settings
            ])
                ->throw()
                ->json();

            $customerId = $response['id'] ?? null;

            if (! $customerId) {
                throw new \Exception('Asaas: No customer ID returned');
            }

            Log::info('Asaas customer created', [
                'tenant_id' => $tenant->id,
                'customer_id' => $customerId,
            ]);

            return $customerId;
        } catch (RequestException $e) {
            Log::error('Asaas customer creation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->response->json(),
            ]);

            throw new \Exception('Erro ao criar cliente no Asaas: '.$e->getMessage());
        }
    }

    public function createSubscription(Tenant $tenant, BillingPlan $plan): array
    {
        try {
            $customerId = $tenant->asaas_customer_id;

            if (! $customerId) {
                throw new \Exception('No customer ID found for tenant');
            }

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'access_token' => $this->apiKey,
            ])->asForm()->post($this->baseUrl.'/subscriptions', [
                'customerId' => $customerId,
                'billingType' => 'UNDEFINED',
                'value' => $plan->price(),
                'nextDueDate' => now()->addMonth()->format('Y-m-d'),
                'cycle' => 'MONTHLY',
                'description' => 'Plano '.$plan->label(),
            ])
                ->throw()
                ->json();

            $subscriptionId = $response['id'] ?? null;
            $status = $response['status'] ?? 'ACTIVE';
            $nextDueDate = $response['nextDueDate'] ?? now()->addMonth()->toDateString();

            Log::info('Asaas subscription created', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscriptionId,
                'plan' => $plan->value,
            ]);

            return [
                'id' => $subscriptionId,
                'status' => $status,
                'next_due_date' => $nextDueDate,
            ];
        } catch (RequestException $e) {
            Log::error('Asaas subscription creation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->response->json(),
            ]);

            throw new \Exception('Erro ao criar assinatura no Asaas: '.$e->getMessage());
        }
    }

    public function updateSubscriptionStatus(Tenant $tenant, string $status): void
    {
        $mappedStatus = $this->mapAsaasStatusToTenantStatus($status);

        $tenant->update([
            'status' => $mappedStatus,
        ]);

        Log::info('Subscription status updated', [
            'tenant_id' => $tenant->id,
            'asaas_status' => $status,
            'tenant_status' => $mappedStatus,
        ]);
    }

    public function cancelSubscription(Tenant $tenant): void
    {
        try {
            if (! $tenant->asaas_subscription_id) {
                throw new \Exception('No subscription ID found for tenant');
            }

            Http::withHeaders(['access_token' => $this->apiKey])
                ->delete($this->baseUrl.'/subscriptions/'.$tenant->asaas_subscription_id)
                ->throw();

            $tenant->update(['status' => 'cancelled']);

            Log::info('Asaas subscription cancelled', [
                'tenant_id' => $tenant->id,
                'subscription_id' => $tenant->asaas_subscription_id,
            ]);
        } catch (RequestException $e) {
            Log::error('Asaas subscription cancellation failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->response?->json(),
            ]);

            throw new \Exception('Erro ao cancelar assinatura no Asaas');
        }
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
