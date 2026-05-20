<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('channels')->default('["email", "whatsapp"]');
            $table->boolean('reminder_24h_before')->default(true);
            $table->boolean('reminder_1h_before')->default(true);
            $table->boolean('appointment_confirmed')->default(true);
            $table->boolean('appointment_cancelled')->default(true);
            $table->string('email_from')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('notification_settings');
    }
};
