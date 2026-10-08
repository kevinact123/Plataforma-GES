<?php

namespace App\Policies;

use App\Models\Patologia;
use App\Models\User;

class PatologiaPolicy
{
    public function view(User $user, Patologia $patologia): bool
    {
        return $user->hasPermission('ver_registros')
            && $user->puedeVerPatologia($patologia);
    }

    public function update(User $user, Patologia $patologia): bool
    {
        return $user->hasPermission('administrar_patologias')
            && $user->puedeEditarPatologia($patologia);
    }

    public function assign(User $user, Patologia $patologia): bool
    {
        return $user->hasPermission('asignar_pacientes')
            && $user->puedeAsignarPatologia($patologia);
    }
}
