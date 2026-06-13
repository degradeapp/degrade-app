<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isReceptionist();
    }

    public function rules(): array
    {
        // Mesma regra do Store: 'exists' SEMPRE escopado pelo tenant (IDOR de escrita).
        $tenantId = auth()->user()->tenant_id;

        return [
            'service_ids' => 'nullable|array|min:1',
            'service_ids.*' => [Rule::exists('services', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'barber_ids' => 'nullable|array',
            'barber_ids.*' => ['nullable', Rule::exists('barbers', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'starts_at' => 'nullable|date_format:Y-m-d\TH:i:s|after:now',
            'notes' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after' => 'O horário precisa ser no futuro.',
            'starts_at.date_format' => 'Horário inválido.',
        ];
    }
}
