<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDigitadoraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('administrar_usuarios') === true;
    }

    public function rules(): array
    {
        return [
            'id_rol' => ['sometimes', 'required', 'integer', Rule::exists('roles', 'id_rol')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('usuarios', 'username')],
            'correo' => ['required', 'string', 'email', 'max:255', Rule::unique('usuarios', 'correo')],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
            'tipo_digitadora' => ['sometimes', 'nullable', Rule::in([
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

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('id_rol')) {
                return;
            }

            $esDigitadora = strtolower((string) $this->rolSolicitado()?->nombre) === 'digitadora';
            $tipo = $this->input('tipo_digitadora');

            if ($esDigitadora && $tipo === null && $this->has('tipo_digitadora')) {
                $validator->errors()->add('tipo_digitadora', 'Debes indicar si la digitadora es NO_CONFIDENCIAL o CONFIDENCIAL.');
            }

            if (! $esDigitadora && $tipo !== null) {
                $validator->errors()->add('tipo_digitadora', 'Solo el rol Digitadora puede tener tipo_digitadora; para Administrador y Supervisor debe ser NULL.');
            }

            if (! $esDigitadora && ! empty($this->input('permisos'))) {
                $validator->errors()->add('permisos', 'Los permisos por patología solo aplican al rol Digitadora.');
            }
        }];
    }

    /** Sin id_rol se mantiene el comportamiento histórico: se crea una Digitadora. */
    public function rolSolicitado(): ?Rol
    {
        return $this->filled('id_rol')
            ? Rol::query()->find($this->input('id_rol'))
            : Rol::query()->whereRaw('LOWER(nombre) = ?', ['digitadora'])->first();
    }
}
