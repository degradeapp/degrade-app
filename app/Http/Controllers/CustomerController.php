<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Modules\Customer\Actions\CreateCustomer;
use App\Modules\Customer\Actions\DeleteCustomer;
use App\Modules\Customer\Actions\EraseCustomerData;
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
                    fputcsv($out, array_map($this->csvSafe(...), [
                        $c->name,
                        $c->phone,
                        $c->email,
                        $c->notes,
                        $c->created_at?->format('d/m/Y'),
                    ]), ';');
                }
            });

            fclose($out);
        }, 'clientes.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * CSV/formula injection: nome e observação chegam do link público e do bot, então
     * um "cliente" chamado =HYPERLINK(...) viraria fórmula ativa quando o dono abrisse
     * o arquivo no Excel. Célula que começa com = + - @ tab ou CR ganha um apóstrofo
     * (OWASP: vira texto puro).
     */
    private function csvSafe(?string $value): ?string
    {
        if ($value !== null && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
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

    /**
     * Pedido de eliminação do titular (LGPD): apaga os dados pessoais de verdade e
     * mantém o histórico financeiro sem identificação. Só o dono; irreversível.
     * Aceita cliente já excluído (soft-delete) — o pedido pode vir depois.
     */
    public function erase(Customer $customer, EraseCustomerData $action): Response
    {
        $this->authorize('delete', $customer);

        $action($customer, auth()->id());

        return response()->noContent();
    }
}
