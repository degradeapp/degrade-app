<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O plano Rede foi extinto (um tenant = uma barbearia = um local). Tenants
     * que estavam no Rede viram Barbearia (mesmo limite de 10 profissionais).
     * Roda ANTES da migração que derruba units/unit_id: nenhum tenant pode
     * ficar com um valor de plano que o enum BillingPlan não conhece mais.
     */
    public function up(): void
    {
        DB::table('tenants')->where('plan', 'rede')->update(['plan' => 'barbearia']);
    }

    public function down(): void
    {
        // Sem volta: não dá pra saber quais tenants eram Rede. O fallback
        // defensivo de Tenant::currentPlan() cobre qualquer resíduo.
    }
};
