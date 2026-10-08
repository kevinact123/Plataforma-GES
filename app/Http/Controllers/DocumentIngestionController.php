<?php

namespace App\Http\Controllers;

use App\Models\DocumentoGeneral;
use App\Services\DocumentIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentIngestionController extends Controller
{
    public function __construct(
        private readonly DocumentIngestionService $ingestionService,
    ) {}

    /**
     * Inicia el flujo de ingestión: sube, registra y analiza documento.
     *
     * POST /documentacion/ingerir
     *
     * @response 201 {
     *   "data": {
     *     "id_documento": 1,
     *     "nombre": "archivo.xlsx",
     *     "tipo": "hoja_calculo",
     *     "extension": "xlsx",
     *     "estado": "pendiente",
     *     "puede_importar": true,
     *     "campos_detectados": ["rut", "nombre"],
     *     "datos_previsualizacion": [...],
     *     "campos_ignorados": [],
     *     "errores": [],
     *     "duplicados_detectados": [],
     *     "advertencias": []
     *   },
     *   "message": "Documento registrado y analizado exitosamente."
     * }
     */
    public function ingest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'documento' => [
                'required',
                'file',
                'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg,gif,txt,tif,tiff',
            ],
            'nombre' => ['sometimes', 'nullable', 'string', 'max:255'],
            'id_categoria' => ['sometimes', 'nullable', 'integer', 'exists:documentacion_categorias,id_categoria'],
        ]);

        $user = $request->user();
        if (! $user?->activo) {
            return response()->json([
                'message' => 'Tu usuario no está habilitado para ingerir documentos.',
            ], 403);
        }

        try {
            // Registrar documento
            $registroInfo = $this->ingestionService->registerDocument(
                $validated['documento'],
                $user,
                $validated['nombre'] ?? null,
                $validated['id_categoria'] ?? null,
            );

            // Obtener documento registrado
            $documento = DocumentoGeneral::find($registroInfo['id_documento']);

            // Analizar documento
            $metadatos = $this->ingestionService->analyzeDocument($documento, $request->user());

            $importacion = $this->ingestionService->importarSiCorresponde($documento, $request->user());

            // Obtener previsualización
            $preview = $this->ingestionService->getPreview($documento);

            $message = match (true) {
                ($importacion['status'] ?? null) === 'success' => 'Documento escaneado: se detectaron datos GES y se importaron automáticamente.',
                $importacion !== null => 'Documento escaneado, pero la importación automática falló: '.($importacion['error'] ?? 'revisa los datos.'),
                default => 'Documento guardado. No contiene registros GES, por lo que no se importó nada.',
            };

            return response()->json([
                'data' => $preview,
                'message' => $message,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error durante la ingestión: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene la previsualización detallada de un documento ingestionado.
     *
     * GET /documentacion/{documento}/preview
     *
     * @response 200 {
     *   "data": {
     *     "id_documento": 1,
     *     "nombre": "archivo.xlsx",
     *     "tipo": "hoja_calculo",
     *     "extension": "xlsx",
     *     "estado": "pendiente",
     *     "puede_importar": true,
     *     "campos_detectados": ["rut", "nombre"],
     *     "datos_previsualizacion": [...],
     *     "campos_ignorados": [],
     *     "errores": [],
     *     "duplicados_detectados": [],
     *     "advertencias": []
     *   }
     * }
     */
    public function preview(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $user = $request->user();

        // Validar autorización
        if (! $user?->activo) {
            return response()->json([
                'message' => 'Tu usuario no está habilitado para consultar documentos.',
            ], 403);
        }

        // Verificar que el usuario sea el propietario o un admin
        if ($documento->id_usuario !== $user->id_usuario && ! $user->esAdmin()) {
            return response()->json([
                'message' => 'No tienes permiso para ver la previsualización de este documento.',
            ], 403);
        }

        $preview = $this->ingestionService->getPreview($documento);

        return response()->json([
            'data' => $preview,
        ]);
    }

    /**
     * Confirma e importa un documento ingestionado a la base de datos.
     * Crea pacientes, registros GES, y asocia documentos según sea necesario.
     *
     * POST /documentacion/{documento}/importar
     *
     * @response 200 {
     *   "data": {
     *     "id_documento": 1,
     *     "status": "success",
     *     "mensaje": "Documento importado exitosamente.",
     *     "id_paciente": 123,
     *     "id_registro": 456,
     *     "registros_creados": {
     *       "paciente": true,
     *       "registro_ges": true
     *     }
     *   },
     *   "message": "Importación completada exitosamente."
     * }
     */
    public function import(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $user = $request->user();

        // Validar autorización
        if (! $user?->activo) {
            return response()->json([
                'message' => 'Tu usuario no está habilitado para importar documentos.',
            ], 403);
        }

        // Verificar que el usuario sea el propietario o un admin
        if ($documento->id_usuario !== $user->id_usuario && ! $user->esAdmin()) {
            return response()->json([
                'message' => 'No tienes permiso para importar este documento.',
            ], 403);
        }

        $preview = $this->ingestionService->getPreview($documento);
        if (! array_key_exists('puede_importar', $preview)) {
            return response()->json([
                'message' => 'El documento no ha sido analizado. Primero debe obtener la previsualización.',
                'data' => null,
            ], 422);
        }

        // Validar que el documento esté listo para importar
        if (! ($preview['puede_importar'] ?? false)) {
            return response()->json([
                'message' => 'El documento no está listo para importar. Hay errores en la validación.',
                'errors' => $preview['errores'] ?? [],
            ], 422);
        }

        try {
            $resultado = $this->ingestionService->importDocument($documento, $user);

            if ($resultado['status'] === 'error') {
                return response()->json([
                    'data' => $resultado,
                    'message' => $resultado['error'] ?? 'Error durante la importación.',
                ], 422);
            }

            return response()->json([
                'data' => $resultado,
                'message' => 'Importación completada exitosamente.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error durante la importación: '.$e->getMessage(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Descarga el archivo original de un documento ingestionado.
     *
     * GET /documentacion/{documento}/descargar
     */
    public function download(Request $request, DocumentoGeneral $documento)
    {
        $user = $request->user();

        // Validar autorización
        if (! $user?->activo) {
            return response()->json([
                'message' => 'Tu usuario no está habilitado para descargar documentos.',
            ], 403);
        }

        // Verificar que el usuario sea el propietario o un admin
        if ($documento->id_usuario !== $user->id_usuario && ! $user->esAdmin()) {
            return response()->json([
                'message' => 'No tienes permiso para descargar este documento.',
            ], 403);
        }

        if (! Storage::disk('local')->exists($documento->ruta_archivo)) {
            return response()->json([
                'message' => 'El archivo ya no existe en almacenamiento.',
            ], 404);
        }

        return response()->download(
            Storage::disk('local')->path($documento->ruta_archivo),
            $documento->nombre_original,
            ['Content-Type' => $documento->mime_type ?? 'application/octet-stream'],
        );
    }

    /**
     * Obtiene el historial de análisis de un documento.
     *
     * GET /documentacion/{documento}/historial-ingestion
     */
    public function historyAnalysis(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        $user = $request->user();

        // Validar autorización
        if (! $user?->activo) {
            return response()->json([
                'message' => 'Tu usuario no está habilitado para consultar documentos.',
            ], 403);
        }

        // Verificar que el usuario sea el propietario o un admin
        if ($documento->id_usuario !== $user->id_usuario && ! $user->esAdmin()) {
            return response()->json([
                'message' => 'No tienes permiso para ver el historial de este documento.',
            ], 403);
        }

        $metadatos = $documento->descripcion ? json_decode($documento->descripcion, true) : [];

        return response()->json([
            'data' => [
                'id_documento' => $documento->id_documento,
                'nombre' => $documento->nombre_original,
                'estado_actual' => $documento->estado,
                'ingestion_status' => $metadatos['ingestion_status'] ?? null,
                'registered_at' => $metadatos['registered_at'] ?? null,
                'analyzed_at' => $metadatos['analyzed_at'] ?? null,
                'updated_at' => $metadatos['updated_at'] ?? null,
                'status_details' => $metadatos['status_details'] ?? null,
                'analisis' => [
                    'tipo_documento' => $metadatos['analysis']['tipo_documento'] ?? null,
                    'campos_detectados' => $metadatos['analysis']['campos_detectados'] ?? [],
                    'campos_ignorados' => $metadatos['analysis']['datos_ignorados'] ?? [],
                    'errores' => $metadatos['analysis']['errores'] ?? [],
                ],
                'duplicados' => $metadatos['duplicates'] ?? [],
            ],
        ]);
    }
}
