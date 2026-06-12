<?php

namespace App\Jobs;

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantContext;
use App\Modules\Whatsapp\Models\WhatsappAccount;
use App\Modules\Whatsapp\Services\BotEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingWhatsappMessage implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $phoneNumberId,
        public string $fromPhone,
        public string $text,
        public ?string $messageId = null,
    ) {}

    public function handle(BotEngine $engine): void
    {
        $account = WhatsappAccount::where('phone_number_id', $this->phoneNumberId)->first();
        if (! $account) {
            return;
        }

        $tenant = Tenant::find($account->tenant_id);
        if (! $tenant) {
            return;
        }

        app(TenantContext::class)->set($tenant);
        app()->instance('tenant', $tenant);

        $engine->handleIncoming($tenant, $this->fromPhone, $this->text);
    }
}
