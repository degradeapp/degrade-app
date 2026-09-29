<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cobrança real no Asaas:
     * - billing_document: CPF/CNPJ do titular (o Asaas recusa cliente sem documento
     *   válido). Gravado criptografado, por isso text (ver cicatriz do varchar no pgsql).
     * - payment_url / next_due_date: fatura em aberto e vencimento, vindos da API e
     *   atualizados pelos webhooks — a tela de cobrança não consulta o Asaas a cada GET.
     * - asaas_webhook_events: o Asaas entrega "at least once"; o id do evento é a chave
     *   de idempotência (reenvio não reprocessa) e o payment_id permite ignorar um
     *   OVERDUE que chega atrasado depois do pagamento já confirmado.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('billing_document')->nullable();
            $table->text('payment_url')->nullable();
            $table->date('next_due_date')->nullable();
        });

        Schema::create('asaas_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('event', 80);
            $table->string('payment_id')->nullable()->index();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asaas_webhook_events');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['billing_document', 'payment_url', 'next_due_date']);
        });
    }
};
