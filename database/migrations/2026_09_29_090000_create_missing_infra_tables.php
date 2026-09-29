<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabelas de infraestrutura que o esqueleto do Laravel cria e que ficaram de fora:
     * - cache_locks: Cache::lock() e withoutOverlapping() com CACHE_STORE=database
     *   (o padrão de produção) estouravam "relation cache_locks does not exist".
     * - failed_jobs: com QUEUE_CONNECTION=database, um job que esgota as tentativas
     *   não tinha onde ser gravado — a falha sumia sem rastro.
     */
    public function up(): void
    {
        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->string('owner');
                $table->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('cache_locks');
    }
};
