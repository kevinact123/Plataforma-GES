<?php

namespace App\Services;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class GestionUsuariosService
{
    public function usuariosRegistrados(): array
    {
        return User::query()
            ->whereNull('eliminado_en')
            ->whereHas('rol')
            ->with(['rol', 'permisosPatologia.patologia'])
            ->orderBy('nombre')
            ->get()
            ->map(fn (User $user): array => $this->serializeDigitadora($user))
            ->all();
    }

    public function roles(): array
    {
        return Rol::query()->orderBy('id_rol')->get(['id_rol', 'nombre'])->map(fn (Rol $rol): array => [
            'id_rol' => $rol->id_rol,
            'nombre' => $rol->nombre,
            'es_digitadora' => $rol->esDigitadora(),
        ])->all();
    }

    public function patologias(): array
    {
        return Patologia::query()
            ->where('activo', true)
            ->orderBy('numero_ges')
            ->get(['id_patologia', 'numero_ges', 'nombre', 'confidencial'])
            ->map(fn (Patologia $patologia): array => [
                'id_patologia' => $patologia->id_patologia,
                'numero_ges' => $patologia->numero_ges,
                'nombre' => $patologia->nombre,
                'confidencial' => (bool) $patologia->confidencial,
            ])
            ->all();
    }

    public function crearDigitadora(array $data): array
    {
        try {
            return DB::transaction(function () use ($data): array {
                $rol = isset($data['id_rol'])
                    ? Rol::query()->findOrFail($data['id_rol'])
                    : Rol::query()->whereRaw('LOWER(nombre) = ?', ['digitadora'])->firstOrFail();
                $esDigitadora = $rol->esDigitadora();

                $user = User::query()->create([
                    'nombre' => trim($data['nombre']),
                    'apellido' => trim($data['apellido']),
                    'username' => $data['username'],
                    'correo' => $data['correo'],
                    'password' => $data['password'],
                    'id_rol' => $rol->id_rol,
                    'tipo_digitadora' => $esDigitadora ? ($data['tipo_digitadora'] ?? User::TIPO_DIGITADORA_NO_CONFIDENCIAL) : null,
                    'activo' => true,
                ]);

                if ($esDigitadora) {
                    $this->guardarPermisos($user, $data['permisos'] ?? []);
                    $this->depurarPermisosConfidenciales($user);
                }

                return $this->serializeDigitadora($user->fresh(['permisosPatologia.patologia']));
            });
        } catch (QueryException $exception) {
            throw $this->convertirDuplicadoEnValidacion($exception);
        }
    }

    /**
     * Convierte una violación de clave única (p. ej. por dos envíos simultáneos del
     * formulario) en un error de validación legible en lugar de propagar el 500 crudo.
     */
    private function convertirDuplicadoEnValidacion(QueryException $exception): QueryException|ValidationException
    {
        if ($exception->getCode() !== '23000') {
            return $exception;
        }

        $mensaje = $exception->getMessage();

        return match (true) {
            str_contains($mensaje, 'usuarios_username') || str_contains($mensaje, 'usuarios.username') => ValidationException::withMessages([
                'username' => 'Este nombre de usuario ya está en uso.',
            ]),
            str_contains($mensaje, 'usuarios_correo') || str_contains($mensaje, 'usuarios.correo') => ValidationException::withMessages([
                'correo' => 'Este correo ya está en uso.',
            ]),
            default => $exception,
        };
    }

    public function actualizarDigitadora(User $user, array $data): array
    {
        if (! $user->hasRole('digitadora')) {
            abort(422, 'Solo se pueden editar los datos y accesos de un/a digitador/a.');
        }

        return DB::transaction(function () use ($user, $data): array {
            foreach (['nombre', 'apellido'] as $campo) {
                if (array_key_exists($campo, $data)) {
                    $user->update([$campo => trim($data[$campo])]);
                }
            }

            if (array_key_exists('username', $data)) {
                $user->update(['username' => $data['username']]);
            }

            if (array_key_exists('correo', $data)) {
                $user->update(['correo' => $data['correo']]);
            }

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
                $user->tokens()->delete();
            }

            if (array_key_exists('tipo_digitadora', $data)) {
                $user->update(['tipo_digitadora' => $data['tipo_digitadora']]);
            }

            if (array_key_exists('permisos', $data)) {
                $this->guardarPermisos($user, $data['permisos'] ?? []);
            }

            $this->depurarPermisosConfidenciales($user->fresh());

            return $this->serializeDigitadora($user->fresh(['permisosPatologia.patologia']));
        });
    }

    public function cambiarEstadoDigitadora(User $user, bool $activo): array
    {
        return DB::transaction(function () use ($user, $activo): array {
            if (! $user->hasRole('digitadora')) {
                abort(422, 'El usuario seleccionado no es un/a digitador/a.');
            }

            $datos = ['activo' => $activo];

            if ($activo) {
                $datos['intentos_fallidos'] = 0;
                $datos['bloqueado_en'] = null;
                // Limpia también el límite temporal de peticiones del login.
                RateLimiter::clear(md5('otp-generar'.'otp-generar:'.strtolower($user->username)));
            }

            $user->update($datos);

            if (! $activo) {
                $user->tokens()->delete();
            }

            return $this->serializeDigitadora($user->fresh(['permisosPatologia.patologia']));
        });
    }

    public function eliminarDigitadora(User $user): void
    {
        if (! $user->hasRole('digitadora')) {
            abort(422, 'El usuario seleccionado no es un/a digitador/a.');
        }

        // Eliminación lógica: se conserva la fila para no alterar registros, asignaciones ni historial ya realizados.
        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->permisosPatologia()->delete();
            $user->update(['activo' => false, 'eliminado_en' => now()]);
        });
    }
    /**
     * Una digitadora NO_CONFIDENCIAL no puede conservar permisos sobre patologías confidenciales.
     */
    private function depurarPermisosConfidenciales(User $user): void
    {
        if ($user->puedeAccederAConfidenciales()) {
            return;
        }

        $user->permisosPatologia()
            ->whereIn('id_patologia', Patologia::query()->where('confidencial', true)->select('id_patologia'))
            ->delete();
    }

    private function guardarPermisos(User $user, array $permisos): void
    {
        $permisosValidos = collect($permisos)
            ->map(function ($permiso): array {
                return [
                    'id_patologia' => (int) ($permiso['id_patologia'] ?? 0),
                    'puede_ver' => (bool) ($permiso['puede_ver'] ?? false),
                    'puede_editar' => (bool) ($permiso['puede_editar'] ?? false),
                    'puede_asignar' => (bool) ($permiso['puede_asignar'] ?? false),
                ];
            })
            ->filter(fn (array $permiso): bool => $permiso['id_patologia'] > 0 && (
                $permiso['puede_ver'] || $permiso['puede_editar'] || $permiso['puede_asignar']
            ))
            ->values()
            ->all();

        $patologiasEnviadas = collect($permisosValidos)
            ->pluck('id_patologia')
            ->all();

        $query = $user->permisosPatologia();
        if ($patologiasEnviadas !== []) {
            $query->whereNotIn('id_patologia', $patologiasEnviadas);
        }
        $query->delete();

        foreach ($permisosValidos as $permiso) {
            PermisoPatologia::query()->updateOrCreate(
                [
                    'id_usuario' => $user->id_usuario,
                    'id_patologia' => $permiso['id_patologia'],
                ],
                [
                    'puede_ver' => $permiso['puede_ver'],
                    'puede_editar' => $permiso['puede_editar'],
                    'puede_asignar' => $permiso['puede_asignar'],
                ],
            );
        }
    }

    private function serializeDigitadora(User $user): array
    {
        return [
            'id_usuario' => $user->id_usuario,
            'nombre' => $user->hasRole('administrador')
                ? 'Administrador del sistema'
                : trim($user->nombre.' '.$user->apellido),
            'nombre_pila' => $user->nombre,
            'apellido' => $user->apellido,
            'username' => $user->username,
            'correo' => $user->correo,
            'activo' => (bool) $user->activo,
            'bloqueado' => $user->estaBloqueado(),
            'rol' => $user->rol?->nombre,
            'tipo_digitadora' => $user->hasRole('digitadora') ? ($user->tipo_digitadora ?? User::TIPO_DIGITADORA_NO_CONFIDENCIAL) : null,
            'permisos' => $user->permisosPatologia
                ->filter(fn (PermisoPatologia $permiso): bool => (bool) $permiso->puede_ver || (bool) $permiso->puede_editar || (bool) $permiso->puede_asignar)
                ->map(fn (PermisoPatologia $permiso): array => [
                    'id_patologia' => $permiso->id_patologia,
                    'patologia' => $permiso->patologia?->nombre,
                    'puede_ver' => (bool) $permiso->puede_ver,
                    'puede_editar' => (bool) $permiso->puede_editar,
                    'puede_asignar' => (bool) $permiso->puede_asignar,
                ])
                ->values()
                ->all(),
        ];
    }
}
