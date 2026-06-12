<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Consentimento (opt-in) para mensagens de MARKETING (avaliação, retorno).
            // LGPD: marketing exige autorização explícita; padrão é FALSE (nunca pré-marcado).
            // marketing_consent_at guarda QUANDO o cliente autorizou (trilha de prova).
            $table->boolean('accepts_marketing')->default(false)->after('notes');
            $table->timestamp('marketing_consent_at')->nullable()->after('accepts_marketing');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['accepts_marketing', 'marketing_consent_at']);
        });
    }
};
