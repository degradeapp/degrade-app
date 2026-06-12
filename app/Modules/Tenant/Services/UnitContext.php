<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Unit\Models\Unit;
use Illuminate\Support\Collection;

/**
 * Unidade ATIVA da requisição (espelha o TenantContext, mas pra unidades dentro
 * do tenant). Segurança: a fronteira dura continua sendo o tenant; isto é uma
 * camada de escopo DENTRO do tenant.
 *
 * - Barbeiro/recepção (têm unit_id "casa"): presos na unidade deles, NÃO trocam.
 * - Dono/gerente (unit_id null): trocam de unidade; sem seleção = consolidado (todas).
 */
class UnitContext
{
    protected ?int $activeUnitId = null;

    protected bool $locked = false;

    protected bool $resolved = false;

    protected ?Collection $units = null;

    public function resolve(): void
    {
        // Reset: resolve() precisa ser idempotente (o singleton pode ser reusado, ex.: em
        // testes com múltiplas requests). Sem isso, o estado de uma request vazava pra outra.
        $this->activeUnitId = null;
        $this->locked = false;
        $this->units = null;
        $this->resolved = false;

        if (! app()->has('tenant')) {
            return;
        }

        // Unidades ativas do tenant atual (já filtradas por TenantScope).
        $this->units = Unit::where('is_active', true)->orderBy('id')->get();

        $user = auth()->user();

        if (! $user) {
            $this->resolved = true;

            return;
        }

        // Gateado por PAPEL (não por unit_id): só dono/gerente trocam de unidade / veem
        // consolidado. Barbeiro/recepção SEMPRE travados numa unidade — segurança: um
        // barbeiro sem unidade atribuída NÃO pode cair no modo "todas".
        if ($user->isOwner() || $user->isManager()) {
            // query() só lê a query string — NÃO o parâmetro de rota {unit} (que é um
            // modelo bindado em /units/{unit}), evitando "Object não pode virar int".
            $req = request()->query('unit');
            $sel = session('active_unit_id');

            if ($req !== null && $this->isValid((int) $req)) {
                $this->activeUnitId = (int) $req;
                session(['active_unit_id' => $this->activeUnitId]);
            } elseif ($sel !== null && $this->isValid((int) $sel)) {
                $this->activeUnitId = (int) $sel;
            }
            // senão: null = consolidado (todas as unidades)
        } else {
            // Barbeiro/recepção: unidade-casa, ou a 1ª como fallback seguro (nunca "todas").
            $home = ($user->unit_id && $this->isValid((int) $user->unit_id))
                ? (int) $user->unit_id
                : $this->units->first()?->id;

            if ($home) {
                $this->activeUnitId = $home;
                $this->locked = true;
            }
        }

        $this->resolved = true;
    }

    /**
     * Unidade concreta pra AGENDA / DISPONIBILIDADE / GRAVAÇÃO (nunca null quando há
     * unidades): se dono/gerente está em consolidado, a agenda usa a 1ª unidade.
     */
    public function currentUnitId(): ?int
    {
        if ($this->activeUnitId !== null) {
            return $this->activeUnitId;
        }

        return $this->units?->first()?->id;
    }

    /**
     * Unidade pra DASHBOARD / RELATÓRIO: null = consolidado (todas), só dono/gerente.
     * Barbeiro/recepção sempre têm uma unidade concreta aqui.
     */
    public function scopedUnitId(): ?int
    {
        return $this->activeUnitId;
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function canSwitch(): bool
    {
        return ! $this->locked;
    }

    public function units(): Collection
    {
        return $this->units ?? collect();
    }

    public function hasMultiple(): bool
    {
        return $this->units()->count() > 1;
    }

    protected function isValid(int $id): bool
    {
        return $this->units !== null && $this->units->contains('id', $id);
    }
}
