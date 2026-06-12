<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regra: o dono fica com 100% do próprio serviço (ele não paga comissão a si mesmo;
 * o CommissionService nem gera comissão pra dono). Contas novas já nascem assim
 * (RegisterTenantOwner). Aqui alinhamos as barbearias ANTIGAS, cujo barbeiro-dono foi
 * criado pelo onboarding antigo com o padrão de 50%.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('barbers')
            ->whereIn('user_id', function ($q) {
                $q->select('id')->from('users')->where('role', 'owner');
            })
            ->update(['default_commission_percentage' => 100]);
    }

    public function down(): void
    {
        // Sem rollback: não guardamos o valor anterior (e é só cosmético pro dono).
    }
};
