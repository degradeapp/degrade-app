<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barber_time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['barber_id', 'date']);
            $table->index(['barber_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_time_offs');
    }
};
