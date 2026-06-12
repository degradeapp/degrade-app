<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Whatsapp\Models\WhatsappAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappClient
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.whatsapp.api_url', 'https://graph.facebook.com/v18.0');
    }

    public function sendText(WhatsappAccount $account, string $to, string $message): ?string
    {
        if (! $account->is_active) {
            Log::warning('WhatsApp account not active', ['tenant_id' => $account->tenant_id]);

            return null;
        }

        try {
            $response = Http::withToken($account->access_token)
                ->timeout(10)
                ->post("{$this->baseUrl}/{$account->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalizePhone($to),
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            if ($response->successful()) {
                return $response->json('messages.0.id');
            }

            Log::warning('WhatsApp send failed', [
                'status' => $response->status(),
                'body' => $response->json(),
                'tenant_id' => $account->tenant_id,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::error('WhatsApp client exception', [
                'message' => $e->getMessage(),
                'tenant_id' => $account->tenant_id,
            ]);

            return null;
        }
    }

    public function sendTemplate(WhatsappAccount $account, string $to, string $templateName, array $params = []): ?string
    {
        if (! $account->is_active) {
            return null;
        }

        try {
            $response = Http::withToken($account->access_token)
                ->timeout(10)
                ->post("{$this->baseUrl}/{$account->phone_number_id}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->normalizePhone($to),
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => 'pt_BR'],
                        'components' => empty($params) ? [] : [[
                            'type' => 'body',
                            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $params),
                        ]],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('messages.0.id');
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) === 11 || strlen($digits) === 10) {
            return '55'.$digits;
        }

        return $digits;
    }
}
