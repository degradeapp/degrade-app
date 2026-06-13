<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isReceptionist();
    }

    public function rules(): array
    {
        // SEGURANÇA: 'exists' sempre escopado pelo tenant do usuário logado.
        // 'exists:customers,id' puro aceitaria um id de OUTRO tenant (a regra
        // consulta a tabela sem o TenantScope), permitindo vincular um
        // agendamento a cliente/barbeiro alheio (IDOR de escrita).
        $tenantId = auth()->user()->tenant_id;

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => [Rule::exists('services', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'barber_ids' => 'nullable|array',
            'barber_ids.*' => ['nullable', Rule::exists('barbers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            // Override de preço por serviço só para este atendimento (id => valor).
            'prices' => 'nullable|array',
            'prices.*' => 'nullable|numeric|min:0|max:999999',
            // "Atender agora" (walk-in) marca o horário atual, que ao confirmar fica
            // alguns segundos/minutos "no passado". Damos uma folga de 15 min para não
            // bloquear o walk-in, mas ainda barrando horário realmente passado.
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:s', 'after_or_equal:'.now()->subMinutes(15)->format('Y-m-d\TH:i:s')],
            'source' => 'required|in:customer,walk_in,phone,whatsapp',
            'notes' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after_or_equal' => 'O horário não pode ser no passado.',
            'starts_at.date_format' => 'Horário inválido.',
        ];
    }
}
