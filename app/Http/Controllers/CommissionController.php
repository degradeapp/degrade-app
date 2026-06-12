<?php

namespace App\Http\Controllers;

use App\Http\Resources\CommissionResource;
use App\Modules\Commission\Models\Commission;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommissionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        // withTrashed: comissões de barbeiro já excluído continuam mostrando o nome.
        $query = Commission::with(['barber' => fn ($q) => $q->withTrashed(), 'appointment']);

        if (request('status')) {
            $query->where('status', request('status'));
        }

        if (request('barber_id')) {
            $query->where('barber_id', request('barber_id'));
        }

        if (request('month')) {
            $month = request('month'); // format: YYYY-MM
            $parts = explode('-', $month);
            $query->byMonth((int) $parts[0], (int) $parts[1]);
        }

        $commissions = $query->paginate(15);

        return CommissionResource::collection($commissions);
    }

    /**
     * Pendentes agrupadas por barbeiro (a base do "fechar pagamento"): cada barbeiro
     * com quantas comissões e o total a receber no período. É o que a tela mostra
     * em vez de uma lista solta de comissões corte-a-corte.
     */
    public function pendingSummary(Request $request): JsonResponse
    {
        $query = Commission::pending()->with(['barber' => fn ($q) => $q->withTrashed()->select('id', 'name')]);

        if ($request->filled('month')) {
            [$year, $month] = explode('-', $request->input('month'));
            $query->byMonth((int) $year, (int) $month);
        }

        $groups = $query->orderByDesc('reference_date')->get()
            ->groupBy('barber_id')
            ->map(fn ($items) => [
                'barber_id' => (int) $items->first()->barber_id,
                'barber_name' => $items->first()->barber?->name ?? '—',
                'count' => $items->count(),
                'total' => (float) $items->sum('amount'),
                // Itens individuais — pra poder pagar uma a uma (além do "pagar tudo").
                'items' => $items->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'amount' => (float) $c->amount,
                    'reference_date' => $c->reference_date?->toDateString(),
                ])->values(),
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json(['data' => $groups]);
    }

    /**
     * Fecha o pagamento de UM barbeiro: marca todas as comissões pendentes dele
     * (no mês, se informado) como pagas de uma vez. Substitui o marcar-uma-a-uma.
     */
    public function payBarber(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barber_id' => 'required|integer',
            'month' => 'nullable|string',
        ]);

        $query = Commission::pending()->where('barber_id', $data['barber_id']);

        if (! empty($data['month'])) {
            [$year, $month] = explode('-', $data['month']);
            $query->byMonth((int) $year, (int) $month);
        }

        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('amount');
        $query->update(['status' => 'paid', 'paid_at' => Carbon::now()]);

        return response()->json(['paid_count' => $count, 'paid_total' => $total]);
    }

    public function show(Commission $commission): JsonResponse
    {
        $this->authorize('view', $commission);

        return response()->json(new CommissionResource($commission->load(['barber' => fn ($q) => $q->withTrashed(), 'appointment'])));
    }

    public function markAsPaid(Commission $commission): JsonResponse
    {
        $this->authorize('view', $commission);

        $commission->update([
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        return response()->json(new CommissionResource($commission->load(['barber' => fn ($q) => $q->withTrashed(), 'appointment'])));
    }
}
