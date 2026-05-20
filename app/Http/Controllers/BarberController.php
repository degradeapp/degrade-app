<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateTimeOffRequest;
use App\Http\Requests\StoreBarberRequest;
use App\Http\Requests\UpdateBarberRequest;
use App\Http\Requests\UpsertScheduleRequest;
use App\Http\Resources\BarberResource;
use App\Http\Resources\BarberTimeOffResource;
use App\Modules\Barber\Actions\CreateBarber;
use App\Modules\Barber\Actions\CreateBarberTimeOff;
use App\Modules\Barber\Actions\DeleteBarber;
use App\Modules\Barber\Actions\DeleteBarberTimeOff;
use App\Modules\Barber\Actions\UpdateBarber;
use App\Modules\Barber\Actions\UpsertBarberSchedule;
use App\Modules\Barber\Models\Barber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BarberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $barbers = Barber::with('schedules')->paginate(15);

        return BarberResource::collection($barbers);
    }

    public function store(StoreBarberRequest $request, CreateBarber $action): JsonResponse
    {
        $barber = $action(
            name: $request->input('name'),
            phone: $request->input('phone'),
            userId: $request->input('user_id'),
            defaultCommissionPercentage: $request->input('default_commission_percentage'),
        );

        return response()->json(
            new BarberResource($barber->load('schedules')),
            Response::HTTP_CREATED
        );
    }

    public function show(Barber $barber): JsonResponse
    {
        $this->authorize('view', $barber);

        return response()->json(new BarberResource($barber->load('schedules')));
    }

    public function update(Barber $barber, UpdateBarberRequest $request, UpdateBarber $action): JsonResponse
    {
        $this->authorize('update', $barber);

        $updated = $action(
            barber: $barber,
            name: $request->input('name'),
            phone: $request->input('phone'),
            defaultCommissionPercentage: $request->input('default_commission_percentage'),
            isActive: $request->input('is_active'),
        );

        return response()->json(new BarberResource($updated->load('schedules')));
    }

    public function destroy(Barber $barber, DeleteBarber $action): Response
    {
        $this->authorize('delete', $barber);

        $action($barber, auth()->id());

        return response()->noContent();
    }

    public function schedule(
        Barber $barber,
        int $day,
        UpsertScheduleRequest $request,
        UpsertBarberSchedule $action
    ): JsonResponse {
        $this->authorize('update', $barber);

        $schedule = $action(
            barber: $barber,
            dayOfWeek: $day,
            startTime: $request->input('start_time'),
            endTime: $request->input('end_time'),
        );

        return response()->json([
            'message' => 'Horário atualizado com sucesso.',
            'schedule' => $schedule,
        ]);
    }

    public function timeOff(Barber $barber, CreateTimeOffRequest $request, CreateBarberTimeOff $action): JsonResponse
    {
        $this->authorize('update', $barber);

        $timeOff = $action(
            barber: $barber,
            date: $request->input('date'),
            reason: $request->input('reason'),
        );

        return response()->json(
            new BarberTimeOffResource($timeOff),
            Response::HTTP_CREATED
        );
    }

    public function deleteTimeOff(Barber $barber, string $date, DeleteBarberTimeOff $action): Response
    {
        $this->authorize('update', $barber);

        $action($barber, $date);

        return response()->noContent();
    }
}
