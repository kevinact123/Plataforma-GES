<?php

use App\Http\Controllers\AsignacionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ComplejidadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentIngestionController;
use App\Http\Controllers\DocumentoGeneralController;
use App\Http\Controllers\GestionUsuariosController;
use App\Http\Controllers\HitoController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\PacienteController;
use App\Http\Controllers\PatologiaController;
use App\Http\Controllers\RegistroGesController;
use App\Http\Controllers\RegistroGesDocumentoController;
use Illuminate\Support\Facades\Route;

// El login y la verificación OTP viven en routes/web.php: requieren sesión de Laravel.

Route::middleware(['auth:sanctum', 'inactivity', 'confidential'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/notificaciones', [NotificacionController::class, 'index']);
    Route::post('/notificaciones/leer-todas', [NotificacionController::class, 'marcarTodasLeidas']);
    Route::post('/notificaciones/{id}/leer', [NotificacionController::class, 'marcarLeida']);

    Route::middleware('permission:administrar_usuarios')->group(function (): void {
        Route::get('/admin/digitadoras', [GestionUsuariosController::class, 'index']);
        Route::post('/admin/digitadoras', [GestionUsuariosController::class, 'store']);
        Route::put('/admin/digitadoras/{usuario}', [GestionUsuariosController::class, 'update']);
        Route::patch('/admin/digitadoras/{usuario}/estado', [GestionUsuariosController::class, 'cambiarEstado']);
        Route::delete('/admin/digitadoras/{usuario}', [GestionUsuariosController::class, 'destroy']);
    });

    Route::middleware('permission:asignar_pacientes')->group(function (): void {
        Route::post('/asignaciones', [AsignacionController::class, 'asignar']);
        Route::post('/asignaciones/sugerir', [AsignacionController::class, 'sugerir']);
        Route::post('/asignaciones/automaticas/registro/{registro}', [AsignacionController::class, 'asignarAutomaticamenteRegistro']);
        Route::post('/asignaciones/automaticas/paciente/{paciente}', [AsignacionController::class, 'asignarAutomaticamentePaciente']);
        Route::post('/registros-ges/{registro}/asignacion-automatica', [AsignacionController::class, 'asignarAutomaticamenteRegistro']);
        Route::post('/pacientes/{paciente}/asignacion-automatica', [AsignacionController::class, 'asignarAutomaticamentePaciente']);
        Route::get('/usuarios/{usuario}/carga', [AsignacionController::class, 'cargaPorUsuario']);
        Route::get('/registros-ges/{registro}/historial-asignaciones', [AsignacionController::class, 'historialRegistro']);
    });
    Route::middleware('permission:reasignar_pacientes')->group(function (): void {
        Route::post('/asignaciones/{idAsignacion}/reasignar', [AsignacionController::class, 'reasignar']);
        Route::post('/asignaciones/{idAsignacion}/finalizar', [AsignacionController::class, 'finalizar']);
    });

    Route::middleware('permission:ver_dashboard')->group(function (): void {
        Route::get('/dashboard/resumen', [DashboardController::class, 'resumen']);
        Route::get('/dashboard/distribuciones', [DashboardController::class, 'distribuciones']);
        Route::get('/dashboard/carga-operadores', [DashboardController::class, 'cargaOperadores']);
        Route::get('/dashboard/registros-por-operador', [DashboardController::class, 'registrosPorOperador']);
        Route::get('/dashboard/hitos', [DashboardController::class, 'hitos']);
        Route::get('/dashboard/complejidad-promedio', [DashboardController::class, 'complejidadPromedio']);
    });
    Route::middleware('permission:ver_registros')->group(function (): void {
        Route::get('/documentacion', [DocumentoGeneralController::class, 'index']);
        Route::get('/documentacion/categorias', [DocumentoGeneralController::class, 'categories']);
        Route::get('/documentacion/{documento}/download', [DocumentoGeneralController::class, 'download']);
        Route::get('/documentacion/{documento}/preview', [DocumentIngestionController::class, 'preview']);
        Route::get('/documentacion/{documento}/historial-ingestion', [DocumentIngestionController::class, 'historyAnalysis']);
        Route::get('/documentacion/{documento}/descargar', [DocumentIngestionController::class, 'download']);
    });
    Route::middleware('permission:crear_registros')->group(function (): void {
        Route::post('/documentacion/analizar', [DocumentoGeneralController::class, 'analyze']);
        Route::post('/documentacion/ingerir', [DocumentIngestionController::class, 'ingest']);
        Route::post('/documentacion', [DocumentoGeneralController::class, 'store']);
        Route::post('/documentacion/{documento}/importar', [DocumentIngestionController::class, 'import']);
    });
    Route::middleware('permission:editar_registros')->group(function (): void {
        Route::post('/documentacion/categorias', [DocumentoGeneralController::class, 'storeCategory']);
        Route::put('/documentacion/categorias/{categoria}', [DocumentoGeneralController::class, 'updateCategory']);
        Route::delete('/documentacion/categorias/{categoria}', [DocumentoGeneralController::class, 'destroyCategory']);
        Route::put('/documentacion/{documento}', [DocumentoGeneralController::class, 'update']);
        Route::delete('/documentacion/{documento}', [DocumentoGeneralController::class, 'destroy']);
    });
    Route::post('/documentacion/{documento}/asignacion-automatica', [DocumentoGeneralController::class, 'asignarAutomaticamente'])->middleware('permission:asignar_pacientes');

    Route::middleware('permission:ver_dashboard')->group(function (): void {
        Route::get('/complejidad', [ComplejidadController::class, 'index']);
        Route::get('/complejidad/promedio-por-tipo', [ComplejidadController::class, 'promedioPorTipo']);
        Route::get('/complejidad/operadores', [ComplejidadController::class, 'porOperador']);
        Route::get('/complejidad/patologias', [ComplejidadController::class, 'porPatologia']);
    });
    Route::post('/complejidad', [ComplejidadController::class, 'store'])->middleware('permission:editar_registros');

    Route::middleware('permission:ver_registros')->group(function (): void {
        Route::get('/registros-ges/pendientes', [RegistroGesController::class, 'pendientes']);
        Route::get('/registros-ges/asignados', [RegistroGesController::class, 'asignados']);
        Route::get('/registros-ges/sin-asignar', [RegistroGesController::class, 'sinAsignar']);
        Route::get('/registros-ges/catalogos', [RegistroGesController::class, 'catalogos']);
        Route::get('/registros-ges/{registro}/patologias-asociadas', [RegistroGesController::class, 'listarPatologiasAsociadas']);
        Route::get('/registros-ges/{registro}/documentos', [RegistroGesDocumentoController::class, 'index']);
        Route::get('/registros-ges/{registro}/documentos/{documento}', [RegistroGesDocumentoController::class, 'show']);
        Route::get('/registros-ges/{registro}/documentos/{documento}/download', [RegistroGesDocumentoController::class, 'download']);
        Route::get('/registros-ges/{registro}/anteriores', [RegistroGesController::class, 'anteriores']);
        Route::get('/registros-ges/{registro}/hitos', [HitoController::class, 'index']);
        Route::get('/registros-ges/{registro}/hitos/pendientes', [HitoController::class, 'pendientes']);
        Route::get('/registros-ges', [RegistroGesController::class, 'index']);
        Route::get('/registros-ges/{registro}', [RegistroGesController::class, 'show']);
        Route::get('/patologias', [PatologiaController::class, 'index']);
        Route::get('/patologias/{patologia}/registros', [PatologiaController::class, 'registros'])->middleware('patologia.autorizacion:view');
        Route::get('/patologias/{patologia}', [PatologiaController::class, 'show'])->middleware('patologia.autorizacion:view');
        Route::get('/pacientes/rut/{rut}', [PacienteController::class, 'byRut']);
        Route::get('/pacientes', [PacienteController::class, 'index']);
        Route::get('/pacientes/{paciente}/registros-ges', [PacienteController::class, 'registrosGes']);
        Route::get('/pacientes/{paciente}', [PacienteController::class, 'show']);
    });

    Route::middleware('permission:crear_registros')->group(function (): void {
        Route::post('/registros-ges', [RegistroGesController::class, 'store']);
        Route::post('/pacientes', [PacienteController::class, 'store']);
    });

    Route::delete('/registros-ges/{registro}', [RegistroGesController::class, 'destroy'])
        ->middleware('permission:eliminar_registros');

    Route::middleware('permission:editar_registros')->group(function (): void {
        Route::put('/registros-ges/{registro}', [RegistroGesController::class, 'update']);
        Route::post('/registros-ges/{registro}/patologias-asociadas', [RegistroGesController::class, 'agregarPatologiaAsociada']);
        Route::put('/registros-ges/{registro}/patologias-asociadas/{asociacion}', [RegistroGesController::class, 'actualizarPatologiaAsociada']);
        Route::patch('/registros-ges/{registro}/patologias-asociadas/{asociacion}', [RegistroGesController::class, 'actualizarPatologiaAsociada']);
        Route::delete('/registros-ges/{registro}/patologias-asociadas/{asociacion}', [RegistroGesController::class, 'quitarPatologiaAsociada']);
        Route::post('/registros-ges/{registro}/documentos', [RegistroGesDocumentoController::class, 'store']);
        Route::delete('/registros-ges/{registro}/documentos/{documento}', [RegistroGesDocumentoController::class, 'destroy']);
    });

    Route::middleware('permission:gestionar_hitos')->group(function (): void {
        Route::post('/registros-ges/{registro}/hitos', [HitoController::class, 'crear']);
        Route::post('/hitos/{idHito}/iniciar', [HitoController::class, 'iniciar']);
        Route::post('/hitos/{idHito}/completar', [HitoController::class, 'completar']);
        Route::post('/hitos/{idHito}/estado', [HitoController::class, 'cambiarEstado']);

        Route::delete('/hitos/{idHito}', [HitoController::class, 'eliminar']);
    });

    Route::middleware('permission:administrar_patologias')->group(function (): void {
        Route::post('/patologias', [PatologiaController::class, 'store']);
        Route::put('/patologias/{patologia}/confidencialidad', [PatologiaController::class, 'actualizarConfidencialidad']);
    });

    Route::middleware('permission:editar_registros')->group(function (): void {
        Route::post('/pacientes/eliminar-muchos', [PacienteController::class, 'destroyMany']);
        Route::post('/pacientes/eliminar-muchos-definitivamente', [PacienteController::class, 'destroyManyPermanently']);
        Route::delete('/pacientes/{paciente}', [PacienteController::class, 'destroy']);
    });
});

Route::prefix('v1')->group(function (): void {
    Route::get('/health', function (): array {
        return [
            'status' => 'ok',
            'service' => 'plataforma-ges-api',
        ];
    });
});
