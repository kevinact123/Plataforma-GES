<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OtpVerificacionNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class OtpService
{
    private const TTL_SEGUNDOS = 45;

    private const MAX_INTENTOS = 3;

    public const RESULTADO_OK = 'ok';

    public const RESULTADO_INVALIDO = 'invalido';

    public const RESULTADO_EXPIRADO = 'expirado';

    public const RESULTADO_DEMASIADOS_INTENTOS = 'demasiados_intentos';

    public function generar(User $user): void
    {
        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Se guarda el hash del código; el valor en texto plano solo se envía por correo.
        Cache::put($this->clave($user), [
            'codigo_hash' => Hash::make($codigo),
            'expira_en' => now()->addSeconds(self::TTL_SEGUNDOS)->timestamp,
            'intentos' => 0,
        ], self::TTL_SEGUNDOS);

        $user->notify(new OtpVerificacionNotification($codigo));
    }

    public function reenviar(User $user): void
    {
        $this->invalidar($user);
        $this->generar($user);
    }

    public function verificar(User $user, string $codigo): string
    {
        $clave = $this->clave($user);
        $datos = Cache::get($clave);

        if (! $datos || $datos['expira_en'] < now()->timestamp) {
            $this->invalidar($user);

            return self::RESULTADO_EXPIRADO;
        }

        if ($datos['intentos'] >= self::MAX_INTENTOS) {
            $this->invalidar($user);

            return self::RESULTADO_DEMASIADOS_INTENTOS;
        }

        if (! Hash::check($codigo, $datos['codigo_hash'])) {
            $datos['intentos']++;

            if ($datos['intentos'] >= self::MAX_INTENTOS) {
                $this->invalidar($user);

                return self::RESULTADO_DEMASIADOS_INTENTOS;
            }

            $segundosRestantes = max($datos['expira_en'] - now()->timestamp, 1);
            Cache::put($clave, $datos, $segundosRestantes);

            return self::RESULTADO_INVALIDO;
        }

        $this->invalidar($user);

        return self::RESULTADO_OK;
    }

    public function invalidar(User $user): void
    {
        Cache::forget($this->clave($user));
    }

    private function clave(User $user): string
    {
        return 'otp:usuario:'.$user->getKey();
    }
}
