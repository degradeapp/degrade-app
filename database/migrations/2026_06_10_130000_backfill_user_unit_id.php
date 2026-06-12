<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Barbeiro/recepção precisam de uma unidade-casa (segurança: sem ela, um login de
     * balcão não fica escopado). Backfill: cada barbeiro/recepção existente recebe a
     * unidade principal do seu tenant. Dono/gerente ficam null (veem todas + consolidado).
     */
    public function up(): void
    {
        foreach (DB::table('tenants')->get() as $tenant) {
            $unitId = DB::table('units')
                ->where('tenant_id', $tenant->id)
                ->orderBy('id')
                ->value('id');

            if (! $unitId) {
                continue;
            }

            DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->whereIn('role', ['barber', 'receptionist'])
                ->whereNull('unit_id')
                ->update(['unit_id' => $unitId]);
        }
    }

    public function down(): void
    {
        // Sem rollback de dados: unit_id volta a null naturalmente se a coluna for dropada.
    }
};
