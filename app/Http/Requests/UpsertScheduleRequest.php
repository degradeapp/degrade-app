<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->isOwner() || auth()->user()->isManager();
    }

    public function rules(): array
    {
        return [
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.date_format' => 'Horário de início inválido.',
            'end_time.date_format' => 'Horário de fim inválido.',
            'end_time.after' => 'O horário de fim deve ser depois do início.',
        ];
    }
}
