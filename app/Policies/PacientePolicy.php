<?php

namespace App\Policies;

use App\Models\Paciente;
use App\Models\User;

class PacientePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('crear_registros');
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('ver_registros');
    }

    public function view(User $user, Paciente $paciente): bool
    {
        return $user->hasPermission('ver_registros')
            && $paciente->activo
            && (
                ! $paciente->registrosGes()->exists()
                || $paciente->registrosGes()->visibleTo($user)->exists()
            );
    }

    public function delete(User $user, Paciente $paciente): bool
    {
        return $user->activo
            && $paciente->activo
            && $user->esAdmin();
    }
}
