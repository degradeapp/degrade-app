<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('whatsapp_conversations')->cascadeOnDelete();
            $table->string('message_id')->unique();
            $table->string('direction')->comment('incoming or outgoing');
            $table->string('type')->default('text');
            $table->text('content');
            $table->string('status')->nullable()->comment('pending, sent, delivered, read, failed');
            $table->timestamps();
            $table->index(['conversation_id', 'created_at']);
            $table->index(['message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
