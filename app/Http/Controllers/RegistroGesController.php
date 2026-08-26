<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroGesIndexRequest;
use App\Http\Resources\PacienteResource;
use App\Http\Resources\RegistroGesResource;
use App\Http\Resources\PatologiaResource;
use App\Http\Resources\PrioridadResource;
use App\Http\Resources\TipoRegistroResource;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\Prioridad;
use App\Models\RegistroGes;
use App\Models\TipoRegistro;
use App\Services\RegistroGesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class RegistroGesController extends Controller
{
    public function __construct(private readonly RegistroGesService $service)
    {
    }

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
                    ->where(function ($query) use ($user): void {
                        $query->where('confidencial', false)
                            ->orWhereIn('id_patologia', $user->permisosPatologia()->where('puede_ver', true)->select('id_patologia'));
                    })
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

        if (!$registroGes) {
            return response()->json([
                'message' => 'Registro GES no encontrado.',
            ], 404);
        }

        return new RegistroGesResource($registroGes->load(['paciente', 'patologia', 'prioridad', 'tipoRegistro', 'documentos']));
    }

    public function store(\App\Http\Requests\StoreRegistroGesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $patologia = Patologia::findOrFail($validated['id_patologia']);
        if (! $request->user()->puedeEditarPatologia($patologia)) {
            return response()->json([
                'message' => 'No tienes permiso para crear registros de esta patología.',
            ], 403);
        }

        $registro = RegistroGes::create($validated);

        return response()->json([
            'data' => new RegistroGesResource($registro->fresh(['paciente', 'patologia', 'prioridad', 'tipoRegistro'])),
            'message' => 'Registro GES creado correctamente.',
        ], 201);
    }

    public function destroy(Request $request, RegistroGes $registro): JsonResponse
    {
        if ($request->user()->cannot('delete', $registro)) {
            return response()->json(['message' => 'No tienes permiso para eliminar este registro.'], 403);
        }

        DB::transaction(function () use ($registro): void {
            if (Schema::hasTable('registros_ges_documentos')) {
                foreach ($registro->documentos as $documento) {
                    if (! empty($documento->ruta_archivo) && Storage::disk('local')->exists($documento->ruta_archivo)) {
                        Storage::disk('local')->delete($documento->ruta_archivo);
                    }

                    $documento->delete();
                }
            }

            if (Schema::hasTable('asignaciones')) {
                $registro->asignaciones()->delete();
            }

            if (Schema::hasTable('hitos')) {
                $registro->hitos()->delete();
            }

            if (Schema::hasTable('historial_registros')) {
                $registro->historial()->delete();
            }

            $registro->delete();
        });

        return response()->json([
            'message' => 'Registro GES eliminado correctamente.',
        ]);
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
