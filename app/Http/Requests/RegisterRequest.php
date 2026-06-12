<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        // Cadastro coleta o DONO (pessoa). O nome da barbearia é definido no
        // onboarding — por isso não há tenant_name/tenant_slug aqui (o slug é
        // gerado automaticamente e de forma única no RegisterTenantOwner).
        return [
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => ['required', 'string', new BrazilianPhone],
            'password' => 'required|string|min:8|max:72|confirmed',
        ];
    }
}
