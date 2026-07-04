<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBarberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => preg_replace('/\D/', '', (string) $this->input('phone'))]);
        }
    }

    public function rules(): array
    {
        // SEGURANÇA: user_id escopado pelo tenant (mesmo padrão dos requests de
        // agendamento). 'exists:users,id' puro vincularia o barbeiro a um login
        // de OUTRO tenant (a regra consulta a tabela sem o TenantScope).
        $tenantId = auth()->user()->tenant_id;

        return [
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', new BrazilianPhone],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'default_commission_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
