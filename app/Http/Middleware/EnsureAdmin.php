<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->user()?->esAdmin()) {
            return response()->json([
                'message' => 'Solo los administradores pueden realizar esta acción.',
            ], 403);
        }

        return $next($request);
    }
}
