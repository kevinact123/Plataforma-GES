<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Autoridad definitiva del cierre de sesión por inactividad.
 *
 * Se apoya en la columna nativa `last_used_at` de Sanctum (personal_access_tokens),
 * sin crear tablas, columnas ni migraciones nuevas.
 */
class EnsureSessionNotInactive
{
    public function handle(Request $request, Closure $next): mixed
    {
        $token = $request->user()?->currentAccessToken();

        $lastUsedAt = $token instanceof Model
            ? $token->getAttribute('last_used_at')
            : null;

        if ($lastUsedAt) {
            $timeoutSeconds = (int) config('session.inactivity_timeout', 840);

            if ($lastUsedAt->lt(now()->subSeconds($timeoutSeconds))) {
                $token->delete();

                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                return response()->json([
                    'message' => 'Tu sesión ha expirado por inactividad. Inicia sesión nuevamente.',
                    'expired' => true,
                ], 401);
            }
        }

        return $next($request);
    }
}
