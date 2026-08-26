<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PacienteRutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rut' => $this->route('rut'),
        ]);
    }

    public function rules(): array
    {
        return [
            'rut' => ['required', 'string', 'max:20'],
        ];
    }
}