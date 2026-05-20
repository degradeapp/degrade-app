<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager() || auth()->user()->isReceptionist();
    }

    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'service_ids' => 'required|array|min:1',
            'service_ids.*' => 'exists:services,id',
            'barber_ids' => 'nullable|array',
            'barber_ids.*' => 'nullable|exists:barbers,id',
            'starts_at' => 'required|date_format:Y-m-d\TH:i:s|after:now',
            'source' => 'required|in:customer,walk_in,phone,whatsapp',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
