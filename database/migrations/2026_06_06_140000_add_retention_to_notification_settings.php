<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            // Transacional (utility no WhatsApp): pode ligar por padrão.
            $table->boolean('appointment_rescheduled')->default(true)->after('appointment_cancelled');

            // Marketing (exige consentimento/LGPD): desligado por padrão.
            $table->boolean('review_request')->default(false)->after('appointment_rescheduled');
            $table->boolean('winback_enabled')->default(false)->after('review_request');
            $table->unsignedSmallInteger('winback_days')->default(30)->after('winback_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn(['appointment_rescheduled', 'review_request', 'winback_enabled', 'winback_days']);
        });
    }
};
