<?php

namespace App\Http\Controllers;

use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use App\Services\DocumentAnalysisService;
use App\Services\DocumentPatientDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentPatientController extends Controller
{
    public function __construct(
        private readonly DocumentPatientDetectionService $detector,
        private readonly DocumentAnalysisService $analysis,
    ) {}

    public function detect(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        if ($error = $this->authorizeDocument($request, $documento)) {
            return $error;
        }

        $result = $this->detector->detect($documento);

        if ($result['status'] === 'registrado' && $documento->id_paciente === null) {
            $documento->update(['id_paciente' => $result['paciente']['id_paciente']]);
            $result['vinculado'] = true;
        }

        return response()->json(['data' => $result]);
    }

    public function addPatient(Request $request, DocumentoGeneral $documento): JsonResponse
    {
        if ($error = $this->authorizeDocument($request, $documento)) {
            return $error;
        }

        $validated = $request->validate([
            'rut' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
        ]);

        $rut = $this->analysis->normalizeRut($validated['rut']);

        if ($existing = $this->detector->findPatient($rut)) {
            $documento->update(['id_paciente' => $existing->id_paciente]);

            return response()->json([
                'message' => 'El paciente ya estaba registrado; el documento fue vinculado.',
                'data' => $this->detector->patientData($existing),
            ]);
        }

        [$nombre, $paterno, $materno] = $this->splitName($validated);

        $paciente = Paciente::create([
            'rut' => $rut,
            'nombre' => $nombre,
            'apellido_paterno' => $paterno,
            'apellido_materno' => $materno,
            'fecha_nacimiento' => '1900-01-01',
            'activo' => true,
        ]);

        $documento->update(['id_paciente' => $paciente->id_paciente]);

        return response()->json([
            'message' => 'Paciente agregado a la base de datos y documento vinculado.',
            'data' => $this->detector->patientData($paciente),
        ], 201);
    }

    private function splitName(array $v): array
    {
        if (! empty($v['apellido_paterno'])) {
            return [trim($v['nombre']), trim($v['apellido_paterno']), isset($v['apellido_materno']) ? trim($v['apellido_materno']) : null];
        }

        $parts = preg_split('/\s+/', trim($v['nombre']));
        $count = count($parts);

        return match (true) {
            $count >= 4 => [implode(' ', array_slice($parts, 0, $count - 2)), $parts[$count - 2], $parts[$count - 1]],
            $count === 3 => [$parts[0], $parts[1], $parts[2]],
            $count === 2 => [$parts[0], $parts[1], null],
            default => [$parts[0], 'Sin apellido', null],
        };
    }

    private function authorizeDocument(Request $request, DocumentoGeneral $documento): ?JsonResponse
    {
        $user = $request->user();

        if (! $user?->activo) {
            return response()->json(['message' => 'Tu usuario no está habilitado.'], 403);
        }

        if ($documento->id_usuario !== $user->id_usuario && ! $user->esAdmin()) {
            return response()->json(['message' => 'No tienes permiso sobre este documento.'], 403);
        }

        return null;
    }
}
