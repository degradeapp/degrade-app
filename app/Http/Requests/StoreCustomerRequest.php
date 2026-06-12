<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $u = auth()->user();

        return $u && ($u->isOwner() || $u->isManager() || $u->isReceptionist());
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $digits = preg_replace('/\D/', '', (string) $this->input('phone'));
            $this->merge(['phone' => $digits !== '' ? $digits : null]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            // Telefone opcional; se informado, precisa ser celular válido e único no tenant.
            'phone' => ['nullable', 'string', new BrazilianPhone, 'unique:customers,phone,NULL,id,tenant_id,'.auth()->user()->tenant_id.',deleted_at,NULL'],
            'email' => 'nullable|email|max:150',
            'notes' => 'nullable|string|max:200',
        ];
    }
}
