<?php

namespace App\Policies;

use App\Models\Paciente;
use App\Models\RegistroGes;
use App\Models\User;

class PacientePolicy
{
    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Paciente $paciente): bool
    {
        return $user->activo
            && $paciente->activo
            && (
                !$paciente->registrosGes()->exists()
                || $paciente->registrosGes()->visibleTo($user)->exists()
            );
    }
}
