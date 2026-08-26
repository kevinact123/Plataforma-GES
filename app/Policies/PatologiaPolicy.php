<?php

namespace App\Policies;

use App\Models\Patologia;
use App\Models\User;

class PatologiaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->esAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Patologia $patologia): bool
    {
        return $user->puedeVerPatologia($patologia);
    }

    public function update(User $user, Patologia $patologia): bool
    {
        return $user->puedeEditarPatologia($patologia);
    }

    public function assign(User $user, Patologia $patologia): bool
    {
        return $user->puedeAsignarPatologia($patologia);
    }
}