<?php

namespace App\Http\Controllers;

use App\Http\Requests\PatologiaIndexRequest;
use App\Http\Resources\PatologiaResource;
use App\Http\Resources\RegistroGesResource;
use App\Models\Patologia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatologiaController extends Controller
{
    public function index(PatologiaIndexRequest $request): mixed
    {
        $user = $request->user();
        $query = Patologia::query()
            ->visibleTo($user)
            ->with(['registrosGes' => fn ($registroQuery) => $registroQuery
                ->visibleTo($user)
                ->with(['prioridad', 'tipoRegistro'])]);

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        } else {
            $query->where('activo', true);
        }

        return PatologiaResource::collection(
            $query->orderBy('numero_ges')->paginate($request->integer('per_page', 20)),
        );
    }

    public function store(Request $request): JsonResponse
    {
        if (! $request->user()?->hasPermission('administrar_patologias')) {
            return response()->json([
                'message' => 'No tienes permiso para administrar patologías.',
            ], 403);
        }

        $data = $request->validate([
            'numero_ges' => ['required', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:1000'],
            'confidencial' => ['nullable', 'boolean'],
        ]);

        $patologia = Patologia::query()->create([
            'numero_ges' => (int) $data['numero_ges'],
            'nombre' => trim($data['nombre']),
            'descripcion' => $data['descripcion'] ?? null,
            'confidencial' => (bool) ($data['confidencial'] ?? false),
            'activo' => true,
        ]);

        return response()->json([
            'data' => new PatologiaResource($patologia),
            'message' => 'Patología creada correctamente.',
        ], 201);
    }

    public function actualizarConfidencialidad(Request $request, Patologia $patologia): JsonResponse
    {
        $data = $request->validate([
            'confidencial' => ['required', 'boolean'],
        ]);

        $anterior = (bool) $patologia->confidencial;
        $nuevo = (bool) $data['confidencial'];

        if ($anterior !== $nuevo) {
            $patologia->update(['confidencial' => $nuevo]);

            Log::info('Cambio de confidencialidad de patología', [
                'id_patologia' => $patologia->getKey(),
                'numero_ges' => $patologia->numero_ges,
                'confidencial_anterior' => $anterior,
                'confidencial_nuevo' => $nuevo,
                'id_usuario' => $request->user()->getKey(),
                'ip' => $request->ip(),
            ]);
        }

        return response()->json([
            'data' => new PatologiaResource($patologia->refresh()),
            'message' => 'Confidencialidad de la patología actualizada correctamente.',
        ]);
    }

    public function show(Request $request, Patologia $patologia): PatologiaResource|JsonResponse
    {
        if ($request->user()->cannot('view', $patologia)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar esta patología.',
            ], 403);
        }

        return new PatologiaResource($patologia->load([
            'registrosGes' => fn ($registroQuery) => $registroQuery
                ->visibleTo($request->user())
                ->with(['prioridad', 'tipoRegistro']),
        ]));
    }

    public function registros(Request $request, Patologia $patologia): JsonResponse
    {
        if ($request->user()->cannot('view', $patologia)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar los registros de esta patología.',
            ], 403);
        }

        $registros = $patologia->registrosGes()
            ->visibleTo($request->user())
            ->with(['paciente', 'prioridad', 'tipoRegistro'])
            ->orderByDesc('fecha_ingreso')
            ->get();

        return response()->json([
            'data' => RegistroGesResource::collection($registros),
        ]);
    }
}
