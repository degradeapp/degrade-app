<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        $service = $this->route('service');

        return [
            'name' => 'required|string|max:255|unique:services,name,'.$service->id.',id,tenant_id,'.auth()->user()->tenant_id.',deleted_at,NULL',
            'description' => 'nullable|string',
            'duration_minutes' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'nullable|boolean',
        ];
    }
}
