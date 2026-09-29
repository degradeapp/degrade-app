<?php

namespace App\Modules\Customer\Actions;

use App\Modules\Customer\Models\Customer;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Eliminação dos dados pessoais de um cliente a pedido dele (LGPD, art. 18, VI).
 *
 * Excluir comum é soft-delete: some da lista, mas nome/telefone continuam no banco
 * pra sempre. Aqui os dados pessoais são APAGADOS de verdade, e o histórico
 * financeiro (atendimentos, valores, comissões) fica — a barbearia precisa dele
 * (obrigação legal/contábil, art. 16, I) e ele não identifica mais ninguém.
 *
 * Onde o dado pessoal morava além da própria linha do cliente:
 * - activity_log: o AuditObserver grava os atributos completos em cada
 *   criação/edição/exclusão (old_values/new_values/metadata.changes);
 * - conversas e mensagens de WhatsApp do bot.
 */
readonly class EraseCustomerData
{
    public function __invoke(Customer $customer, int $userId): void
    {
        DB::transaction(function () use ($customer, $userId) {
            $phone = $customer->phone;

            // saveQuietly: o observer de auditoria gravaria os dados antigos (justo o
            // que está sendo apagado) em old_values.
            $customer->forceFill([
                'name' => 'Cliente removido',
                'phone' => null,
                'email' => null,
                'notes' => null,
                'is_active' => false,
                'anonymized_at' => now(),
                'deleted_by' => $userId,
                'deleted_at' => $customer->deleted_at ?? now(),
            ])->saveQuietly();

            DB::table('activity_log')
                ->where('tenant_id', $customer->tenant_id)
                ->where('model_type', Customer::class)
                ->where('model_id', $customer->id)
                ->update(['old_values' => null, 'new_values' => null, 'metadata' => null]);

            $conversationIds = DB::table('whatsapp_conversations')
                ->where('tenant_id', $customer->tenant_id)
                ->where(function ($q) use ($customer, $phone) {
                    $q->where('customer_id', $customer->id);
                    if ($phone) {
                        $q->orWhere('phone_number', $phone);
                    }
                })
                ->pluck('id');

            DB::table('whatsapp_messages')->whereIn('conversation_id', $conversationIds)->delete();
            DB::table('whatsapp_conversations')->whereIn('id', $conversationIds)->delete();

            // Registro do ATO (quem apagou e quando), sem nenhum dado pessoal.
            ActivityLogger::custom($customer->tenant_id, 'erased', Customer::class, $customer->id);
        });
    }
}
