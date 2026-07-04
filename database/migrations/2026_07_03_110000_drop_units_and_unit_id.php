<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove a camada de multiunidade do schema: colunas unit_id (appointments,
     * barbers, users) e a tabela units. Os índices que sustentam agenda e
     * dashboard NÃO passam por aqui: (tenant_id, starts_at),
     * (tenant_id, barber_id, starts_at) e (tenant_id, status, starts_at)
     * existem desde a criação de appointments e continuam intactos.
     *
     * Ordem importa no SQLite: índice primeiro, depois FK + coluna (o
     * dropForeign recria a tabela sem a FK inline, liberando o DROP COLUMN
     * nativo). No Postgres, dropForeign/dropColumn são ALTERs diretos.
     */
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'unit_id', 'starts_at']);
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'unit_id']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn('unit_id');
        });

        Schema::dropIfExists('units');
    }

    public function down(): void
    {
        // Recria a ESTRUTURA da multiunidade (sem repopular dados: as unidades
        // dropadas não existem mais; unit_id volta como null em tudo).
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

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'unit_id', 'starts_at']);
        });

        Schema::table('barbers', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'unit_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
        });
    }
};
