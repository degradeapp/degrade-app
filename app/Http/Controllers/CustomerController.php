<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\DeleteCustomer;
use App\Modules\Customer\Actions\UpdateCustomer;
use App\Modules\Customer\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CustomerController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Customer::query();

        if ($q = request('q')) {
            $digits = preg_replace('/\D/', '', $q) ?: '';
            $query->where(function ($w) use ($q, $digits) {
                $w->where('name', 'like', "%{$q}%");
                if ($digits !== '') {
                    $w->orWhere('phone', 'like', "%{$digits}%");
                }
            });
        }

        $customers = $query->orderBy('name')->paginate(request('per_page', 50));

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $action): JsonResponse
    {
        $customer = $action(
            name: $request->input('name'),
            phone: $request->input('phone'),
            email: $request->input('email'),
        );

        if ($request->filled('notes')) {
            $customer->update(['notes' => $request->input('notes')]);
        }

        return response()->json(
            new CustomerResource($customer),
            Response::HTTP_CREATED
        );
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(new CustomerResource($customer));
    }

    public function update(Customer $customer, UpdateCustomerRequest $request, UpdateCustomer $action): JsonResponse
    {
        $this->authorize('update', $customer);

        $updated = $action(
            customer: $customer,
            name: $request->input('name'),
            phone: $request->input('phone'),
            email: $request->input('email'),
        );

        if ($request->has('notes')) {
            $updated->update(['notes' => $request->input('notes')]);
        }

        return response()->json(new CustomerResource($updated->fresh()));
    }

    public function destroy(Customer $customer, DeleteCustomer $action): Response
    {
        $this->authorize('delete', $customer);

        $action($customer, auth()->id());

        return response()->noContent();
    }
}
