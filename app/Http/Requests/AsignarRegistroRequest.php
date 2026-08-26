<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AsignarRegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_registro' => ['required', 'integer', 'exists:registros_ges,id_registro'],
            'id_usuario' => ['required', 'integer', 'exists:usuarios,id_usuario'],
            'cantidad_trabajo' => ['nullable', 'integer', 'min:1', 'max:100'],
            'prioridad' => ['nullable', 'integer', 'min:1', 'max:5'],
            'dificultad' => ['nullable', 'integer', 'min:1', 'max:5'],
            'complejidad' => ['nullable', 'integer', 'min:1', 'max:5'],
            'disponibilidad' => ['nullable', 'integer', 'min:1', 'max:5'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ];
    }
}
