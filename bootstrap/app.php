<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'patologia.confidencial' => App\Http\Middleware\CheckPatologiaConfidencial::class,
            'patologia.autorizacion' => App\Http\Middleware\AuthorizePatologiaAccess::class,
            'admin' => App\Http\Middleware\EnsureAdmin::class,
            'permission' => App\Http\Middleware\EnsurePermission::class,
            'confidential' => App\Http\Middleware\EnsureConfidentialAccess::class,
            'inactivity' => App\Http\Middleware\EnsureSessionNotInactive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Illuminate\Database\QueryException $e, Illuminate\Http\Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $driverCode = (int) ($e->errorInfo[1] ?? 0);
            $message = match (true) {
                $driverCode === 1364 && preg_match("/Field '([^']+)'/", $e->getMessage(), $m) === 1
                    => 'El campo '.str_replace('_', ' ', $m[1]).' es obligatorio.',
                $driverCode === 1062 => 'Ya existe un registro con esos datos.',
                $driverCode === 1451 => 'No se puede eliminar porque tiene información asociada.',
                $driverCode === 1452 => 'Uno de los datos seleccionados no existe o no es válido.',
                default => 'No se pudo guardar la información. Revisa los datos e intenta nuevamente.',
            };

            return response()->json(['message' => $message], 422);
        });
    })->create();
