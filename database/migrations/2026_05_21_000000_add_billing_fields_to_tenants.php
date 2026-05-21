<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan')->nullable()->default(null)->after('asaas_customer_id');
            $table->string('asaas_subscription_id')->nullable()->unique()->after('plan');
        });

        // Backfill: set plan = 'barbearia' for existing active tenants
        DB::table('tenants')
            ->where('status', 'active')
            ->update(['plan' => 'barbearia']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
            $table->dropUnique(['asaas_subscription_id']);
            $table->dropColumn('asaas_subscription_id');
        });
    }
};
