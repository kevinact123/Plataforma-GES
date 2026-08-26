<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HitoCrearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'id_usuario' => ['nullable', 'integer', 'exists:usuarios,id_usuario'],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
