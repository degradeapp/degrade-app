<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Exclusão de conta é reversível por uma janela (grace period): a barbearia
            // some do sistema na hora, mas os dados ficam guardados até purge_scheduled_at,
            // quando são apagados em definitivo (LGPD: direito ao esquecimento).
            $table->softDeletes();
            $table->timestamp('purge_scheduled_at')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'purge_scheduled_at']);
        });
    }
};
