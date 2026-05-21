<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SelectPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plan' => 'required|string|in:solo,barbearia,rede',
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Selecione um plano.',
            'plan.in' => 'Plano inválido.',
        ];
    }
}
