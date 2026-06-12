<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Modules\Appointment\Models\Appointment;
use App\Modules\Commission\Models\Commission;
use App\Modules\Customer\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function indexPage(): Response
    {
        return Inertia::render('Reports/Index');
    }

    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $tenantId = app('tenant')->id;
        $from = $request->input('from') ? Carbon::parse($request->input('from'))->startOfDay() : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->input('to') ? Carbon::parse($request->input('to'))->endOfDay() : Carbon::now()->endOfDay();

        // Unidade ativa: null = consolidado (todas). Quando uma está selecionada, o relatório
        // inteiro (receita, comissões, rankings) escopa nela. Clientes seguem da rede (CRM).
        $unitId = app(\App\Modules\Tenant\Services\UnitContext::class)->scopedUnitId();
        $byUnit = fn ($q) => $q->when($unitId !== null, fn ($qq) => $qq->where('unit_id', $unitId));
        $byUnitVia = fn ($q) => $q->when($unitId !== null, fn ($qq) => $qq->whereHas('appointment', fn ($a) => $a->where('unit_id', $unitId)));

        $completedQuery = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit);

        $revenue = (float) (clone $completedQuery)->sum('total_price');
        $completedCount = (clone $completedQuery)->count();

        $cancelledCount = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::cancelled->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit)
            ->count();

        $noShowCount = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::no_show->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit)
            ->count();

        $newCustomers = Customer::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        // Usa os limites Carbon (startOfDay/endOfDay), NÃO toDateString(): a coluna é
        // `date` mas o SQLite guarda "YYYY-MM-DD 00:00:00", e comparar com a string
        // "YYYY-MM-DD" no limite superior descartava o dia inteiro (00:00:00 > data pura).
        $commissionsPending = (float) Commission::where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->whereBetween('reference_date', [$from, $to])
            ->tap($byUnitVia)
            ->sum('amount');

        $commissionsPaid = (float) Commission::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('reference_date', [$from, $to])
            ->tap($byUnitVia)
            ->sum('amount');

        // Quebra POR UNIDADE (o "consolidado" da rede): receita e atendimentos de cada uma.
        $perUnit = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit)
            ->selectRaw('unit_id, COUNT(*) as count, SUM(total_price) as revenue')
            ->groupBy('unit_id')
            ->orderByDesc('revenue')
            ->with(['unit' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->get()
            ->map(fn ($row) => [
                'unit_id' => $row->unit_id,
                'name' => $row->unit?->name ?? '—',
                'count' => (int) $row->count,
                'revenue' => (float) $row->revenue,
            ]);

        $topBarbers = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit)
            ->whereNotNull('barber_id')
            ->selectRaw('barber_id, COUNT(*) as count, SUM(total_price) as revenue')
            ->groupBy('barber_id')
            ->orderByDesc('revenue')
            ->limit(5)
            // withTrashed: barbeiro excluído ainda aparece no ranking histórico.
            ->with(['barber' => fn ($q) => $q->withTrashed()->select('id', 'name')])
            ->get()
            ->map(fn ($row) => [
                'barber_id' => $row->barber_id,
                'name' => $row->barber?->name ?? '—',
                'count' => (int) $row->count,
                'revenue' => (float) $row->revenue,
            ]);

        $topCustomers = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to])
            ->tap($byUnit)
            ->selectRaw('customer_id, COUNT(*) as count, SUM(total_price) as revenue')
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('customer:id,name')
            ->get()
            ->map(fn ($row) => [
                'customer_id' => $row->customer_id,
                'name' => $row->customer?->name ?? '—',
                'count' => (int) $row->count,
                'revenue' => (float) $row->revenue,
            ]);

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'revenue' => $revenue,
                'avg_ticket' => $completedCount > 0 ? round($revenue / $completedCount, 2) : 0,
                'completed_count' => $completedCount,
                'cancelled_count' => $cancelledCount,
                'no_show_count' => $noShowCount,
                'new_customers' => $newCustomers,
                'commissions_pending' => $commissionsPending,
                'commissions_paid' => $commissionsPaid,
                // O que sobra pra barbearia depois de pagar (e provisionar) os funcionários.
                'net_revenue' => round($revenue - $commissionsPending - $commissionsPaid, 2),
                'per_unit' => $perUnit,
                'top_barbers' => $topBarbers,
                'top_customers' => $topCustomers,
            ],
        ]);
    }
}
