<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatologiaIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'activo' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}