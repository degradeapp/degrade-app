<?php

namespace App\Http\Requests;

use App\Rules\BrazilianPhone;
use Illuminate\Foundation\Http\FormRequest;

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
        return [
            'name' => 'required|string|max:100',
            'phone' => ['required', 'string', new BrazilianPhone],
            'user_id' => 'nullable|exists:users,id',
            'default_commission_percentage' => 'nullable|numeric|min:0|max:100',
            'unit_id' => 'nullable|integer',
        ];
    }
}
