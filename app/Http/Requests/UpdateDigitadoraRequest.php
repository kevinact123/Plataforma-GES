<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDigitadoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('administrar_usuarios') === true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'apellido' => ['sometimes', 'required', 'string', 'max:100'],
            'username' => ['sometimes', 'required', 'string', 'max:100', 'alpha_dash', Rule::unique('usuarios', 'username')->ignore($this->route('usuario'), 'id_usuario')],
            'password' => ['sometimes', 'nullable', 'string', 'min:8', 'max:72', 'confirmed'],
            'correo' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($this->route('usuario'), 'id_usuario')],
            'tipo_digitadora' => ['sometimes', 'required', Rule::in([
                User::TIPO_DIGITADORA_NO_CONFIDENCIAL,
                User::TIPO_DIGITADORA_CONFIDENCIAL,
            ])],
            'permisos' => ['nullable', 'array'],
            'permisos.*.id_patologia' => ['required', 'integer', 'exists:patologias,id_patologia'],
            'permisos.*.puede_ver' => ['sometimes', 'boolean'],
            'permisos.*.puede_editar' => ['sometimes', 'boolean'],
            'permisos.*.puede_asignar' => ['sometimes', 'boolean'],
        ];
    }
}
