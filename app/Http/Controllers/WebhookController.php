<?php

namespace App\Http\Controllers;

use App\Modules\Billing\Services\BillingService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    public function handleAsaasWebhook(Request $request): Response
    {
        // Stub: Skip Asaas signature validation for now
        // TODO: Implement signature validation with config('services.asaas.webhook_secret')

        $event = $request->input('event');
        $data = $request->input('data', []);

        Log::info('Asaas webhook received (STUB)', [
            'event' => $event,
            'customer_id' => $data['customer'] ?? null,
        ]);

        if ($event === 'subscription.payment_received') {
            $this->handlePaymentReceived($data);
        } elseif ($event === 'subscription.payment_overdue') {
            $this->handlePaymentOverdue($data);
        } elseif ($event === 'subscription.cancelled') {
            $this->handleSubscriptionCancelled($data);
        }

        return response('', Response::HTTP_OK);
    }

    private function handlePaymentReceived(array $data): void
    {
        $customerId = $data['customer'] ?? null;
        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if ($tenant) {
            $this->billingService->updateSubscriptionStatus($tenant, 'ACTIVE');
            Log::info('Payment received for tenant', ['tenant_id' => $tenant->id]);
        }
    }

    private function handlePaymentOverdue(array $data): void
    {
        $customerId = $data['customer'] ?? null;
        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if ($tenant) {
            $this->billingService->updateSubscriptionStatus($tenant, 'OVERDUE');
            Log::info('Payment overdue for tenant', ['tenant_id' => $tenant->id]);
        }
    }

    private function handleSubscriptionCancelled(array $data): void
    {
        $customerId = $data['customer'] ?? null;
        $tenant = Tenant::where('asaas_customer_id', $customerId)->first();

        if ($tenant) {
            $this->billingService->updateSubscriptionStatus($tenant, 'CANCELLED');
            Log::info('Subscription cancelled for tenant', ['tenant_id' => $tenant->id]);
        }
    }
}
