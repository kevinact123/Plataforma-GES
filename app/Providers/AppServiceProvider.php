<?php

namespace App\Providers;

use App\Models\Patologia;
use App\Models\Paciente;
use App\Models\RegistroGes;
use App\Models\User;
use App\Policies\PacientePolicy;
use App\Policies\PatologiaPolicy;
use App\Policies\RegistroGesPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Patologia::class, PatologiaPolicy::class);
        Gate::policy(Paciente::class, PacientePolicy::class);
        Gate::policy(RegistroGes::class, RegistroGesPolicy::class);

        Gate::before(function (User $user, string $ability, mixed $arguments): ?bool {
            if ($user->esAdmin()) {
                return true;
            }

            return null;
        });
    }
}
