<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('barber_time_off', function (Blueprint $table) {
            // Folga pode ser um período: `date` = início, `end_date` = fim (nulo = um dia só).
            $table->date('end_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('barber_time_off', function (Blueprint $table) {
            $table->dropColumn('end_date');
        });
    }
};
