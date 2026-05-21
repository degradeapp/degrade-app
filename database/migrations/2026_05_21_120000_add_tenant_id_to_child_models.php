<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_services', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'appointment_id']);
        });

        Schema::table('barber_schedules', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'barber_id']);
        });

        Schema::table('barber_time_offs', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'barber_id']);
        });
    }

    public function down(): void
    {
        Schema::table('barber_time_offs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'barber_id']);
            $table->dropForeignKeyConstraints();
            $table->dropColumn('tenant_id');
        });

        Schema::table('barber_schedules', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'barber_id']);
            $table->dropForeignKeyConstraints();
            $table->dropColumn('tenant_id');
        });

        Schema::table('appointment_services', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'appointment_id']);
            $table->dropForeignKeyConstraints();
            $table->dropColumn('tenant_id');
        });
    }
};
