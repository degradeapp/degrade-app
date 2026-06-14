<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingWhatsappMessage;
use App\Modules\Whatsapp\Enums\WhatsappBotState;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use App\Modules\Whatsapp\Models\WhatsappConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsappController extends Controller
{
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function webhook(Request $request): Response
    {
        // SEGURANÇA: a Meta assina todo POST com X-Hub-Signature-256 (HMAC do corpo
        // com o App Secret). Sem essa checagem, qualquer um que descubra a URL
        // injeta "mensagens" falsas e cria agendamentos/conversas em nome de
        // clientes. Mesmo padrão do webhook Asaas: sem secret configurado
        // (dev/local/teste) não há como verificar — aceita; com secret, exige.
        if (! $this->verifySignature($request)) {
            Log::warning('WhatsApp webhook signature verification failed');

            return response('Unauthorized', Response::HTTP_UNAUTHORIZED);
        }

        $payload = $request->all();
        // LGPD: NÃO logar o payload completo (contém telefone e texto da mensagem
        // do cliente = dado pessoal). Só metadata suficiente pra operar/debugar.
        Log::info('WhatsApp webhook received', ['entries' => count($payload['entry'] ?? [])]);

        $entries = $payload['entry'] ?? [];
        foreach ($entries as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                foreach (($value['messages'] ?? []) as $message) {
                    if (! $phoneNumberId) {
                        continue;
                    }

                    $from = $message['from'] ?? null;
                    $text = $message['text']['body'] ?? null;
                    $messageId = $message['id'] ?? null;
                    if (! $from || ! $text) {
                        continue;
                    }

                    ProcessIncomingWhatsappMessage::dispatch(
                        phoneNumberId: $phoneNumberId,
                        fromPhone: $from,
                        text: $text,
                        messageId: $messageId,
                    );
                }
            }
        }

        return response('', 200);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('services.whatsapp.app_secret');

        if (! $secret) {
            // B2: em PRODUÇÃO fecha a porta (fail closed): sem secret não dá pra
            // confiar no remetente, então rejeita. Dev/local/teste aceita (não há
            // o que verificar). Configurar WHATSAPP_APP_SECRET é obrigatório no deploy.
            if (app()->environment('production')) {
                Log::critical('WHATSAPP_APP_SECRET ausente em produção: webhook rejeitado.');

                return false;
            }

            return true;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        // Comparação em tempo constante (anti timing attack), igual ao Asaas.
        return hash_equals($expected, $header);
    }

    public function listAccounts(): JsonResponse
    {
        $account = WhatsappAccount::where('tenant_id', app('tenant')->id)->first();

        return response()->json([
            'data' => $account ? [
                'id' => $account->id,
                'phone_number_id' => $account->phone_number_id,
                'is_active' => $account->is_active,
                'verified_at' => $account->verified_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function upsertAccount(Request $request): JsonResponse
    {
        $request->validate([
            'phone_number_id' => 'required|string|max:100',
            'access_token' => 'required|string|min:10|max:500',
        ]);

        $tenantId = app('tenant')->id;

        $account = WhatsappAccount::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'phone_number_id' => $request->input('phone_number_id'),
                'access_token' => $request->input('access_token'),
                'is_active' => true,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $account->id,
                'phone_number_id' => $account->phone_number_id,
                'is_active' => $account->is_active,
            ],
        ]);
    }

    public function listConversations(): JsonResponse
    {
        $conversations = WhatsappConversation::with('customer')
            ->where('tenant_id', app('tenant')->id)
            ->orderByDesc('last_interaction_at')
            ->take(50)
            ->get()
            ->map(fn (WhatsappConversation $c) => [
                'id' => $c->id,
                'phone_number' => $c->phone_number,
                'customer_name' => $c->customer?->name,
                'state' => $c->state instanceof \BackedEnum ? $c->state->value : $c->state,
                'state_label' => $c->state instanceof WhatsappBotState ? $c->state->label() : '',
                'last_interaction_at' => $c->last_interaction_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $conversations]);
    }

    public function showConversation(WhatsappConversation $conversation): JsonResponse
    {
        if ($conversation->tenant_id !== app('tenant')->id) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'phone_number' => $conversation->phone_number,
                'state' => $conversation->state instanceof \BackedEnum ? $conversation->state->value : $conversation->state,
                'messages' => $conversation->messages()
                    ->orderBy('created_at')
                    ->take(100)
                    ->get()
                    ->map(fn ($m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'content' => $m->content,
                        'created_at' => $m->created_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }
}
