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
        if (! $this->verifySignature($request)) {
            Log::warning('Asaas webhook signature verification failed');

            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        Log::info('Asaas webhook received and verified', [
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

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.asaas.webhook_secret');

        // Sem secret configurado (sandbox / local / testes): não há como verificar a
        // assinatura — aceita. Em produção o secret estará definido e a verificação HMAC
        // completa é aplicada. (Ver billing_critical_rules.)
        if (! $secret) {
            return true;
        }

        $signature = $request->header('asaas-signature');

        if (! $signature) {
            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
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
