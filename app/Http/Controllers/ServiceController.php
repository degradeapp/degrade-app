<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachBarberServiceRequest;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Modules\Barber\Models\Barber;
use App\Modules\Service\Actions\AttachBarberService;
use App\Modules\Service\Actions\CreateService;
use App\Modules\Service\Actions\DeleteBarberService;
use App\Modules\Service\Actions\DeleteService;
use App\Modules\Service\Actions\UpdateService;
use App\Modules\Service\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $services = Service::with('barbers')->paginate(15);

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request, CreateService $action): JsonResponse
    {
        $service = $action(
            name: $request->input('name'),
            durationMinutes: $request->input('duration_minutes'),
            price: $request->input('price'),
            description: $request->input('description'),
            commissionPercentage: $request->input('commission_percentage'),
        );

        return response()->json(
            new ServiceResource($service->load('barbers')),
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
            durationMinutes: $request->input('duration_minutes'),
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

    public function detachBarber(Service $service, Barber $barber, DeleteBarberService $action): Response
    {
        $this->authorize('update', $service);

        $action($service, $barber);

        return response()->noContent();
    }
}
