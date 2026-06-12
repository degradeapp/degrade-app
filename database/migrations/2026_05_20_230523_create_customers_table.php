<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->index();
            $table->string('email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('total_visits')->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->timestamp('last_visit_at')->nullable();
            $table->softDeletesDatetime('deleted_at');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX customers_tenant_phone_unique ON customers (tenant_id, phone) WHERE deleted_at IS NULL');
        } else {
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['tenant_id', 'phone'], 'customers_tenant_phone_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
