<?php

namespace App\Modules\Billing\Services;

use App\Enums\BillingPlan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente da API v3 do Asaas. Contrato conferido na documentação oficial
 * (docs.asaas.com), não "de cabeça": corpo JSON, header access_token, header
 * User-Agent (obrigatório pra contas criadas após jun/2024), cliente em
 * POST /customers com cpfCnpj válido, assinatura em POST /subscriptions com o
 * campo `customer` (NÃO customerId) e troca de valor via PUT /subscriptions/{id}.
 */
class BillingService
{
    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.asaas.api_key') ?? '';
        $this->baseUrl = config('services.asaas.sandbox') ? 'https://sandbox.asaas.com/api/v3' : 'https://api.asaas.com/api/v3';
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->withHeaders([
                'access_token' => $this->apiKey,
                'User-Agent' => 'Degrade/1.0',
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(5)
            ->timeout(15);
    }

    public function createCustomer(Tenant $tenant): string
    {
        $owner = $tenant->users()->where('role', 'owner')->first();
        $phone = $owner ? $tenant->barbers()->where('user_id', $owner->id)->value('phone') : null;

        $payload = array_filter([
            'name' => $tenant->name,
            'cpfCnpj' => $tenant->billing_document,
            'email' => $owner?->email,
            'mobilePhone' => $phone,
            'externalReference' => (string) $tenant->id,
        ]);

        $customerId = $this->send(fn () => $this->client()->post('/customers', $payload), 'criar cliente', $tenant)['id'] ?? null;

        if (! $customerId) {
            throw new \RuntimeException('Asaas: cliente criado sem id.');
        }

        Log::info('Asaas customer created', ['tenant_id' => $tenant->id, 'customer_id' => $customerId]);

        return $customerId;
    }

    /**
     * Atualiza o CPF/CNPJ de um cliente que já existe no Asaas (o dono corrigiu o documento).
     */
    public function updateCustomerDocument(Tenant $tenant): void
    {
        $this->send(
            fn () => $this->client()->put('/customers/'.$tenant->asaas_customer_id, ['cpfCnpj' => $tenant->billing_document]),
            'atualizar cliente',
            $tenant,
        );
    }

    /**
     * @return array{id: string, next_due_date: string}
     */
    public function createSubscription(Tenant $tenant, BillingPlan $plan): array
    {
        if (! $tenant->asaas_customer_id) {
            throw new \RuntimeException('Tenant sem cliente no Asaas.');
        }

        $response = $this->send(fn () => $this->client()->post('/subscriptions', [
            'customer' => $tenant->asaas_customer_id,
            'billingType' => 'UNDEFINED', // o dono escolhe Pix, boleto ou cartão na fatura
            'value' => $plan->price(),
            'nextDueDate' => $this->firstDueDate($tenant),
            'cycle' => 'MONTHLY',
            'description' => 'Degradê - Plano '.$plan->label(),
            'externalReference' => (string) $tenant->id,
        ]), 'criar assinatura', $tenant);

        if (empty($response['id'])) {
            throw new \RuntimeException('Asaas: assinatura criada sem id.');
        }

        Log::info('Asaas subscription created', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $response['id'],
            'plan' => $plan->value,
        ]);

        return [
            'id' => $response['id'],
            'next_due_date' => $response['nextDueDate'] ?? $this->firstDueDate($tenant),
        ];
    }

    /**
     * Troca de plano NA MESMA assinatura. Apagar e recriar (o jeito antigo) disparava
     * SUBSCRIPTION_DELETED da antiga e cobrava a nova no mesmo mês; aqui só muda o
     * valor, inclusive da fatura que ainda está em aberto.
     */
    public function changeSubscriptionPlan(Tenant $tenant, BillingPlan $plan): void
    {
        $this->send(fn () => $this->client()->put('/subscriptions/'.$tenant->asaas_subscription_id, [
            'value' => $plan->price(),
            'description' => 'Degradê - Plano '.$plan->label(),
            'updatePendingPayments' => true,
        ]), 'trocar plano', $tenant);

        Log::info('Asaas subscription plan changed', [
            'tenant_id' => $tenant->id,
            'subscription_id' => $tenant->asaas_subscription_id,
            'plan' => $plan->value,
        ]);
    }

    /**
     * Fatura em aberto mais antiga da assinatura (link que o dono abre pra pagar).
     *
     * @return array{url: ?string, due_date: ?string}
     */
    public function openInvoice(Tenant $tenant): array
    {
        $response = $this->send(
            fn () => $this->client()->get('/subscriptions/'.$tenant->asaas_subscription_id.'/payments'),
            'buscar faturas',
            $tenant,
        );

        $open = collect($response['data'] ?? [])
            ->filter(fn ($p) => in_array($p['status'] ?? null, ['PENDING', 'OVERDUE'], true))
            ->sortBy('dueDate')
            ->first();

        return [
            'url' => $open['invoiceUrl'] ?? null,
            'due_date' => $open['dueDate'] ?? null,
        ];
    }

    public function deleteRemoteSubscription(string $subscriptionId): void
    {
        $this->client()->delete('/subscriptions/'.$subscriptionId)->throw();
    }

    public function cancelSubscription(Tenant $tenant): void
    {
        if (! $tenant->asaas_subscription_id) {
            throw new \RuntimeException('Tenant sem assinatura no Asaas.');
        }

        $subscriptionId = $tenant->asaas_subscription_id;

        try {
            $this->deleteRemoteSubscription($subscriptionId);
        } catch (RequestException $e) {
            // Já removida lá (404): o estado desejado já existe, segue o cancelamento local.
            if ($e->response->status() !== 404) {
                Log::error('Asaas subscription cancellation failed', [
                    'tenant_id' => $tenant->id,
                    'status' => $e->response->status(),
                ]);

                throw new \RuntimeException('Erro ao cancelar assinatura no Asaas', previous: $e);
            }
        }

        // Limpa o id: uma nova assinatura depois do cancelamento cria outra do zero, e o
        // webhook SUBSCRIPTION_DELETED desta aqui passa a ser reconhecido como antigo.
        // Quem estava ATIVO mantém o next_due_date: ele marca o fim do período já pago,
        // e o acesso segue até lá (Tenant::hasAccess, Termos §4). Quem não pagou nada
        // (trial, vencido) não tem período pago a preservar.
        $tenant->update([
            'status' => 'cancelled',
            'asaas_subscription_id' => null,
            'payment_url' => null,
            'next_due_date' => $tenant->isActive() ? $tenant->next_due_date : null,
        ]);

        Log::info('Asaas subscription cancelled', ['tenant_id' => $tenant->id, 'subscription_id' => $subscriptionId]);
    }

    /**
     * 1ª cobrança: no fim do trial se ele ainda está valendo (o teste grátis é o teste
     * grátis, sem mês extra de brinde); senão, hoje.
     */
    public function firstDueDate(Tenant $tenant): string
    {
        $today = Carbon::today();

        if ($tenant->status === 'trial' && $tenant->trial_ends_at?->copy()->startOfDay()->gt($today)) {
            return $tenant->trial_ends_at->toDateString();
        }

        return $today->toDateString();
    }

    private function send(callable $request, string $action, Tenant $tenant): array
    {
        try {
            return $request()->throw()->json() ?? [];
        } catch (RequestException $e) {
            // Só os códigos/descrições de erro do Asaas: o corpo da requisição tem CPF/CNPJ.
            Log::error("Asaas: falha ao {$action}", [
                'tenant_id' => $tenant->id,
                'status' => $e->response->status(),
                'errors' => $e->response->json('errors'),
            ]);

            throw new AsaasException($e->response->json('errors.0.description'), previous: $e);
        }
    }
}
