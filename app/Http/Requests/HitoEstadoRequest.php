<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HitoEstadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(['pendiente', 'en_proceso', 'completado'])],
            'observacion' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
