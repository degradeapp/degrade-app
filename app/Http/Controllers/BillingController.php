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

        return response()->json(new BillingResource($tenant));
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

            $tenant->update([
                'plan' => $plan->value,
                'status' => 'active',
                'asaas_subscription_id' => $subscription['id'] ?? null,
            ]);

            SubscriptionCreated::dispatch($tenant, $plan);

            return response()->json(
                new BillingResource($tenant),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => 'Erro ao processar pagamento. Tente novamente.'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
}
