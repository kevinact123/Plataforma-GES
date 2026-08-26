<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroGesIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_prioridad' => ['nullable', 'integer', 'exists:prioridades,id_prioridad'],
            'id_patologia' => ['nullable', 'integer', 'exists:patologias,id_patologia'],
            'id_tipo_registro' => ['nullable', 'integer', 'exists:tipos_registro,id_tipo_registro'],
            'estado' => ['nullable', 'string', 'max:50'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}