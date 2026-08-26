<?php

namespace App\Http\Middleware;

use App\Models\Patologia;
use App\Services\RegistroGesAuditService;
use Closure;
use Illuminate\Http\Request;

class AuthorizePatologiaAccess
{
    public function handle(Request $request, Closure $next, string $ability = 'view'): mixed
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'Autenticación requerida.',
            ], 401);
        }

        $patologia = $request->route('patologia');

        if (!$patologia instanceof Patologia) {
            $patologiaId = $request->route('patologia') ?? $request->input('id_patologia');
            $patologia = $patologiaId ? Patologia::find($patologiaId) : null;
        }

        if (!$patologia) {
            return response()->json([
                'message' => 'Patología no encontrada.',
            ], 404);
        }

        if (!$request->user()->can($ability, $patologia)) {
            app(RegistroGesAuditService::class)->registrar(
                $request->route('registro') ?? null,
                $request->user()->id_usuario,
                'acceso_denegado',
                'patologia',
                $ability,
                'denegado',
            );

            return response()->json([
                'message' => 'No tienes permisos para ' . $this->accion($ability) . ' esta patología.',
            ], 403);
        }

        return $next($request);
    }

    private function accion(string $ability): string
    {
        return match ($ability) {
            'view' => 'consultar',
            'update' => 'editar',
            'assign' => 'asignar',
            default => 'gestionar',
        };
    }
}
