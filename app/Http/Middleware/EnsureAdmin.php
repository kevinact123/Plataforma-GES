<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! $request->user()?->hasPermission('administrar_usuarios')) {
            return response()->json([
                'message' => 'No tienes permiso para administrar usuarios.',
            ], 403);
        }

        return $next($request);
    }
}
