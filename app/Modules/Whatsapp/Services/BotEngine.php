<?php

namespace App\Modules\Whatsapp\Services;

use App\Enums\AppointmentSource;
use App\Modules\Appointment\Actions\CreateAppointment;
use App\Modules\Barber\Models\Barber;
use App\Modules\Customer\Models\Customer;
use App\Modules\Service\Models\Service;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Whatsapp\Enums\WhatsappBotState;
use App\Modules\Whatsapp\Models\WhatsappConversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class BotEngine
{
    public function __construct(
        private WhatsappClient $client,
        private CreateAppointment $createAppointment,
    ) {}

    public function handleIncoming(Tenant $tenant, string $fromPhone, string $text): void
    {
        $conversation = $this->findOrCreateConversation($tenant, $fromPhone);

        if ($conversation->state === WhatsappBotState::human_handoff) {
            return;
        }

        // Anti-spam: máx. 15 mensagens/min por telefone+tenant (flood do bot é descartado)
        $throttleKey = "wabot:{$tenant->id}:{$fromPhone}";
        if (RateLimiter::tooManyAttempts($throttleKey, 15)) {
            return;
        }
        RateLimiter::hit($throttleKey, 60);

        if ($this->isIdleTimeout($conversation)) {
            $conversation->state = WhatsappBotState::greeting;
            $conversation->session_data = [];
        }

        $conversation->last_interaction_at = Carbon::now();
        $conversation->idle_at = Carbon::now()->addMinutes(30);

        $reply = $this->processStep($conversation, trim($text));
        $conversation->save();

        if ($reply !== null) {
            $this->reply($tenant, $fromPhone, $reply);
        }
    }

    private function processStep(WhatsappConversation $conversation, string $input): ?string
    {
        $lower = mb_strtolower($input);
        $session = $conversation->session_data ?? [];

        if (in_array($lower, ['cancelar', 'voltar', 'inicio', 'menu'], true)) {
            $conversation->state = WhatsappBotState::greeting;
            $conversation->session_data = [];

            return $this->greetingMessage($conversation);
        }

        if (in_array($lower, ['humano', 'atendente', 'falar com alguem'], true)) {
            $conversation->state = WhatsappBotState::human_handoff;

            return 'Tudo bem, vou chamar alguém da equipe para te atender. Aguarde um instante.';
        }

        switch ($conversation->state) {
            case WhatsappBotState::greeting:
                return $this->stepGreeting($conversation, $input);

            case WhatsappBotState::choosing_service:
                return $this->stepChoosingService($conversation, $input, $session);

            case WhatsappBotState::choosing_barber:
                return $this->stepChoosingBarber($conversation, $input, $session);

            case WhatsappBotState::choosing_date:
                return $this->stepChoosingDate($conversation, $input, $session);

            case WhatsappBotState::choosing_slot:
                return $this->stepChoosingSlot($conversation, $input, $session);

            case WhatsappBotState::confirming:
                return $this->stepConfirming($conversation, $input, $session);

            case WhatsappBotState::done:
            default:
                $conversation->state = WhatsappBotState::greeting;

                return $this->greetingMessage($conversation);
        }
    }

    private function greetingMessage(WhatsappConversation $conversation): string
    {
        $tenantName = $conversation->tenant?->name ?? 'a barbearia';

        return "Olá! 👋 Bem-vindo à {$tenantName}.\n\n".
            "Posso te ajudar a agendar um horário. Responda com:\n".
            "1️⃣ Agendar atendimento\n".
            "2️⃣ Falar com atendente\n\n".
            'Você pode digitar "cancelar" a qualquer momento.';
    }

    private function stepGreeting(WhatsappConversation $conversation, string $input): string
    {
        $services = Service::where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($services->isEmpty()) {
            return 'Desculpe, a barbearia ainda não cadastrou serviços. Tente novamente em breve.';
        }

        $conversation->state = WhatsappBotState::choosing_service;
        $conversation->session_data = ['services_offered' => $services->pluck('id')->all()];

        $lines = ['Ótimo! Qual serviço você quer?', ''];
        foreach ($services as $i => $s) {
            $price = number_format($s->price, 2, ',', '.');
            $lines[] = ($i + 1).". {$s->name} (R$ {$price})";
        }
        $lines[] = '';
        $lines[] = 'Responda com o número.';

        return implode("\n", $lines);
    }

    private function stepChoosingService(WhatsappConversation $conversation, string $input, array $session): string
    {
        $idx = (int) preg_replace('/\D/', '', $input) - 1;
        $offered = $session['services_offered'] ?? [];

        if ($idx < 0 || $idx >= count($offered)) {
            return 'Não entendi. Responda com o número do serviço.';
        }

        $serviceId = $offered[$idx];
        $service = Service::find($serviceId);

        if (! $service) {
            return 'Esse serviço não está mais disponível. Digite "menu" para começar de novo.';
        }

        $session['service_id'] = $serviceId;

        $barbers = Barber::where('tenant_id', $conversation->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($barbers->isEmpty()) {
            return 'Não há barbeiros disponíveis no momento.';
        }

        $conversation->state = WhatsappBotState::choosing_barber;
        $session['barbers_offered'] = $barbers->pluck('id')->all();
        $conversation->session_data = $session;

        $lines = ["Serviço: {$service->name}", '', 'Com qual barbeiro?', ''];
        foreach ($barbers as $i => $b) {
            $lines[] = ($i + 1).". {$b->name}";
        }
        $lines[] = (count($barbers) + 1).'. Qualquer um';
        $lines[] = '';
        $lines[] = 'Responda com o número.';

        return implode("\n", $lines);
    }

    private function stepChoosingBarber(WhatsappConversation $conversation, string $input, array $session): string
    {
        $idx = (int) preg_replace('/\D/', '', $input) - 1;
        $offered = $session['barbers_offered'] ?? [];

        if ($idx === count($offered)) {
            $session['barber_id'] = null;
        } elseif ($idx < 0 || $idx >= count($offered)) {
            return 'Não entendi. Responda com o número.';
        } else {
            $session['barber_id'] = $offered[$idx];
        }

        $conversation->state = WhatsappBotState::choosing_date;
        $conversation->session_data = $session;

        return "Para qual dia?\n\n".
            "1️⃣ Hoje\n".
            "2️⃣ Amanhã\n".
            '3️⃣ Outro dia (responda com a data: DD/MM)';
    }

    private function stepChoosingDate(WhatsappConversation $conversation, string $input, array $session): string
    {
        $lower = mb_strtolower($input);
        $tz = config('app.timezone', 'America/Manaus');

        if ($lower === '1' || str_contains($lower, 'hoje')) {
            $date = Carbon::now($tz)->startOfDay();
        } elseif ($lower === '2' || str_contains($lower, 'amanh')) {
            $date = Carbon::now($tz)->addDay()->startOfDay();
        } elseif (preg_match('/(\d{1,2})[\/\-](\d{1,2})/', $input, $m)) {
            $year = Carbon::now($tz)->year;
            try {
                $date = Carbon::create($year, (int) $m[2], (int) $m[1], 0, 0, 0, $tz);
                if ($date->isPast()) {
                    $date->addYear();
                }
            } catch (\Throwable) {
                return 'Data inválida. Use o formato DD/MM, por exemplo 25/12.';
            }
        } else {
            return 'Não entendi. Responda 1 (hoje), 2 (amanhã) ou uma data DD/MM.';
        }

        $session['date'] = $date->toDateString();
        $conversation->state = WhatsappBotState::choosing_slot;
        $conversation->session_data = $session;

        $slots = $this->availableSlots($conversation->tenant_id, $session);

        if (empty($slots)) {
            $conversation->state = WhatsappBotState::choosing_date;

            return 'Não há horários disponíveis nessa data. Tente outro dia.';
        }

        $session['slots_offered'] = $slots;
        $conversation->session_data = $session;

        $lines = ["Horários disponíveis em {$date->format('d/m')}:", ''];
        foreach ($slots as $i => $s) {
            $lines[] = ($i + 1).'. '.$s;
        }
        $lines[] = '';
        $lines[] = 'Responda com o número.';

        return implode("\n", $lines);
    }

    private function stepChoosingSlot(WhatsappConversation $conversation, string $input, array $session): string
    {
        $idx = (int) preg_replace('/\D/', '', $input) - 1;
        $slots = $session['slots_offered'] ?? [];

        if ($idx < 0 || $idx >= count($slots)) {
            return 'Não entendi. Responda com o número do horário.';
        }

        $session['slot'] = $slots[$idx];
        $conversation->state = WhatsappBotState::confirming;
        $conversation->session_data = $session;

        $service = Service::find($session['service_id'] ?? 0);
        $barber = isset($session['barber_id']) ? Barber::find($session['barber_id']) : null;
        $barberName = $barber?->name ?? 'qualquer barbeiro';

        return "Confirme seu agendamento:\n\n".
            "📅 Data: {$session['date']}\n".
            "🕐 Horário: {$session['slot']}\n".
            "✂️ Serviço: {$service?->name}\n".
            "👤 Barbeiro: {$barberName}\n\n".
            'Responda SIM para confirmar ou NÃO para cancelar.';
    }

    private function stepConfirming(WhatsappConversation $conversation, string $input, array $session): string
    {
        $lower = mb_strtolower($input);

        if (in_array($lower, ['sim', 's', 'confirmar', 'ok'], true)) {
            $appointmentId = $this->createAppointmentFromSession($conversation, $session);
            $conversation->state = WhatsappBotState::done;
            $conversation->session_data = [];

            if ($appointmentId === null) {
                return '⚠️ Não consegui confirmar agora (horário pode ter ficado indisponível). Digite "menu" para tentar outro horário.';
            }

            return '✅ Agendamento confirmado! Te esperamos. Você pode digitar "menu" para fazer outro agendamento.';
        }

        $conversation->state = WhatsappBotState::greeting;
        $conversation->session_data = [];

        return 'Tudo bem, cancelei. Digite "menu" para tentar de novo.';
    }

    private function availableSlots(int $tenantId, array $session): array
    {
        $tz = config('app.timezone', 'America/Manaus');
        $date = Carbon::parse($session['date'].' 09:00', $tz);
        $end = Carbon::parse($session['date'].' 18:00', $tz);
        $slots = [];

        while ($date->lessThan($end)) {
            $slots[] = $date->format('H:i');
            $date->addMinutes(30);
            if (count($slots) >= 8) {
                break;
            }
        }

        return $slots;
    }

    private function createAppointmentFromSession(WhatsappConversation $conversation, array $session): ?int
    {
        $customer = Customer::firstOrCreate(
            ['tenant_id' => $conversation->tenant_id, 'phone' => $conversation->phone_number],
            ['name' => 'Cliente WhatsApp', 'tenant_id' => $conversation->tenant_id]
        );
        $conversation->customer_id = $customer->id;

        $tz = config('app.timezone', 'America/Manaus');
        $startsAt = Carbon::parse($session['date'].' '.$session['slot'], $tz);

        try {
            $appointment = ($this->createAppointment)(
                customerId: $customer->id,
                serviceIds: [(int) $session['service_id']],
                startsAt: $startsAt,
                source: AppointmentSource::whatsapp,
                barberIds: isset($session['barber_id']) ? [(int) $session['barber_id']] : null,
                notes: 'Agendado pelo WhatsApp Bot',
            );

            return $appointment->id;
        } catch (\Throwable $e) {
            Log::warning('BotEngine: failed to create appointment', [
                'tenant_id' => $conversation->tenant_id,
                'error' => $e->getMessage(),
                'session' => $session,
            ]);

            return null;
        }
    }

    private function findOrCreateConversation(Tenant $tenant, string $phone): WhatsappConversation
    {
        $conversation = WhatsappConversation::where('tenant_id', $tenant->id)
            ->where('phone_number', $phone)
            ->first();

        if (! $conversation) {
            $conversation = WhatsappConversation::create([
                'tenant_id' => $tenant->id,
                'phone_number' => $phone,
                'state' => WhatsappBotState::greeting,
                'session_data' => [],
                'last_interaction_at' => Carbon::now(),
                'idle_at' => Carbon::now()->addMinutes(30),
            ]);
        }

        return $conversation;
    }

    private function isIdleTimeout(WhatsappConversation $conversation): bool
    {
        if (! $conversation->idle_at) {
            return false;
        }

        return $conversation->idle_at->isPast();
    }

    private function reply(Tenant $tenant, string $to, string $message): void
    {
        $account = $tenant->whatsappAccount ?? null;
        if (! $account) {
            return;
        }

        $this->client->sendText($account, $to, $message);
    }
}
