<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentSource;
use App\Http\Requests\PublicBookingRequest;
use App\Modules\Appointment\Actions\CreateAppointment;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Appointment\Services\AvailabilityService;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\Unit\Models\Unit;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Link público de agendamento: /agendar/{slug}. SEM login. O cliente final
 * escolhe serviço, barbeiro (ou "qualquer"), data e horário, informa nome e
 * telefone, e o agendamento nasce com source=customer na unidade correta.
 *
 * Segurança (fronteiras deste controller):
 * - O tenant é resolvido SÓ pelo slug. Tenant inexistente, suspenso, vencido
 *   ou cancelado responde 404 genérico (não revela que a barbearia existe).
 *   Regra de aceite: status active OU trial válido. Justificativa: durante o
 *   trial o dono está testando o produto com clientes reais; bloquear o link
 *   mataria a melhor demo possível. Suspenso/past_due/cancelled = link fora
 *   do ar (inadimplente não opera de graça).
 * - TODA leitura passa pelo TenantScope (o contexto do tenant é setado aqui),
 *   e as validações de service/barber/unit usam queries já escopadas, então
 *   IDs de outro tenant simplesmente não existem (404/422, nunca vazam).
 * - O catálogo expõe o MÍNIMO: nome/preço de serviço ativo e nome/foto de
 *   barbeiro ativo. Nunca telefone de barbeiro, nunca lista de clientes,
 *   nunca dados financeiros.
 * - Diferente do balcão (que permite encaixe), o fluxo público RESPEITA a
 *   disponibilidade real (expediente, folga e conflito) via AvailabilityService.
 *   Cliente anônimo não encaixa ninguém.
 * - Rate limit por IP nas rotas (throttle:public-booking / public-booking-create).
 */
class PublicBookingController extends Controller
{
    /** Horizonte máximo de agendamento online (dias à frente). */
    private const MAX_DAYS_AHEAD = 60;

    public function __construct(private AvailabilityService $availability) {}

    /**
     * Catálogo público: dados da barbearia, unidades (se Rede), serviços e
     * barbeiros ativos. É a carga inicial da página /agendar/{slug}.
     */
    public function catalog(string $slug): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);

        $units = Unit::where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (Unit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'address' => $u->address,
            ])
            ->values();

        $services = Service::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'price' => (float) $s->price,
            ])
            ->values();

        $barbers = Barber::where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Barber $b) => [
                'id' => $b->id,
                'unit_id' => $b->unit_id,
                'name' => $b->name,
                'photo_url' => $b->photoUrl(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'name' => $tenant->name,
                'logo_url' => $tenant->logoUrl(),
                'timezone' => $tenant->setting('timezone', config('app.timezone')),
                'multi_unit' => $units->count() > 1,
                'units' => $units,
                'services' => $services,
                'barbers' => $barbers,
            ],
        ]);
    }

    /**
     * Horários disponíveis para uma data. barber_id ausente = "qualquer":
     * união dos horários livres de todos os barbeiros ativos da unidade.
     */
    public function slots(string $slug, Request $request): JsonResponse
    {
        $this->resolveTenant($slug);

        $data = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'barber_id' => 'nullable|integer',
            'unit_id' => 'nullable|integer',
        ]);

        $unit = $this->resolveUnit($data['unit_id'] ?? null);
        $date = Carbon::parse($data['date'])->startOfDay();

        if ($date->lt(Carbon::today()) || $date->gt(Carbon::today()->addDays(self::MAX_DAYS_AHEAD))) {
            return response()->json(['message' => 'Data fora do período de agendamento online.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $barbers = $this->bookableBarbers($unit, $data['barber_id'] ?? null);

        if ($barbers->isEmpty()) {
            // 404 e não 422: um barber_id de outro tenant/unidade não pode ser
            // distinguível de um id inexistente (anti-enumeração).
            abort(404);
        }

        $now = Carbon::now();
        $times = [];
        foreach ($barbers as $barber) {
            foreach ($this->availability->getAvailableSlots($barber, $date->copy(), Appointment::DEFAULT_BLOCK_MINUTES) as $slot) {
                $start = Carbon::parse($slot['start_time']);
                if ($start->lte($now)) {
                    continue; // nunca oferecer horário no passado (caso de hoje)
                }
                $times[$start->format('H:i')] = true;
            }
        }

        $times = array_keys($times);
        sort($times);

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'unit_id' => $unit->id,
                'slots' => $times,
            ],
        ]);
    }

    /**
     * Cria o agendamento público. Valida tudo de novo no servidor (nunca
     * confia no que a página pública mandou) e re-checa a disponibilidade
     * imediatamente antes de gravar.
     */
    public function store(string $slug, PublicBookingRequest $request, CreateAppointment $action): JsonResponse
    {
        $tenant = $this->resolveTenant($slug);
        $unit = $this->resolveUnit($request->input('unit_id'));

        $startsAt = Carbon::parse($request->input('starts_at'));

        if ($startsAt->lte(Carbon::now())) {
            return response()->json(['message' => 'O horário não pode ser no passado.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if ($startsAt->gt(Carbon::now()->addDays(self::MAX_DAYS_AHEAD))) {
            return response()->json(['message' => 'Escolha uma data dentro dos próximos '.self::MAX_DAYS_AHEAD.' dias.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Serviços: TODOS precisam existir NO TENANT e estar ativos. A query é
        // tenant-scoped, então id de outro tenant "não existe" aqui.
        $serviceIds = array_values(array_unique(array_map('intval', $request->input('service_ids'))));
        $services = Service::whereIn('id', $serviceIds)->where('is_active', true)->get();
        if ($services->count() !== count($serviceIds)) {
            return response()->json(['message' => 'Um dos serviços selecionados não está disponível.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $endsAt = $startsAt->copy()->addMinutes(Appointment::DEFAULT_BLOCK_MINUTES);

        // Barbeiro escolhido (escopado por tenant + unidade) ou "qualquer um
        // que esteja livre" no horário pedido.
        $barber = $this->pickBarber($unit, $request->input('barber_id'), $startsAt, $endsAt);
        if (! $barber) {
            return response()->json(['message' => 'Este horário não está mais disponível. Escolha outro.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Cliente: casa pelo telefone DENTRO do tenant, ou cria. Atualiza o nome
        // só se o registro existente nasceu sem nome real (ex.: bot do WhatsApp).
        $phone = preg_replace('/\D/', '', (string) $request->input('phone'));
        $customer = Customer::firstOrCreate(
            ['tenant_id' => $tenant->id, 'phone' => $phone],
            ['name' => $request->input('name'), 'is_active' => true],
        );
        if ($customer->name === 'Cliente WhatsApp') {
            $customer->update(['name' => $request->input('name')]);
        }

        try {
            $appointment = $action(
                customerId: $customer->id,
                serviceIds: $services->pluck('id')->all(),
                startsAt: $startsAt,
                source: AppointmentSource::customer,
                barberIds: array_fill(0, $services->count(), $barber->id),
                notes: 'Agendado pelo link público',
                unitId: $unit->id,
            );
        } catch (\Exception $e) {
            return response()->json(['message' => 'Não foi possível concluir o agendamento. Tente novamente.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Resposta mínima: confirmação pro cliente, sem expor estrutura interna.
        return response()->json([
            'data' => [
                'id' => $appointment->id,
                'starts_at' => $appointment->starts_at?->toIso8601String(),
                'barber_name' => $barber->name,
                'services' => $services->pluck('name')->values(),
                'total_price' => (float) $appointment->total_price,
                'unit_name' => $unit->name,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Resolve o tenant pelo slug e instala o contexto (TenantScope + fuso da
     * loja). 404 genérico para qualquer estado não-operante.
     */
    private function resolveTenant(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)->first();

        abort_if(! $tenant || ! ($tenant->isActive() || $tenant->isTrialing()), 404);

        app(TenantContext::class)->set($tenant);
        app()->instance('tenant', $tenant);

        return $tenant;
    }

    /**
     * Unidade pública: a informada (validada dentro do tenant) ou a 1ª ativa.
     * Unidade de outro tenant nunca aparece (query escopada) = 404.
     */
    private function resolveUnit(mixed $unitId): Unit
    {
        $query = Unit::where('is_active', true);

        $unit = $unitId
            ? $query->where('id', (int) $unitId)->first()
            : $query->orderBy('id')->first();

        abort_if(! $unit, 404);

        return $unit;
    }

    /**
     * Barbeiros agendáveis da unidade. Com barber_id, retorna só ele (se for
     * da unidade e estiver ativo); sem, todos os ativos da unidade.
     */
    private function bookableBarbers(Unit $unit, mixed $barberId)
    {
        $query = Barber::where('is_active', true)->where('unit_id', $unit->id);

        if ($barberId) {
            $query->where('id', (int) $barberId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Escolhe o barbeiro que vai atender: o pedido (se realmente disponível)
     * ou, no modo "qualquer", o primeiro da unidade livre no horário. Aqui a
     * disponibilidade é DURA (expediente + folga + conflito) — link público
     * não encaixa.
     */
    private function pickBarber(Unit $unit, mixed $barberId, Carbon $startsAt, Carbon $endsAt): ?Barber
    {
        foreach ($this->bookableBarbers($unit, $barberId) as $barber) {
            if ($this->availability->isAvailable($barber, $startsAt->copy(), $endsAt->copy())) {
                return $barber;
            }
        }

        return null;
    }
}
