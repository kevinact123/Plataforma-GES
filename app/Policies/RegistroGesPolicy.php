<?php

namespace App\Policies;

use App\Models\RegistroGes;
use App\Models\User;

class RegistroGesPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->esAdmin()) {
            return true;
        }

        return null;
    }

    public function view(User $user, RegistroGes $registro): bool
    {
        return $registro->patologia()->where('activo', true)->exists()
            && $user->puedeVerPatologia($registro->patologia);
    }

    public function update(User $user, RegistroGes $registro): bool
    {
        return $registro->patologia()->where('activo', true)->exists()
            && $user->puedeEditarPatologia($registro->patologia);
    }

    public function delete(User $user, RegistroGes $registro): bool
    {
        return $this->update($user, $registro);
    }
}
