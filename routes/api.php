<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplejidadController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PatologiaController;
use App\Http\Controllers\RegistroGesController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('admin')->group(function (): void {
        Route::get('/admin/digitadoras', [\App\Http\Controllers\GestionUsuariosController::class, 'index']);
        Route::post('/admin/digitadoras', [\App\Http\Controllers\GestionUsuariosController::class, 'store']);
        Route::post('/asignaciones', [\App\Http\Controllers\AsignacionController::class, 'asignar']);
        Route::post('/asignaciones/sugerir', [\App\Http\Controllers\AsignacionController::class, 'sugerir']);
        Route::post('/asignaciones/{idAsignacion}/reasignar', [\App\Http\Controllers\AsignacionController::class, 'reasignar']);
        Route::post('/asignaciones/{idAsignacion}/finalizar', [\App\Http\Controllers\AsignacionController::class, 'finalizar']);
        Route::get('/usuarios/{usuario}/carga', [\App\Http\Controllers\AsignacionController::class, 'cargaPorUsuario']);
        Route::get('/registros-ges/{registro}/historial-asignaciones', [\App\Http\Controllers\AsignacionController::class, 'historialRegistro']);
    });

    Route::get('/dashboard/resumen', [\App\Http\Controllers\DashboardController::class, 'resumen']);
    Route::get('/dashboard/distribuciones', [\App\Http\Controllers\DashboardController::class, 'distribuciones']);
    Route::get('/dashboard/carga-operadores', [\App\Http\Controllers\DashboardController::class, 'cargaOperadores']);
    Route::get('/dashboard/registros-por-operador', [\App\Http\Controllers\DashboardController::class, 'registrosPorOperador']);
    Route::get('/dashboard/hitos', [\App\Http\Controllers\DashboardController::class, 'hitos']);
    Route::get('/dashboard/complejidad-promedio', [\App\Http\Controllers\DashboardController::class, 'complejidadPromedio']);

    Route::get('/complejidad', [ComplejidadController::class, 'index']);
    Route::get('/complejidad/promedio-por-tipo', [ComplejidadController::class, 'promedioPorTipo']);
    Route::get('/complejidad/operadores', [ComplejidadController::class, 'porOperador']);
    Route::get('/complejidad/patologias', [ComplejidadController::class, 'porPatologia']);

    Route::get('/registros-ges/pendientes', [RegistroGesController::class, 'pendientes']);
    Route::get('/registros-ges/asignados', [RegistroGesController::class, 'asignados']);
    Route::get('/registros-ges/sin-asignar', [RegistroGesController::class, 'sinAsignar']);
    Route::get('/registros-ges/catalogos', [RegistroGesController::class, 'catalogos']);
    Route::post('/registros-ges', [RegistroGesController::class, 'store']);
    Route::delete('/registros-ges/{registro}', [RegistroGesController::class, 'destroy']);
    Route::get('/registros-ges/{registro}/documentos', [\App\Http\Controllers\RegistroGesDocumentoController::class, 'index']);
    Route::post('/registros-ges/{registro}/documentos', [\App\Http\Controllers\RegistroGesDocumentoController::class, 'store']);
    Route::get('/registros-ges/{registro}/documentos/{documento}', [\App\Http\Controllers\RegistroGesDocumentoController::class, 'show']);
    Route::get('/registros-ges/{registro}/documentos/{documento}/download', [\App\Http\Controllers\RegistroGesDocumentoController::class, 'download']);
    Route::delete('/registros-ges/{registro}/documentos/{documento}', [\App\Http\Controllers\RegistroGesDocumentoController::class, 'destroy']);
    Route::get('/registros-ges/{registro}/anteriores', [RegistroGesController::class, 'anteriores']);
    Route::post('/registros-ges/{registro}/hitos', [\App\Http\Controllers\HitoController::class, 'crear']);
    Route::get('/registros-ges/{registro}/hitos', [\App\Http\Controllers\HitoController::class, 'index']);
    Route::get('/registros-ges/{registro}/hitos/pendientes', [\App\Http\Controllers\HitoController::class, 'pendientes']);
    Route::post('/hitos/{idHito}/iniciar', [\App\Http\Controllers\HitoController::class, 'iniciar']);
    Route::post('/hitos/{idHito}/completar', [\App\Http\Controllers\HitoController::class, 'completar']);
    Route::get('/registros-ges', [RegistroGesController::class, 'index']);
    Route::get('/registros-ges/{registro}', [RegistroGesController::class, 'show']);

    Route::get('/patologias', [PatologiaController::class, 'index']);
    Route::get('/patologias/{patologia}/registros', [PatologiaController::class, 'registros'])->middleware('patologia.autorizacion:view');
    Route::get('/patologias/{patologia}', [PatologiaController::class, 'show'])->middleware('patologia.autorizacion:view');

    Route::get('/pacientes/rut/{rut}', [PacienteController::class, 'byRut']);
    Route::post('/pacientes', [PacienteController::class, 'store']);
    Route::get('/pacientes', [PacienteController::class, 'index']);
    Route::get('/pacientes/{paciente}/registros-ges', [PacienteController::class, 'registrosGes']);
    Route::get('/pacientes/{paciente}', [PacienteController::class, 'show']);
});

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): array {
        return [
            'status' => 'ok',
            'service' => 'plataforma-ges-api',
        ];
    });
});
