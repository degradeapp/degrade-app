<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|unique:customers,phone,NULL,id,tenant_id,'.auth()->user()->tenant_id.',deleted_at,NULL',
            'email' => 'nullable|email|max:255',
        ];
    }
}
