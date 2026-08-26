<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistroGesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->activo === true;
    }

    public function rules(): array
    {
        return [
            'id_paciente' => ['required', 'integer', 'exists:pacientes,id_paciente'],
            'id_patologia' => ['required', 'integer', 'exists:patologias,id_patologia'],
            'id_prioridad' => ['required', 'integer', 'exists:prioridades,id_prioridad'],
            'id_tipo_registro' => ['required', 'integer', 'exists:tipos_registro,id_tipo_registro'],
            'tipo_tratamiento' => ['nullable', 'string', 'max:255'],
            'fecha_ingreso' => ['nullable', 'date'],
            'fecha_limite' => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
            'estado' => ['nullable', 'string', 'max:50'],
            'observaciones' => ['nullable', 'string'],
        ];
    }
}
