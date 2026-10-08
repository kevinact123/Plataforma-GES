<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePacienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'rut' => ['required', 'string', 'max:20', 'unique:pacientes,rut'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'hora_cierre_dau' => ['nullable', 'date'],
            'sexo' => ['nullable', 'string', 'max:10'],
        ];
    }
}
