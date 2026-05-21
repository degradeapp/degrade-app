<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price_snapshot', 10, 2)->comment('Snapshot do preço no momento da criação');
            $table->decimal('commission_percentage_snapshot', 5, 2)->comment('Snapshot da comissão');
            $table->timestamps();
            $table->unique(['appointment_id', 'service_id']);
            $table->index(['barber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_services');
    }
};
