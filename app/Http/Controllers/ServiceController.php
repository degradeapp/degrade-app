<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachBarberServiceRequest;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Actions\AttachBarberService;
use App\Modules\Service\Actions\CreateService;
use App\Modules\Service\Actions\DeleteService;
use App\Modules\Service\Actions\DetachBarberService;
use App\Modules\Service\Actions\UpdateService;
use App\Modules\Service\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Service::with('barbers');

        if ($q = request('q')) {
            $query->where('name', 'like', "%{$q}%");
        }

        if (request()->has('is_active')) {
            $query->where('is_active', request()->boolean('is_active'));
        }

        $services = $query->orderBy('name')->paginate(request('per_page', 50));

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request, CreateService $action): JsonResponse
    {
        $service = $action(
            name: $request->input('name'),
            price: $request->input('price'),
            description: $request->input('description'),
            commissionPercentage: $request->input('commission_percentage'),
        );

        return response()->json(
            new ServiceResource($service->load('barbers')),
            Response::HTTP_CREATED
        );
    }

    /**
     * Cria vários serviços de uma vez (serviços comuns + preço base).
     * Ignora os que já existem (mesmo nome no tenant).
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $request->validate([
            'services' => 'required|array|min:1',
            'services.*.name' => 'required|string|max:80',
            'services.*.price' => 'required|numeric|min:0|max:999999',
            'services.*.commission_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        $tenantId = auth()->user()->tenant_id;
        $saved = collect();

        foreach ($request->input('services') as $item) {
            // Upsert por nome: cria os novos e ATUALIZA o preço dos que já existem.
            $service = Service::firstOrNew(['tenant_id' => $tenantId, 'name' => $item['name']]);
            $service->price = (float) $item['price'];
            $service->is_active = true;

            // Só mexe na comissão se foi informada — não apaga a comissão existente.
            if (isset($item['commission_percentage']) && $item['commission_percentage'] !== null) {
                $service->commission_percentage = (float) $item['commission_percentage'];
            }

            $service->save();
            $saved->push($service);
        }

        return response()->json(
            ['data' => ServiceResource::collection($saved->each->load('barbers'))],
            Response::HTTP_CREATED
        );
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);

        return response()->json(new ServiceResource($service->load('barbers')));
    }

    public function update(Service $service, UpdateServiceRequest $request, UpdateService $action): JsonResponse
    {
        $this->authorize('update', $service);

        $updated = $action(
            service: $service,
            name: $request->input('name'),
            price: $request->input('price'),
            description: $request->input('description'),
            commissionPercentage: $request->input('commission_percentage'),
            isActive: $request->input('is_active'),
        );

        return response()->json(new ServiceResource($updated->load('barbers')));
    }

    public function destroy(Service $service, DeleteService $action): Response
    {
        $this->authorize('delete', $service);

        $action($service, auth()->id());

        return response()->noContent();
    }

    public function attachBarber(
        Service $service,
        Barber $barber,
        AttachBarberServiceRequest $request,
        AttachBarberService $action
    ): JsonResponse {
        $this->authorize('update', $service);

        $updated = $action(
            service: $service,
            barber: $barber,
            commissionPercentage: $request->input('commission_percentage'),
        );

        return response()->json([
            'message' => 'Barbeiro atribuído com sucesso.',
            'service' => new ServiceResource($updated),
        ]);
    }

    public function detachBarber(Service $service, Barber $barber, DetachBarberService $action): Response
    {
        $this->authorize('update', $service);

        $action($service, $barber);

        return response()->noContent();
    }
}
