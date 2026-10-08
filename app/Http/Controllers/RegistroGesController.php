<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroGesIndexRequest;
use App\Http\Requests\StoreRegistroGesPatologiaRequest;
use App\Http\Requests\StoreRegistroGesRequest;
use App\Http\Requests\UpdateRegistroGesPatologiaRequest;
use App\Http\Resources\PacienteResource;
use App\Http\Resources\PatologiaResource;
use App\Http\Resources\PrioridadResource;
use App\Http\Resources\RegistroGesPatologiaResource;
use App\Http\Resources\RegistroGesResource;
use App\Http\Resources\TipoRegistroResource;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\RegistroGesPatologia;
use App\Models\TipoRegistro;
use App\Services\RegistroGesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RegistroGesController extends Controller
{
    public function __construct(private readonly RegistroGesService $service) {}

    public function index(RegistroGesIndexRequest $request): mixed
    {
        return RegistroGesResource::collection(
            $this->service->listar($request->user(), $request->validated()),
        );
    }

    public function catalogos(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'pacientes' => PacienteResource::collection(
                Paciente::query()
                    ->where('activo', true)
                    ->where(function ($patientQuery) use ($user): void {
                        $patientQuery
                            ->whereDoesntHave('registrosGes')
                            ->orWhereHas('registrosGes', fn ($query) => $query->visibleTo($user));
                    })
                    ->orderBy('apellido_paterno')
                    ->get(),
            ),
            'patologias' => PatologiaResource::collection(
                Patologia::query()
                    ->where('activo', true)
                    ->visibleTo($user)
                    ->orderBy('numero_ges')
                    ->get(),
            ),
            'prioridades' => PrioridadResource::collection(Prioridad::query()->orderBy('nivel')->get()),
            'tipos_registro' => TipoRegistroResource::collection(TipoRegistro::query()->where('activo', true)->orderBy('nombre')->get()),
        ]);
    }

    public function show(RegistroGesIndexRequest $request, int $registro): RegistroGesResource|JsonResponse
    {
        $registroGes = $this->service->buscarVisible($request->user(), $registro);

        if (! $registroGes) {
            return response()->json([
                'message' => 'Registro GES no encontrado.',
            ], 404);
        }

        $relations = ['paciente', 'patologia', 'prioridad', 'tipoRegistro', 'documentos', 'documentosGenerales'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $relations[] = 'asociacionesPatologia.patologia';
        }

        return new RegistroGesResource($registroGes->load($relations));
    }

    public function store(StoreRegistroGesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $patologia = Patologia::findOrFail($validated['id_patologia']);
        if (! $request->user()->puedeEditarPatologia($patologia)) {
            return response()->json([
                'message' => 'No tienes permiso para crear registros de esta patología.',
            ], 403);
        }

        if (! Paciente::query()->whereKey($validated['id_paciente'])->where('activo', true)->exists()) {
            return response()->json(['message' => 'El paciente debe estar activo para crear un registro GES.'], 422);
        }

        $validated['fecha_ingreso'] ??= now()->toDateString();
        $asociadas = $validated['patologias_asociadas'] ?? [];
        $tipoAsociacion = $validated['tipo_asociacion'] ?? 'complicacion';
        $observacionAsociacion = $validated['observacion_asociacion'] ?? null;
        unset($validated['patologias_asociadas'], $validated['tipo_asociacion'], $validated['observacion_asociacion']);

        if ($asociadas && ! Schema::hasTable('registro_ges_patologias')) {
            return response()->json(['message' => 'Las patologías asociadas no están habilitadas.'], 409);
        }

        foreach ($asociadas as $idPatologiaAsociada) {
            if ((int) $idPatologiaAsociada === (int) $patologia->getKey()) {
                return response()->json(['message' => 'La patología principal no puede asociarse nuevamente.'], 422);
            }

            if (! $request->user()->puedeEditarPatologia(Patologia::findOrFail($idPatologiaAsociada))) {
                return response()->json(['message' => 'No tienes permiso para asociar una de las patologías indicadas.'], 403);
            }
        }

        $registro = DB::transaction(function () use ($validated, $asociadas, $tipoAsociacion, $observacionAsociacion): RegistroGes {
            $registro = RegistroGes::create($validated);
            if (Schema::hasTable('registro_ges_patologias')) {
                foreach ($asociadas as $idPatologiaAsociada) {
                    $registro->asociacionesPatologia()->create([
                        'id_patologia' => $idPatologiaAsociada,
                        'tipo' => $tipoAsociacion,
                        'observacion' => $observacionAsociacion,
                    ]);
                }
            }

            return $registro;
        });

        $relations = ['paciente', 'patologia', 'prioridad', 'tipoRegistro'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $relations[] = 'asociacionesPatologia.patologia';
        }

        return response()->json([
            'data' => new RegistroGesResource($registro->fresh($relations)),
            'message' => 'Registro GES creado correctamente.',
        ], 201);
    }

    public function update(StoreRegistroGesRequest $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('update', $registro)) {
            return response()->json([
                'message' => 'No tienes permiso para editar este registro.',
            ], 403);
        }

        $validated = $request->validated();
        if (isset($validated['id_paciente'])
            && ! Paciente::query()->whereKey($validated['id_paciente'])->where('activo', true)->exists()) {
            return response()->json(['message' => 'El paciente debe estar activo para asociarlo al registro GES.'], 422);
        }

        if (isset($validated['id_patologia']) && (int) $validated['id_patologia'] !== (int) $registro->id_patologia) {
            $newPatologia = Patologia::findOrFail($validated['id_patologia']);
            if (! $request->user()->puedeEditarPatologia($newPatologia)) {
                return response()->json(['message' => 'No tienes permiso para cambiar a esta patología.'], 403);
            }

            if (Schema::hasTable('registro_ges_patologias')
                && $registro->asociacionesPatologia()->where('id_patologia', $newPatologia->getKey())->exists()) {
                return response()->json(['message' => 'La nueva patología principal ya está asociada al registro.'], 422);
            }
        }

        $registro->fill($validated);
        $registro->save();

        $relations = ['paciente', 'patologia', 'prioridad', 'tipoRegistro', 'documentos'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $relations[] = 'asociacionesPatologia.patologia';
        }

        return response()->json([
            'data' => new RegistroGesResource($registro->fresh($relations)),
            'message' => 'Registro GES actualizado correctamente.',
        ]);
    }

    public function destroy(Request $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('delete', $registro)) {
            return response()->json(['message' => 'No tienes permiso para eliminar este registro.'], 403);
        }

        // Eliminación lógica: la fila y sus documentos, asignaciones, hitos e historial permanecen en la BD.
        $registro->delete();

        return response()->json([
            'message' => 'Registro GES eliminado correctamente.',
        ]);
    }

    public function listarPatologiasAsociadas(Request $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('view', $registro)) {
            return response()->json(['message' => 'No tienes permiso para ver este registro.'], 403);
        }

        if (! Schema::hasTable('registro_ges_patologias')) {
            return response()->json(['data' => []]);
        }

        $asociaciones = $registro->asociacionesPatologia()
            ->with('patologia')
            ->get()
            ->filter(fn (RegistroGesPatologia $asociacion): bool => $request->user()->puedeVerPatologia($asociacion->patologia));

        return response()->json(['data' => RegistroGesPatologiaResource::collection($asociaciones)]);
    }

    public function agregarPatologiaAsociada(StoreRegistroGesPatologiaRequest $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('update', $registro)) {
            return response()->json(['message' => 'No tienes permiso para editar las patologías de este registro.'], 403);
        }

        if (! Schema::hasTable('registro_ges_patologias')) {
            return response()->json(['message' => 'Las patologías asociadas no están habilitadas.'], 409);
        }

        $validated = $request->validated();
        $patologia = Patologia::findOrFail($validated['id_patologia']);

        if ((int) $patologia->getKey() === (int) $registro->id_patologia) {
            return response()->json(['message' => 'La patología principal no puede asociarse nuevamente.'], 422);
        }

        if (! $request->user()->puedeEditarPatologia($patologia)) {
            return response()->json(['message' => 'No tienes permiso para asociar esta patología.'], 403);
        }

        if ($registro->asociacionesPatologia()->where('id_patologia', $patologia->getKey())->exists()) {
            return response()->json(['message' => 'La patología ya está asociada a este registro.'], 422);
        }

        $asociacion = $registro->asociacionesPatologia()->create($validated);

        return response()->json([
            'data' => new RegistroGesPatologiaResource($asociacion->load('patologia')),
            'message' => 'Patología asociada correctamente.',
        ], 201);
    }

    public function actualizarPatologiaAsociada(
        UpdateRegistroGesPatologiaRequest $request,
        RegistroGes $registro,
        RegistroGesPatologia $asociacion,
    ): JsonResponse {
        if ($request->user()->cannot('update', $registro)) {
            return response()->json(['message' => 'No tienes permiso para editar las patologías de este registro.'], 403);
        }

        if ((int) $asociacion->id_registro !== (int) $registro->id_registro) {
            return response()->json(['message' => 'La asociación no pertenece a este registro.'], 404);
        }

        $asociacion->load('patologia');
        if (! $request->user()->puedeEditarPatologia($asociacion->patologia)) {
            return response()->json(['message' => 'No tienes permiso para editar esta patología asociada.'], 403);
        }

        $validated = $request->validated();
        if (isset($validated['id_patologia'])) {
            $patologia = Patologia::findOrFail($validated['id_patologia']);
            if ((int) $patologia->getKey() === (int) $registro->id_patologia) {
                return response()->json(['message' => 'La patología principal no puede asociarse nuevamente.'], 422);
            }

            if (! $request->user()->puedeEditarPatologia($patologia)) {
                return response()->json(['message' => 'No tienes permiso para asociar esta patología.'], 403);
            }

            if ($registro->asociacionesPatologia()
                ->where('id_patologia', $patologia->getKey())
                ->whereKeyNot($asociacion->getKey())
                ->exists()) {
                return response()->json(['message' => 'La patología ya está asociada a este registro.'], 422);
            }
        }

        $asociacion->update($validated);

        return response()->json([
            'data' => new RegistroGesPatologiaResource($asociacion->fresh('patologia')),
            'message' => 'Patología asociada actualizada correctamente.',
        ]);
    }

    public function quitarPatologiaAsociada(Request $request, RegistroGes $registro, RegistroGesPatologia $asociacion): JsonResponse
    {
        if ($request->user()->cannot('update', $registro)) {
            return response()->json(['message' => 'No tienes permiso para editar las patologías de este registro.'], 403);
        }

        if ((int) $asociacion->id_registro !== (int) $registro->id_registro) {
            return response()->json(['message' => 'La asociación no pertenece a este registro.'], 404);
        }

        $asociacion->load('patologia');
        if (! $request->user()->puedeEditarPatologia($asociacion->patologia)) {
            return response()->json(['message' => 'No tienes permiso para quitar esta patología asociada.'], 403);
        }

        $asociacion->delete();

        return response()->json(['message' => 'Patología asociada eliminada correctamente.']);
    }

    public function anteriores(Request $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('view', $registro)) {
            return response()->json(['message' => 'No tienes permiso para ver el historial de este registro.'], 403);
        }

        $anteriores = $registro->paciente()
            ->firstOrFail()
            ->registrosGes()
            ->whereKeyNot($registro->getKey())
            ->with(['patologia', 'prioridad', 'tipoRegistro'])
            ->orderByDesc('fecha_ingreso')
            ->get()
            ->filter(function (RegistroGes $registroAnterior) use ($request): bool {
                $patologia = $registroAnterior->patologia;

                return $patologia !== null && $request->user()->puedeVerPatologia($patologia);
            });

        return response()->json([
            'data' => RegistroGesResource::collection($anteriores)->response()->getData(true)['data'],
        ]);
    }

    public function pendientes(RegistroGesIndexRequest $request): mixed
    {
        return RegistroGesResource::collection(
            $this->service->pendientes($request->user(), $request->validated()),
        );
    }

    public function asignados(RegistroGesIndexRequest $request): mixed
    {
        return RegistroGesResource::collection(
            $this->service->asignados($request->user(), $request->validated()),
        );
    }

    public function sinAsignar(RegistroGesIndexRequest $request): mixed
    {
        return RegistroGesResource::collection(
            $this->service->sinAsignar($request->user(), $request->validated()),
        );
    }
}
