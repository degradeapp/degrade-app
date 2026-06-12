<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Commission\Models\Commission;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $tenant = $user->tenant;
        // Dados financeiros (receita, ticket, comissões) são só pra dono/gerente.
        // Recepcionista/barbeiro veem a parte operacional (agenda, ocupação), sem dinheiro.
        $canSeeFinance = $user->isOwner() || $user->isManager();

        // Escopo de unidade: barbeiro/recepção veem só a sua; dono/gerente veem a unidade
        // selecionada OU consolidado (null = todas). Tenant sem unidade (teste antigo) = todas.
        $unitId = app(\App\Modules\Tenant\Services\UnitContext::class)->scopedUnitId();

        $today = Carbon::now()->startOfDay();
        $tomorrow = $today->copy()->addDay();

        $todayQuery = Appointment::with(['customer', 'barber', 'services.service'])
            ->whereBetween('starts_at', [$today, $tomorrow]);

        if ($unitId !== null) {
            $todayQuery->where('unit_id', $unitId);
        }

        $todayAppointments = $todayQuery->orderBy('starts_at')->get();

        $now = Carbon::now();

        // Contagem pelo status EFETIVO (mesma regra da agenda e do detalhe): "A concluir"
        // é o atendimento cujo horário já passou e ninguém fechou (não conta como "a fazer",
        // que é futuro, nem como "concluído", que só vem da conclusão explícita).
        $completed = $todayAppointments->filter(fn ($a) => $a->effectiveStatus() === AppointmentStatus::completed)->count();
        $awaiting = $todayAppointments->filter(fn ($a) => $a->effectiveStatus() === AppointmentStatus::awaiting_completion)->count();
        $pending = $todayAppointments->filter(fn ($a) => in_array($a->effectiveStatus(), [
            AppointmentStatus::scheduled, AppointmentStatus::confirmed, AppointmentStatus::in_progress,
        ], true))->count();

        $revenue = $todayAppointments
            ->filter(fn ($a) => $a->status === AppointmentStatus::completed)
            ->sum('total_price');

        // Ticket médio do dia = receita ÷ atendimentos concluídos (KPI clássico de salão).
        $avgTicketToday = $completed > 0 ? round((float) $revenue / $completed, 2) : 0.0;

        $mapApt = function (Appointment $apt) {
            $barberName = $apt->barber?->name ?? '—';
            $serviceName = $apt->services->map(fn ($as) => $as->service?->name)->filter()->first() ?? '—';

            return [
                'id' => $apt->id,
                'customer_name' => $apt->customer?->name ?? '—',
                'service_name' => $serviceName,
                'barber_name' => $barberName,
                'barber_initials' => $this->initials($barberName),
                'starts_at' => $apt->starts_at?->toIso8601String(),
                'status' => $apt->effectiveStatus()->value,
            ];
        };

        // Próximos = o que ainda está por vir hoje (termina depois de agora), menos cancelado.
        $upcoming = $todayAppointments
            ->filter(fn ($a) => Carbon::parse($a->ends_at ?? $a->starts_at)->gte($now) && $a->status !== AppointmentStatus::cancelled)
            ->take(5)
            ->map($mapApt)
            ->values()
            ->all();

        // A concluir = passou do horário e segue em aberto. Some dos "próximos", então
        // precisa de uma seção própria pra não ficar invisível (e a tela mentir "livre").
        $awaitingAppointments = $todayAppointments
            ->filter(fn ($a) => $a->effectiveStatus() === AppointmentStatus::awaiting_completion)
            ->take(5)
            ->map($mapApt)
            ->values()
            ->all();

        $trialDaysLeft = null;
        if ($tenant && $tenant->status === 'trial' && $tenant->trial_ends_at) {
            $diff = Carbon::now()->diffInDays(Carbon::parse($tenant->trial_ends_at), false);
            $trialDaysLeft = (int) ceil($diff);
        }

        $revenueWeek = $canSeeFinance ? $this->revenueLast7Days($tenant?->id, $unitId) : [];
        $pendingCommissions = $canSeeFinance && $tenant
            ? (float) Commission::where('tenant_id', $tenant->id)
                ->where('status', 'pending')
                ->when($unitId !== null, fn ($q) => $q->whereHas('appointment', fn ($a) => $a->where('unit_id', $unitId)))
                ->sum('amount')
            : 0.0;
        $occupation = $tenant
            ? $this->occupationToday($tenant->id, $todayAppointments, $unitId)
            : ['rate' => 0, 'booked_hours' => 0.0, 'available_hours' => 0.0];

        return Inertia::render('Dashboard/Index', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'tenant' => [
                'name' => $tenant?->name,
                'status' => $tenant?->status,
                'plan' => $tenant?->plan,
                'timezone' => $tenant?->setting('timezone', config('app.timezone')),
                'trial_days_left' => $trialDaysLeft,
                'onboarding_completed_at' => $tenant?->onboarding_completed_at?->toIso8601String(),
            ],
            'can_see_finance' => $canSeeFinance,
            'stats' => [
                'appointments_today' => $todayAppointments->count(),
                'appointments_completed' => $completed,
                'appointments_pending' => $pending,
                'appointments_awaiting' => $awaiting,
                // Financeiro zerado pra quem não pode ver (não vaza nem no payload).
                'revenue_today' => $canSeeFinance ? (float) $revenue : 0,
                'avg_ticket_today' => $canSeeFinance ? $avgTicketToday : 0,
                'occupation_rate' => $occupation['rate'],
                'occupation_booked_hours' => $occupation['booked_hours'],
                'occupation_available_hours' => $occupation['available_hours'],
            ],
            'upcoming_appointments' => $upcoming,
            'awaiting_appointments' => $awaitingAppointments,
            'revenue_week' => $revenueWeek,
            'pending_commissions' => $pendingCommissions,
        ]);
    }

    private function revenueLast7Days(?int $tenantId, ?int $unitId = null): array
    {
        if (! $tenantId) {
            return [];
        }

        $start = Carbon::now()->startOfDay()->subDays(6);
        $end = Carbon::now()->endOfDay();

        $rows = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->whereBetween('starts_at', [$start, $end])
            ->selectRaw('DATE(starts_at) as day, SUM(total_price) as total')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = Carbon::now()->startOfDay()->subDays($i);
            $key = $d->toDateString();
            $days[] = [
                'date' => $key,
                'label' => $d->isoFormat('ddd'),
                'total' => (float) ($rows->get($key)?->total ?? 0),
            ];
        }

        return $days;
    }

    /**
     * Ocupação de hoje = horas ocupadas ÷ horas de expediente.
     * - Horas de expediente: soma do horário de hoje dos barbeiros ATIVOS, pulando quem
     *   está de folga. (Dia fechado / sem barbeiro = 0 → o front mostra "sem expediente".)
     * - Horas ocupadas: soma da duração dos agendamentos de hoje que seguram horário.
     *   Cancelado e falta (no-show) NÃO contam: a cadeira ficou vazia (padrão de mercado
     *   de utilization rate, onde no-show é capacidade não preenchida).
     */
    private function occupationToday(int $tenantId, $todayAppointments, ?int $unitId = null): array
    {
        $now = Carbon::now();
        $dow = $now->dayOfWeek;          // 0=Dom..6=Sáb (mesma convenção dos horários)
        $today = $now->toDateString();

        // Expediente da unidade ativa (consolidado = todos os barbeiros do tenant).
        $barbers = Barber::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($unitId !== null, fn ($q) => $q->where('unit_id', $unitId))
            ->with(['schedules' => fn ($q) => $q->where('day_of_week', $dow)])
            ->get();

        $availableMinutes = 0;
        foreach ($barbers as $barber) {
            $onTimeOff = $barber->timeOffs()
                ->where('date', '<=', $today)
                ->whereRaw('COALESCE(end_date, date) >= ?', [$today])
                ->exists();
            if ($onTimeOff) {
                continue;
            }
            foreach ($barber->schedules as $sch) {
                $availableMinutes += $this->minutesBetween($sch->start_time, $sch->end_time);
            }
        }

        $bookedMinutes = $todayAppointments
            ->filter(fn ($a) => ! in_array($a->status, [AppointmentStatus::cancelled, AppointmentStatus::no_show], true))
            ->sum(function ($a) {
                if (! $a->starts_at || ! $a->ends_at) {
                    return 0;
                }

                return Carbon::parse($a->starts_at)->diffInMinutes(Carbon::parse($a->ends_at));
            });

        $rate = $availableMinutes > 0 ? (int) min(100, round($bookedMinutes / $availableMinutes * 100)) : 0;

        return [
            'rate' => $rate,
            'booked_hours' => round($bookedMinutes / 60, 1),
            'available_hours' => round($availableMinutes / 60, 1),
        ];
    }

    private function minutesBetween(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        $toMin = function (string $t): int {
            $p = explode(':', $t);

            return ((int) ($p[0] ?? 0)) * 60 + ((int) ($p[1] ?? 0));
        };

        return max(0, $toMin($end) - $toMin($start));
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';

        return mb_strtoupper($first.$last);
    }
}
