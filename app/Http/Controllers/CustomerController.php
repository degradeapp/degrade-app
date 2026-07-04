<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\DeleteCustomer;
use App\Modules\Customer\Actions\UpdateCustomer;
use App\Modules\Customer\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Exporta a base de clientes em CSV (a base é do dono, sem lock-in).
     * Owner-only na rota; exportação de dado pessoal fica na auditoria (LGPD).
     * CSV com ; e BOM UTF-8 pro Excel pt-BR abrir com acento certo.
     */
    public function export(): StreamedResponse
    {
        $tenantId = app('tenant')->id;

        ActivityLogger::log(
            $tenantId,
            'exported',
            Customer::class,
            0,
            metadata: ['total' => Customer::count()],
        );

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nome', 'Telefone', 'Email', 'Observações', 'Cadastrado em'], ';');

            Customer::orderBy('name')->chunk(500, function ($customers) use ($out) {
                foreach ($customers as $c) {
                    fputcsv($out, [
                        $c->name,
                        $c->phone,
                        $c->email,
                        $c->notes,
                        $c->created_at?->format('d/m/Y'),
                    ], ';');
                }
            });

            fclose($out);
        }, 'clientes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
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
