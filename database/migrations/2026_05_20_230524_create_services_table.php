<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('commission_percentage', 5, 2)->nullable()->comment('null = use barber default');
            $table->boolean('is_active')->default(true);
            $table->softDeletesDatetime('deleted_at');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'is_active']);
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX services_tenant_name_unique ON services (tenant_id, name) WHERE deleted_at IS NULL');
        } else {
            Schema::table('services', function (Blueprint $table) {
                $table->unique(['tenant_id', 'name'], 'services_tenant_name_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
