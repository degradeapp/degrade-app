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

        $completedQuery = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to]);

        $revenue = (float) (clone $completedQuery)->sum('total_price');
        $completedCount = (clone $completedQuery)->count();

        $cancelledCount = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::cancelled->value)
            ->whereBetween('starts_at', [$from, $to])
            ->count();

        $noShowCount = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::no_show->value)
            ->whereBetween('starts_at', [$from, $to])
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
            ->sum('amount');

        $commissionsPaid = (float) Commission::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereBetween('reference_date', [$from, $to])
            ->sum('amount');

        $topBarbers = Appointment::where('tenant_id', $tenantId)
            ->where('status', AppointmentStatus::completed->value)
            ->whereBetween('starts_at', [$from, $to])
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
                'top_barbers' => $topBarbers,
                'top_customers' => $topCustomers,
            ],
        ]);
    }
}
