<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isReceptionist();
    }

    public function rules(): array
    {
        return [
            'service_ids' => 'nullable|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'barber_ids' => 'nullable|array',
            'barber_ids.*' => 'nullable|exists:barbers,id',
            'starts_at' => 'nullable|date_format:Y-m-d\TH:i:s|after:now',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
