<?php

namespace App\Http\Controllers;

use App\Enums\BillingPlan;
use App\Events\SubscriptionCreated;
use App\Http\Requests\SelectPlanRequest;
use App\Http\Resources\BillingResource;
use App\Modules\Billing\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class BillingController extends Controller
{
    public function __construct(private BillingService $billingService) {}

    public function show(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        return response()->json(['data' => new BillingResource($tenant)]);
    }

    public function selectPlan(SelectPlanRequest $request): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        $this->authorize('selectPlan', $tenant);

        try {
            $plan = BillingPlan::from($request->input('plan'));

            if (! $tenant->asaas_customer_id) {
                $customerId = $this->billingService->createCustomer($tenant);
                $tenant->update(['asaas_customer_id' => $customerId]);
            }

            $subscription = $this->billingService->createSubscription($tenant, $plan);

            // SEGURANÇA: nunca ativar com base na seleção do plano (frontend).
            // O webhook subscription.payment_received do Asaas é a ÚNICA fonte de
            // verdade para status=active. Aqui só registramos o plano escolhido e a
            // assinatura criada (pagamento ainda PENDENTE). O tenant mantém o status
            // atual (trial) até o pagamento ser confirmado pelo webhook.
            $tenant->update([
                'plan' => $plan->value,
                'asaas_subscription_id' => $subscription['id'] ?? null,
            ]);

            SubscriptionCreated::dispatch($tenant, $plan);

            return response()->json(
                ['data' => new BillingResource($tenant)],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Erro ao processar pagamento. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function cancel(): JsonResponse
    {
        $tenant = auth()->user()->tenant;

        $this->authorize('cancelPlan', $tenant);

        if (! $tenant->asaas_subscription_id) {
            return response()->json(
                ['message' => 'Você não tem uma assinatura ativa para cancelar.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        try {
            // Cancela a recorrência no Asaas e marca a barbearia como cancelada.
            // A partir daí o acesso às telas pagas fica bloqueado (EnsureActiveSubscription).
            $this->billingService->cancelSubscription($tenant);

            return response()->json(['data' => new BillingResource($tenant->fresh())]);
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Não foi possível cancelar agora. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
