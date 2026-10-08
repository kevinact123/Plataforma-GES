<?php

namespace App\Providers;

use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\RegistroGes;
use App\Policies\PacientePolicy;
use App\Policies\PatologiaPolicy;
use App\Policies\RegistroGesPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        // Máximo 5 generaciones de OTP (login) por usuario cada 5 minutos.
        RateLimiter::for('otp-generar', function (Request $request): Limit {
            $identificador = strtolower((string) $request->input('username')) ?: $request->ip();

            return Limit::perMinutes(5, 5)->by('otp-generar:'.$identificador);
        });

        // Máximo 5 reenvíos de OTP por sesión de verificación cada 5 minutos.
        RateLimiter::for('otp-reenviar', function (Request $request): Limit {
            $identificador = $request->session()->getId() ?: $request->ip();

            return Limit::perMinutes(5, 5)->by('otp-reenviar:'.$identificador);
        });

        // Máximo 10 intentos de verificación de OTP por sesión cada 5 minutos.
        RateLimiter::for('otp-verificar', function (Request $request): Limit {
            $identificador = $request->session()->getId() ?: $request->ip();

            return Limit::perMinutes(5, 10)->by('otp-verificar:'.$identificador);
        });
    }
}
