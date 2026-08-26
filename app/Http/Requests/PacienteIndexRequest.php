<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PacienteIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'rut' => ['nullable', 'string', 'max:20'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}