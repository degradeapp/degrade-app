<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
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
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Inertia\Inertia;

class AppointmentController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Appointment::with('services.service', 'services.barber', 'customer', 'barber');

        if (request('status')) {
            $query->where('status', request('status'));
        }

        if (request('date')) {
            $date = Carbon::parse(request('date'));
            $query->whereDate('starts_at', $date);
        }

        if (request('customer_id')) {
            $query->where('customer_id', (int) request('customer_id'));
        }

        if (request('barber_id')) {
            $query->where('barber_id', (int) request('barber_id'));
        }

        if (request('from')) {
            $query->where('starts_at', '>=', Carbon::parse(request('from'))->startOfDay());
        }

        if (request('to')) {
            $query->where('starts_at', '<=', Carbon::parse(request('to'))->endOfDay());
        }

        $appointments = $query->orderByDesc('starts_at')->paginate(request('per_page', 30));

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
                priceOverrides: $request->input('prices', []) ?? [],
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

    public function indexPage(): \Inertia\Response
    {
        $today = Carbon::now()->startOfDay();

        // Dia em foco: ?date=YYYY-MM-DD (ex.: "Ver na agenda" após criar) ou hoje.
        $focus = request('date') ? Carbon::parse(request('date'))->startOfDay() : $today->copy();

        // Janela que cobre hoje E o dia em foco (sem mutar referências compartilhadas).
        $earliest = $focus->lt($today) ? $focus : $today;
        $latest = $focus->gt($today) ? $focus : $today;
        $from = $earliest->copy()->subDays(14);
        $to = $latest->copy()->addDays(45)->endOfDay();

        $appointments = Appointment::with(['customer', 'barber', 'services.service', 'services.barber'])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get()
            ->map(function (Appointment $apt) {
                $barberName = $apt->barber?->name ?? '—';
                $customerName = $apt->customer?->name ?? '—';
                $servicesLabel = $apt->services->map(fn ($as) => $as->service?->name)->filter()->implode(', ');
                $durationMinutes = (int) Carbon::parse($apt->starts_at)->diffInMinutes(Carbon::parse($apt->ends_at));

                return [
                    'id' => $apt->id,
                    'customer_name' => $customerName,
                    'customer_initials' => $this->initials($customerName),
                    'barber_name' => $barberName,
                    'barber_initials' => $this->initials($barberName),
                    'services' => $servicesLabel ?: '—',
                    'starts_at' => $apt->starts_at?->toIso8601String(),
                    'duration_minutes' => $durationMinutes,
                    'price' => (float) $apt->total_price,
                    'status' => $apt->effectiveStatus()->value,
                    'notes' => $apt->notes,
                ];
            })
            ->all();

        return Inertia::render('Appointments/Index', [
            'date' => $focus->toIso8601String(),
            'business_hours' => ['start' => '08:00', 'end' => '22:00'],
            'appointments' => $appointments,
        ]);
    }

    public function createPage(): \Inertia\Response
    {
        // Pré-carrega só os clientes mais prováveis de reagendar (recentes).
        // O restante é buscado sob demanda via /api/customers?q= no front (escala p/ 10k+).
        $customers = Customer::orderByDesc('last_visit_at')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'initials' => $this->initials($c->name),
                'total_visits' => (int) ($c->total_visits ?? 0),
            ])->all();

        $serviceModels = Service::where('is_active', true)->orderBy('name')->get();
        $services = $serviceModels->map(fn (Service $s) => [
            'id' => $s->id,
            'name' => $s->name,
            'price' => (float) $s->price,
        ])->all();
        $allServiceIds = $serviceModels->pluck('id')->all();

        $barbers = Barber::with('services:id')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (Barber $b) use ($allServiceIds) {
                $explicit = $b->services->pluck('id')->all();

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'initials' => $this->initials($b->name),
                    // Sem vínculo explícito → faz todos os serviços (padrão de barbearia).
                    'service_ids' => ! empty($explicit) ? $explicit : $allServiceIds,
                ];
            })->all();

        // Pré-seleção de cliente (vindo de "Agendar" na ficha do cliente).
        // Garante que ele esteja na lista mesmo que não tenha visitas recentes.
        $prefillCustomerId = request('customer_id') ? (int) request('customer_id') : null;
        if ($prefillCustomerId && ! collect($customers)->contains('id', $prefillCustomerId)) {
            $c = Customer::find($prefillCustomerId);
            if ($c) {
                array_unshift($customers, [
                    'id' => $c->id,
                    'name' => $c->name,
                    'phone' => $c->phone,
                    'initials' => $this->initials($c->name),
                    'total_visits' => (int) ($c->total_visits ?? 0),
                ]);
            }
        }

        return Inertia::render('Appointments/Create', [
            'customers' => $customers,
            'services' => $services,
            'barbers' => $barbers,
            'prefill' => [
                'date' => request('date'),
                'time' => request('time'),
                'barber_id' => request('barber_id') ? (int) request('barber_id') : null,
                'customer_id' => $prefillCustomerId,
            ],
        ]);
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }

    public function showPage(Appointment $appointment): \Inertia\Response
    {
        $this->authorize('view', $appointment);

        return Inertia::render('Appointments/Show', [
            // resolve() devolve o array CRU (sem o wrapper "data" que o Inertia adicionaria
            // ao Resource) — é o formato que Appointments/Show.vue consome direto.
            'appointment' => (new AppointmentResource(
                $appointment->load(['customer', 'services.service', 'services.barber', 'barber'])
            ))->resolve(),
        ]);
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

            // Observações são opcionais e tratadas aqui (o action cuida de horário/serviços).
            if ($request->has('notes')) {
                $updated->update(['notes' => $request->input('notes')]);
            }

            return response()->json(new AppointmentResource($updated->fresh()->load('services', 'customer', 'barber')));
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

    public function noShow(Appointment $appointment): JsonResponse
    {
        // Mesma permissão de cancelar. "Não compareceu" não gera comissão e fica
        // separado de "cancelado" no Relatório (mede confiabilidade do cliente).
        $this->authorize('cancel', $appointment);

        $appointment->update(['status' => AppointmentStatus::no_show]);

        return response()->json(new AppointmentResource($appointment->fresh()->load('services', 'customer', 'barber')));
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

    public function daySchedule(Barber $barber, AvailabilityService $service): JsonResponse
    {
        $date = Carbon::parse(request('date'));
        $durationMinutes = (int) request('duration_minutes', Appointment::DEFAULT_BLOCK_MINUTES);

        return response()->json([
            'barber_id' => $barber->id,
            'date' => $date->toDateString(),
            ...$service->getDaySchedule($barber, $date, $durationMinutes),
        ]);
    }
}
