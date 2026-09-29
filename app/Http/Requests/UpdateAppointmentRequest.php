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

        // Barbeiro ativo, OU o que JÁ está neste agendamento: editar a observação de um
        // atendimento de alguém que foi desativado depois não pode travar. O que não pode
        // é colocar um desativado num agendamento (brecha do teto do plano, ver Store).
        $appointment = $this->route('appointment');
        $currentBarberIds = $appointment
            ? $appointment->services()->pluck('barber_id')->push($appointment->barber_id)->filter()->unique()->values()->all()
            : [];

        return [
            'service_ids' => 'nullable|array|min:1',
            'service_ids.*' => [Rule::exists('services', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'barber_ids' => 'nullable|array',
            'barber_ids.*' => ['nullable', Rule::exists('barbers', 'id')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $currentBarberIds))],
            'starts_at' => 'nullable|date_format:Y-m-d\TH:i:s|after:now',
            'notes' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.after' => 'O horário precisa ser no futuro.',
            'starts_at.date_format' => 'Horário inválido.',
            'barber_ids.*.exists' => 'Este barbeiro está desativado. Reative-o em Barbeiros para agendar com ele.',
        ];
    }
}
