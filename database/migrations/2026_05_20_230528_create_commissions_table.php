<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reference_type')->comment('appointment, bonus, deduction, etc');
            $table->string('status')->default('pending');
            $table->decimal('amount', 10, 2);
            $table->date('reference_date');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'barber_id', 'status', 'reference_date']);
            $table->index(['tenant_id', 'reference_date']);
            $table->index(['appointment_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('commissions');
    }
};
