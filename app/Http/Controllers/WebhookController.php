<?php

namespace App\Http\Controllers;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Webhook do Asaas, no contrato REAL (docs.asaas.com):
 * - autenticação: token puro no header `asaas-access-token` (o authToken cadastrado
 *   no painel do Asaas). NÃO é HMAC — a versão antiga esperava `asaas-signature` e
 *   recusaria todo evento real.
 * - corpo: {id, event, dateCreated, payment|subscription: {...}}, eventos em
 *   SCREAMING_SNAKE_CASE (PAYMENT_CONFIRMED, PAYMENT_OVERDUE, SUBSCRIPTION_DELETED...).
 * - entrega "at least once": o `id` do evento vira chave única (reenvio = no-op).
 * - responder 2xx rápido: aqui só há updates locais; evento que não nos interessa
 *   também é 200 (senão o Asaas re-tenta e, após 15 falhas, pausa a fila inteira).
 */
class WebhookController extends Controller
{
    private const PAID = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'];

    public function handleAsaasWebhook(Request $request): Response
    {
        if (! $this->verifyToken($request)) {
            Log::warning('Asaas webhook: token inválido');

            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $eventId = (string) $request->input('id');
        $event = (string) $request->input('event');

        if ($eventId === '' || $event === '') {
            return response('', Response::HTTP_OK);
        }

        $payment = (array) $request->input('payment', []);
        $subscription = (array) $request->input('subscription', []);
        $customerId = $payment['customer'] ?? $subscription['customer'] ?? null;
        $tenant = $customerId ? Tenant::where('asaas_customer_id', $customerId)->first() : null;

        try {
            DB::transaction(function () use ($eventId, $event, $payment, $subscription, $tenant) {
                DB::table('asaas_webhook_events')->insert([
                    'event_id' => $eventId,
                    'event' => substr($event, 0, 80),
                    'payment_id' => $payment['id'] ?? null,
                    'tenant_id' => $tenant?->id,
                    'created_at' => now(),
                ]);

                if ($tenant) {
                    $this->apply($tenant->newQuery()->lockForUpdate()->find($tenant->id), $event, $payment, $subscription);
                }
            });
        } catch (UniqueConstraintViolationException) {
            // Reenvio de um evento já processado.
        }

        Log::info('Asaas webhook', ['event' => $event, 'tenant_id' => $tenant?->id]);

        return response('', Response::HTTP_OK);
    }

    private function apply(Tenant $tenant, string $event, array $payment, array $subscription): void
    {
        // Cobrança/assinatura que não é a atual do tenant (ex.: a que ele cancelou antes
        // de assinar de novo) não pode mexer no status de hoje.
        $subscriptionId = $payment['subscription'] ?? $subscription['id'] ?? null;
        if ($subscriptionId && $subscriptionId !== $tenant->asaas_subscription_id) {
            return;
        }

        match (true) {
            in_array($event, self::PAID, true) => $tenant->update([
                'status' => 'active',
                'payment_url' => null,
            ]),

            $event === 'PAYMENT_CREATED' => $tenant->update([
                'payment_url' => $payment['invoiceUrl'] ?? $tenant->payment_url,
                'next_due_date' => $payment['dueDate'] ?? $tenant->next_due_date,
            ]),

            $event === 'PAYMENT_OVERDUE' => $this->markOverdue($tenant, $payment),

            // Dinheiro devolvido/contestado: a fatura deixou de estar paga.
            in_array($event, ['PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED'], true) => $tenant->update(['status' => 'past_due']),

            in_array($event, ['SUBSCRIPTION_DELETED', 'SUBSCRIPTION_INACTIVATED'], true) => $tenant->update([
                'status' => 'cancelled',
                'payment_url' => null,
            ]),

            default => null,
        };
    }

    /**
     * Fora de ordem: o OVERDUE de uma cobrança que já teve CONFIRMED/RECEIVED
     * processado (reenvio atrasado) é ignorado — não pode derrubar quem pagou.
     */
    private function markOverdue(Tenant $tenant, array $payment): void
    {
        $alreadyPaid = isset($payment['id']) && DB::table('asaas_webhook_events')
            ->where('payment_id', $payment['id'])
            ->whereIn('event', self::PAID)
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $tenant->update([
            'status' => 'past_due',
            'payment_url' => $payment['invoiceUrl'] ?? $tenant->payment_url,
        ]);
    }

    private function verifyToken(Request $request): bool
    {
        $secret = (string) config('services.asaas.webhook_secret');

        // Sem token configurado: fecha a porta (fail closed) em tudo que é publicado
        // (produção E staging) — senão um POST forjado "ativaria" assinatura de graça.
        // Só local/testes aceitam sem token.
        if ($secret === '') {
            if (! app()->environment(['local', 'testing'])) {
                Log::critical('ASAAS_WEBHOOK_SECRET ausente fora do ambiente local: webhook rejeitado.');

                return false;
            }

            return true;
        }

        return hash_equals($secret, (string) $request->header('asaas-access-token'));
    }
}
