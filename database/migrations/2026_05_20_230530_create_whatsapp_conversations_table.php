<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('phone_number')->index();
            $table->string('state')->default('greeting');
            $table->json('session_data')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamp('idle_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone_number']);
            $table->index(['tenant_id', 'state', 'last_interaction_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
