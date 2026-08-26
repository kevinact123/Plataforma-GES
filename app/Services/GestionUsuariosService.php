<?php

namespace App\Services;

use App\Models\Patologia;
use App\Models\PermisoPatologia;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GestionUsuariosService
{
    public function digitadoras(): array
    {
        return User::query()
            ->whereHas('rol', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['digitadora']))
            ->with(['permisosPatologia.patologia'])
            ->orderBy('nombre')
            ->get()
            ->map(fn (User $user): array => $this->serializeDigitadora($user))
            ->all();
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
        return DB::transaction(function () use ($data): array {
            $rol = Rol::query()->whereRaw('LOWER(nombre) = ?', ['digitadora'])->firstOrFail();

            $user = User::query()->create([
                'nombre' => trim($data['nombre']),
                'apellido' => trim($data['apellido']),
                'username' => $data['username'],
                'password' => $data['password'],
                'id_rol' => $rol->id_rol,
                'activo' => true,
            ]);

            $this->guardarPermisos($user, $data['permisos'] ?? []);

            return $this->serializeDigitadora($user->fresh(['permisosPatologia.patologia']));
        });
    }

    private function guardarPermisos(User $user, array $permisos): void
    {
        foreach ($permisos as $permiso) {
            PermisoPatologia::query()->updateOrCreate(
                [
                    'id_usuario' => $user->id_usuario,
                    'id_patologia' => $permiso['id_patologia'],
                ],
                [
                    'puede_ver' => (bool) ($permiso['puede_ver'] ?? false),
                    'puede_editar' => (bool) ($permiso['puede_editar'] ?? false),
                    'puede_asignar' => (bool) ($permiso['puede_asignar'] ?? false),
                ],
            );
        }
    }

    private function serializeDigitadora(User $user): array
    {
        return [
            'id_usuario' => $user->id_usuario,
            'nombre' => trim($user->nombre . ' ' . $user->apellido),
            'username' => $user->username,
            'activo' => (bool) $user->activo,
            'permisos' => $user->permisosPatologia->map(fn (PermisoPatologia $permiso): array => [
                'id_patologia' => $permiso->id_patologia,
                'patologia' => $permiso->patologia?->nombre,
                'puede_ver' => (bool) $permiso->puede_ver,
                'puede_editar' => (bool) $permiso->puede_editar,
                'puede_asignar' => (bool) $permiso->puede_asignar,
            ])->values()->all(),
        ];
    }
}
