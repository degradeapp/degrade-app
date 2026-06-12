<?php

namespace App\Console\Commands;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Console\Command;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge';

    protected $description = 'Apaga em definitivo as barbearias excluídas cuja janela de recuperação (30 dias) expirou — LGPD: direito ao esquecimento.';

    public function handle(): void
    {
        $tenants = Tenant::onlyTrashed()
            ->whereNotNull('purge_scheduled_at')
            ->where('purge_scheduled_at', '<=', now())
            ->get();

        foreach ($tenants as $tenant) {
            // forceDelete remove de verdade a linha da barbearia. Todos os FKs tenant_id
            // usam cascadeOnDelete, então usuários, barbeiros, agendamentos, comissões,
            // serviços etc. somem junto no banco (erasure completo).
            $tenant->forceDelete();

            $this->info("Barbearia apagada em definitivo (ID: {$tenant->id}).");
        }

        $this->info("Purga concluída: {$tenants->count()} barbearia(s).");
    }
}
