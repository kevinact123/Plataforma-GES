<?php

namespace App\Http\Middleware;

use App\Models\Patologia;
use Closure;
use Illuminate\Http\Request;

class CheckPatologiaConfidencial
{
    public function handle(Request $request, Closure $next, string $parameter = 'patologia'): mixed
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Autenticación requerida.',
            ], 401);
        }

        $patologia = $request->route($parameter);

        if (!$patologia instanceof Patologia) {
            $patologiaId = $patologia ?? $request->input('id_patologia');
            $patologia = $patologiaId ? Patologia::find($patologiaId) : null;
        }

        if (!$patologia || !$request->user()?->can('view', $patologia)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar esta patología.',
            ], 403);
        }

        return $next($request);
    }
}
