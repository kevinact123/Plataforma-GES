<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        if (! $request->user()?->hasPermission($permission)) {
            return response()->json([
                'message' => 'No tienes el permiso necesario para realizar esta acción.',
                'permiso_requerido' => $permission,
            ], 403);
        }

        return $next($request);
    }
}
