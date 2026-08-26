<?php

namespace App\Http\Controllers;

use App\Models\RegistroGes;
use App\Models\RegistroGesDocumento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistroGesDocumentoController extends Controller
{
    public function index(Request $request, RegistroGes $registro): JsonResponse
    {
        $this->authorizeRegistro($request, $registro, 'view');

        return response()->json([
            'data' => $registro->documentos()->orderByDesc('fecha_creacion')->get(),
        ]);
    }

    public function show(Request $request, RegistroGes $registro, RegistroGesDocumento $documento): JsonResponse
    {
        $this->authorizeRegistro($request, $registro, 'view');

        if ($documento->id_registro !== $registro->id_registro) {
            return response()->json(['message' => 'Documento no encontrado para este registro.'], 404);
        }

        return response()->json(['data' => $documento]);
    }

    public function store(Request $request, RegistroGes $registro): JsonResponse
    {
        $this->authorizeRegistro($request, $registro, 'view');

        $validated = $request->validate([
            'documento' => ['required', 'file', 'max:20480'],
            'observaciones' => ['nullable', 'string', 'max:255'],
        ]);

        $archivo = $validated['documento'];
        $nombreArchivo = $this->generarNombreArchivo($archivo);
        $ruta = $archivo->storeAs('registros-ges/' . $registro->id_registro, $nombreArchivo, 'local');

        $documento = $registro->documentos()->create([
            'nombre_original' => $archivo->getClientOriginalName(),
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $ruta,
            'mime_type' => $archivo->getMimeType(),
            'tamanio' => $archivo->getSize(),
            'observaciones' => $validated['observaciones'] ?? null,
        ]);

        return response()->json([
            'data' => $documento,
            'message' => 'Documento adjuntado correctamente.',
        ], 201);
    }

    public function download(Request $request, RegistroGes $registro, RegistroGesDocumento $documento): StreamedResponse|JsonResponse
    {
        $this->authorizeRegistro($request, $registro, 'view');

        if ($documento->id_registro !== $registro->id_registro) {
            return response()->json(['message' => 'Documento no encontrado para este registro.'], 404);
        }

        if (!Storage::disk('local')->exists($documento->ruta_archivo)) {
            return response()->json(['message' => 'El archivo ya no existe en almacenamiento.'], 404);
        }

        return Storage::disk('local')->download($documento->ruta_archivo, $documento->nombre_original);
    }

    public function destroy(Request $request, RegistroGes $registro, RegistroGesDocumento $documento): JsonResponse
    {
        $this->authorizeRegistro($request, $registro, 'view');

        if ($documento->id_registro !== $registro->id_registro) {
            return response()->json(['message' => 'Documento no encontrado para este registro.'], 404);
        }

        if (Storage::disk('local')->exists($documento->ruta_archivo)) {
            Storage::disk('local')->delete($documento->ruta_archivo);
        }

        $documento->delete();

        return response()->json([
            'message' => 'Documento eliminado correctamente.',
        ]);
    }

    private function authorizeRegistro(Request $request, RegistroGes $registro): void
    {
        if (! $request->user() || $request->user()->cannot($ability, $registro)) {
            abort(403, 'No tienes permiso para gestionar documentación de este registro.');
        }
    }

    private function generarNombreArchivo($archivo): string
    {
        $extension = $archivo->getClientOriginalExtension();
        $base = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME));

        return sprintf('%s-%s.%s', $base ?: 'documento', now()->format('YmdHis'), $extension ?: 'bin');
    }
}
