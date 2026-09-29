<?php

namespace App\Http\Requests;

use App\Enums\BillingPlan;
use App\Rules\CpfCnpj;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('document')) {
            $this->merge(['document' => CpfCnpj::normalize($this->input('document'))]);
        }
    }

    public function rules(): array
    {
        // O Asaas recusa cliente sem CPF/CNPJ válido: obrigatório até a barbearia ter um salvo.
        $hasDocument = (bool) $this->user()?->tenant?->billing_document;

        return [
            'plan' => ['required', 'string', Rule::enum(BillingPlan::class)],
            'document' => [$hasDocument ? 'nullable' : 'required', 'string', new CpfCnpj],
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required' => 'Selecione um plano.',
            'plan.enum' => 'Plano inválido.',
            'document.required' => 'Informe o CPF ou CNPJ do titular da assinatura.',
        ];
    }
}
