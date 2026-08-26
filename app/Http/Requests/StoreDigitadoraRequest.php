<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDigitadoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdmin() === true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('usuarios', 'username')],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'permisos' => ['nullable', 'array'],
            'permisos.*.id_patologia' => ['required', 'integer', 'exists:patologias,id_patologia'],
            'permisos.*.puede_ver' => ['sometimes', 'boolean'],
            'permisos.*.puede_editar' => ['sometimes', 'boolean'],
            'permisos.*.puede_asignar' => ['sometimes', 'boolean'],
        ];
    }
}
