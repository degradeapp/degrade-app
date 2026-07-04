<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do POST público de agendamento. Rota sem auth: authorize() é true
 * de propósito — quem barra abuso é o rate limit por IP + a validação rígida
 * + as checagens escopadas por tenant no controller.
 */
class PublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => preg_replace('/\D/', '', (string) $this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:150',
            'phone' => ['required', 'string', new BrazilianPhone],
            'service_ids' => 'required|array|min:1|max:5',
            'service_ids.*' => 'required|integer',
            'barber_id' => 'nullable|integer',
            // O "passado" e o horizonte máximo são re-checados no controller
            // com o fuso DO TENANT já aplicado (o request roda antes do contexto).
            'starts_at' => 'required|date_format:Y-m-d\TH:i:s',
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.date_format' => 'Horário inválido.',
            'service_ids.required' => 'Escolha pelo menos um serviço.',
            'service_ids.max' => 'Escolha no máximo 5 serviços por agendamento.',
        ];
    }
}
