<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('barber_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('day_of_week')->comment('0=Sunday, 1=Monday, ..., 6=Saturday');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();
            $table->unique(['barber_id', 'day_of_week']);
            $table->index(['barber_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('barber_schedules');
    }
};
