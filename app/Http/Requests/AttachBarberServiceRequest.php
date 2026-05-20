<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttachBarberServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'commission_percentage' => 'nullable|numeric|min:0|max:100',
        ];
    }
}
