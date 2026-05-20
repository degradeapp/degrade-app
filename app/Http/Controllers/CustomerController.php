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
        $customers = Customer::paginate(15);

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request, CreateCustomer $action): JsonResponse
    {
        $customer = $action(
            name: $request->input('name'),
            phone: $request->input('phone'),
            email: $request->input('email'),
        );

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

        return response()->json(new CustomerResource($updated));
    }

    public function destroy(Customer $customer, DeleteCustomer $action): Response
    {
        $this->authorize('delete', $customer);

        $action($customer, auth()->id());

        return response()->noContent();
    }
}
