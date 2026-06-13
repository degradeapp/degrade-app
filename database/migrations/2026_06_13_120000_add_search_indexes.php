<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // E7: índice de listagem/ordenação por nome (funciona em qualquer banco).
        // Antes customers só tinha [tenant_id, created_at] e phone.
        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'name'], 'customers_tenant_name_idx');
        });

        // E1: busca accent-insensitive escalável via pg_trgm + GIN. SÓ Postgres
        // (produção). SQLite (dev/teste) usa o filtro em memória do SearchService,
        // então aqui não há o que criar.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
        DB::statement('CREATE INDEX IF NOT EXISTS customers_name_trgm_idx ON customers USING gin (lower(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS barbers_name_trgm_idx ON barbers USING gin (lower(name) gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS services_name_trgm_idx ON services USING gin (lower(name) gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_tenant_name_idx');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS customers_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS barbers_name_trgm_idx');
        DB::statement('DROP INDEX IF EXISTS services_name_trgm_idx');
    }
};
