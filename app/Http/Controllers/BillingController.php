<?php

namespace App\Http\Controllers;

use App\Enums\BillingPlan;
use App\Events\SubscriptionCreated;
use App\Http\Requests\SelectPlanRequest;
use App\Http\Resources\BillingResource;
use App\Modules\Billing\Services\AsaasException;
use App\Modules\Billing\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

        $plan = BillingPlan::from($request->input('plan'));

        // Downgrade não pode deixar a barbearia acima do teto do novo plano (o teto é
        // o ÚNICO diferencial entre Solo e Barbearia).
        if ($tenant->staffCount() > $plan->staffLimit()) {
            return response()->json([
                'message' => "O plano {$plan->label()} permite até {$plan->staffLimit()} profissional(is). Desative quem não usa mais antes de trocar.",
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Toque duplo no 3G / duas abas: sem a trava, dois requests criam duas
        // assinaturas no Asaas antes de qualquer um gravar o id.
        $lock = Cache::lock('billing:select-plan:'.$tenant->id, 30);
        if (! $lock->get()) {
            return response()->json(['message' => 'Já estamos processando sua assinatura. Aguarde um instante.'], Response::HTTP_CONFLICT);
        }

        try {
            $documentChanged = $request->filled('document') && $request->input('document') !== $tenant->billing_document;
            if ($documentChanged) {
                $tenant->update(['billing_document' => $request->input('document')]);
            }

            if (! $tenant->asaas_customer_id) {
                $tenant->update(['asaas_customer_id' => $this->billingService->createCustomer($tenant)]);
            } elseif ($documentChanged) {
                $this->billingService->updateCustomerDocument($tenant);
            }

            if ($tenant->asaas_subscription_id) {
                // Mesma assinatura, só muda o valor (ver changeSubscriptionPlan).
                $this->billingService->changeSubscriptionPlan($tenant, $plan);
                $tenant->update(['plan' => $plan->value]);
            } else {
                $subscription = $this->billingService->createSubscription($tenant, $plan);

                // SEGURANÇA: nunca ativar a partir da seleção do plano. O webhook de
                // pagamento confirmado é a ÚNICA fonte de verdade pra status=active.
                $tenant->update([
                    'plan' => $plan->value,
                    'asaas_subscription_id' => $subscription['id'],
                    'next_due_date' => $subscription['next_due_date'],
                ]);

                SubscriptionCreated::dispatch($tenant, $plan);
            }

            $this->refreshOpenInvoice($tenant);

            return response()->json(['data' => new BillingResource($tenant->fresh())], Response::HTTP_CREATED);
        } catch (AsaasException $e) {
            return response()->json(
                ['message' => $e->asaasMessage ?? 'Erro ao processar a assinatura. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (\Throwable $e) {
            report($e);

            return response()->json(
                ['message' => 'Erro ao processar a assinatura. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } finally {
            $lock->release();
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
        } catch (\Throwable $e) {
            report($e);

            return response()->json(
                ['message' => 'Não foi possível cancelar agora. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    /**
     * Link da fatura em aberto pra tela mostrar "Pagar agora". Best-effort: a
     * assinatura já existe; se a consulta falhar, o webhook PAYMENT_CREATED traz o link.
     */
    private function refreshOpenInvoice($tenant): void
    {
        try {
            $invoice = $this->billingService->openInvoice($tenant);
            $tenant->update([
                'payment_url' => $invoice['url'],
                'next_due_date' => $invoice['due_date'] ?? $tenant->next_due_date,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Asaas: não foi possível buscar a fatura em aberto', ['tenant_id' => $tenant->id]);
        }
    }
}
