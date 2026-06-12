<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove o marketing pós-atendimento (avaliação/retorno) e o consentimento que existia
 * só pra gatear isso. Decisão do dono: tirar a parte de marketing por completo.
 * Transacionais (confirmação, lembrete, remarcação, cancelamento) permanecem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['review_request', 'winback_enabled', 'winback_days']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['accepts_marketing', 'marketing_consent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->boolean('review_request')->default(false)->after('appointment_rescheduled');
            $table->boolean('winback_enabled')->default(false)->after('review_request');
            $table->unsignedSmallInteger('winback_days')->default(30)->after('winback_enabled');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('accepts_marketing')->default(false)->after('notes');
            $table->timestamp('marketing_consent_at')->nullable()->after('accepts_marketing');
        });
    }
};
