<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:80|unique:services,name,NULL,id,tenant_id,'.auth()->user()->tenant_id.',deleted_at,NULL',
            'description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0|max:999999',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
