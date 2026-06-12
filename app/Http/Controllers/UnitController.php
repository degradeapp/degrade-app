<?php

namespace App\Http\Controllers;

use App\Modules\Unit\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $units = Unit::withCount(['barbers' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $u) => $this->toArray($u));

        return response()->json(['data' => $units]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = app('tenant');

        // Multiunidade é exclusiva do plano Rede. Solo/Barbearia ficam com 1 unidade.
        if (! $tenant->canAddUnit()) {
            return response()->json([
                'message' => "Seu plano permite {$tenant->effectiveUnitLimit()} unidade(s). Mude para o plano Rede para abrir várias unidades.",
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'name' => 'required|string|min:2|max:100',
            'address' => 'nullable|string|max:200',
        ]);

        $unit = Unit::create([
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'is_active' => true,
        ]);

        return response()->json(['data' => $this->toArray($unit)], Response::HTTP_CREATED);
    }

    public function update(Unit $unit, Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|min:2|max:100',
            'address' => 'nullable|string|max:200',
            'is_active' => 'sometimes|boolean',
        ]);

        $unit->update($data);

        return response()->json(['data' => $this->toArray($unit->fresh())]);
    }

    public function destroy(Unit $unit): JsonResponse|Response
    {
        // Não dá pra remover a única unidade (a rede ficaria sem agenda).
        if (Unit::where('is_active', true)->count() <= 1) {
            return response()->json(['message' => 'Não é possível remover a única unidade da rede.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Tem barbeiro ativo nela? Move/desative antes (senão o barbeiro fica sem unidade).
        if ($unit->barbers()->where('is_active', true)->exists()) {
            return response()->json([
                'message' => 'Esta unidade ainda tem barbeiros ativos. Mova-os para outra unidade antes de removê-la.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $unit->delete(); // soft-delete: histórico de agendamentos mantém o vínculo.

        return response()->noContent();
    }

    /**
     * Troca a unidade ativa (dono/gerente). null/'all' = consolidado (todas).
     * Barbeiro/recepção nem chegam aqui (rota é só owner/manager).
     */
    public function switch(Request $request): JsonResponse
    {
        $val = $request->input('unit_id');

        if ($val === null || $val === 'all') {
            session()->forget('active_unit_id');
        } elseif (Unit::where('id', (int) $val)->exists()) {
            session(['active_unit_id' => (int) $val]);
        }

        return response()->json(['ok' => true]);
    }

    private function toArray(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'address' => $unit->address,
            'is_active' => (bool) $unit->is_active,
            'barbers_count' => (int) ($unit->barbers_count ?? $unit->barbers()->where('is_active', true)->count()),
        ];
    }
}
