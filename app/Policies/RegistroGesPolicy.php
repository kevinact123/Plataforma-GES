<?php

namespace App\Policies;

use App\Models\RegistroGes;
use App\Models\User;

class RegistroGesPolicy
{
    public function view(User $user, RegistroGes $registro): bool
    {
        return $user->hasPermission('ver_registros')
            && $registro->patologia()->where('activo', true)->exists()
            && $user->puedeVerPatologia($registro->patologia);
    }

    public function update(User $user, RegistroGes $registro): bool
    {
        return $user->hasPermission('editar_registros')
            && $registro->patologia()->where('activo', true)->exists()
            && $user->puedeEditarPatologia($registro->patologia);
    }

    public function delete(User $user, RegistroGes $registro): bool
    {
        $patologia = $registro->patologia;

        if (! $user->hasPermission('eliminar_registros') || ! $patologia?->activo) {
            return false;
        }

        return $user->hasRole('digitadora')
            ? $user->puedeEditarPatologia($patologia)
            : $user->puedeAccederAPatologia($patologia);
    }
}
