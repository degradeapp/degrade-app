<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('status')->default('scheduled');
            $table->string('source')->default('manual');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->softDeletesDatetime('deleted_at');
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'starts_at']);
            $table->index(['tenant_id', 'barber_id', 'starts_at']);
            $table->index(['tenant_id', 'customer_id', 'created_at']);
            $table->index(['tenant_id', 'status', 'starts_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('appointments');
    }
};
