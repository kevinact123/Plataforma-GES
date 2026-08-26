<?php

namespace App\Http\Requests;

use App\Models\AuditoriaAcceso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'tipo_documento' => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        try {
            AuditoriaAcceso::create([
                'id_usuario' => null,
                'nombre_usuario' => (string) $this->input('username', 'desconocido'),
                'tipo_documento' => (string) $this->input('tipo_documento', 'USERNAME'),
                'fecha_hora_entrada' => now(),
                'ip' => $this->ip(),
                'estado' => 'RECHAZADO',
                'motivo' => 'Datos de login inválidos.',
            ]);
        } catch (\Throwable $exception) {
            Log::error('No fue posible registrar un login inválido.', [
                'username' => $this->input('username'),
                'error' => $exception->getMessage(),
            ]);
        }

        throw new HttpResponseException(response()->json([
            'message' => 'Los datos de acceso no son válidos.',
            'errors' => $validator->errors(),
        ], 422));
    }
}