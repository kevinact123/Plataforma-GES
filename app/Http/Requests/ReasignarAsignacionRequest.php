<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReasignarAsignacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_usuario' => ['required', 'integer', 'exists:usuarios,id_usuario'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
