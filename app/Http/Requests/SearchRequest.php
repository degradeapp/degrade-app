<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'q' => 'required|string|min:2|max:100',
            'page' => 'nullable|integer|min:1|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'q.required' => 'Termo de busca obrigatório',
            'q.min' => 'Termo deve ter pelo menos 2 caracteres',
            'page.integer' => 'Página deve ser um número',
        ];
    }
}
