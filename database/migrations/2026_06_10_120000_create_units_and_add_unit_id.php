<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Tabela de unidades (locais de uma rede). Sempre dentro de um tenant.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        // 2) unit_id nas tabelas que são POR unidade. Nullable no banco (SQLite-safe);
        //    a obrigatoriedade é garantida no app (sempre setado na criação).
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'unit_id', 'starts_at']);
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'unit_id']);
        });

        // Unidade "casa" do usuário: barbeiro/recepção ficam presos nela; dono/gerente null (todas).
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
        });

        // 3) Backfill seguro: cada tenant existente ganha 1 "Unidade principal" e todos os
        //    seus agendamentos/barbeiros passam a apontar pra ela (evita órfão). Usa query
        //    crua (DB::) de propósito, pra rodar pra TODOS os tenants sem o escopo global.
        foreach (DB::table('tenants')->get() as $tenant) {
            $unitId = DB::table('units')->insertGetId([
                'tenant_id' => $tenant->id,
                'name' => $tenant->name ?: 'Unidade principal',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('appointments')->where('tenant_id', $tenant->id)->update(['unit_id' => $unitId]);
            DB::table('barbers')->where('tenant_id', $tenant->id)->update(['unit_id' => $unitId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'unit_id']);
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'unit_id', 'starts_at']);
            $table->dropConstrainedForeignId('unit_id');
        });

        Schema::dropIfExists('units');
    }
};
