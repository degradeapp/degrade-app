<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentSource;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Modules\Appointment\Actions\CancelAppointment;
use App\Modules\Appointment\Actions\CompleteAppointment;
use App\Modules\Appointment\Actions\CreateAppointment;
use App\Modules\Appointment\Actions\RescheduleAppointment;
use App\Modules\Appointment\Actions\UpdateAppointment;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AvailabilityService;
use App\Modules\Barber\Models\Barber;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AppointmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Appointment::with('services', 'customer', 'barber');

        if (request('status')) {
            $query->where('status', request('status'));
        }

        if (request('date')) {
            $date = Carbon::parse(request('date'));
            $query->whereDate('starts_at', $date);
        }

        $appointments = $query->paginate(15);

        return AppointmentResource::collection($appointments);
    }

    public function store(StoreAppointmentRequest $request, CreateAppointment $action): JsonResponse
    {
        try {
            $appointment = $action(
                customerId: $request->input('customer_id'),
                serviceIds: $request->input('service_ids'),
                startsAt: Carbon::parse($request->input('starts_at')),
                source: AppointmentSource::from($request->input('source')),
                barberIds: $request->input('barber_ids'),
                notes: $request->input('notes'),
            );

            return response()->json(
                new AppointmentResource($appointment),
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function show(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        return response()->json(new AppointmentResource($appointment->load('services', 'customer', 'barber')));
    }

    public function update(Appointment $appointment, UpdateAppointmentRequest $request, UpdateAppointment $action): JsonResponse
    {
        $this->authorize('update', $appointment);

        try {
            $updated = $action(
                appointment: $appointment,
                startsAt: $request->input('starts_at') ? Carbon::parse($request->input('starts_at')) : null,
                serviceIds: $request->input('service_ids'),
                barberIds: $request->input('barber_ids'),
            );

            return response()->json(new AppointmentResource($updated->load('services', 'customer', 'barber')));
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function cancel(Appointment $appointment, CancelAppointmentRequest $request, CancelAppointment $action): JsonResponse
    {
        $this->authorize('cancel', $appointment);

        $updated = $action($appointment, auth()->id(), $request->input('reason'));

        return response()->json(new AppointmentResource($updated->load('services', 'customer', 'barber')));
    }

    public function complete(Appointment $appointment, CompleteAppointment $action): JsonResponse
    {
        $this->authorize('complete', $appointment);

        $updated = $action($appointment, auth()->id());

        return response()->json(new AppointmentResource($updated->load('services', 'customer', 'barber')));
    }

    public function reschedule(Appointment $appointment, UpdateAppointmentRequest $request, RescheduleAppointment $action): JsonResponse
    {
        $this->authorize('update', $appointment);

        try {
            $updated = $action($appointment, Carbon::parse($request->input('starts_at')));

            return response()->json(new AppointmentResource($updated->load('services', 'customer', 'barber')));
        } catch (\Exception $e) {
            return response()->json(
                ['message' => $e->getMessage()],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }

    public function available(Barber $barber, AvailabilityService $service): JsonResponse
    {
        $date = Carbon::parse(request('date'));
        $durationMinutes = (int) request('duration_minutes', 30);

        $slots = $service->getAvailableSlots($barber, $date, $durationMinutes);

        return response()->json([
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
            'duration_minutes' => $durationMinutes,
            'available_slots' => $slots,
        ]);
    }
}
