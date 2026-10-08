<?php

namespace App\Http\Controllers;

use App\Http\Requests\PacienteIndexRequest;
use App\Http\Requests\PacienteRutRequest;
use App\Http\Requests\StorePacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Http\Resources\RegistroGesResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class PacienteController extends Controller
{
    public function store(StorePacienteRequest $request): JsonResponse
    {
        $paciente = Paciente::create([
            'fecha_nacimiento' => '1900-01-01',
            ...$request->validated(),
            'activo' => true,
        ]);

        return response()->json([
            'data' => new PacienteResource($paciente),
            'message' => 'Paciente creado correctamente.',
        ], 201);
    }

    public function index(PacienteIndexRequest $request): mixed
    {
        if (Gate::forUser($request->user())->denies('viewAny', Paciente::class)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar pacientes.',
            ], 403);
        }

        $user = $request->user();
        $registroRelations = ['patologia', 'prioridad', 'tipoRegistro', 'asignaciones.usuario'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $registroRelations[] = 'asociacionesPatologia.patologia';
        }
        $query = Paciente::query()
            ->withCount(['documentosGenerales as documentos_count' => fn ($documentQuery) => $documentQuery
                ->orWhereIn('id_registro', fn ($ids) => $ids->select('id_registro')->from('registros_ges')->whereColumn('registros_ges.id_paciente', 'pacientes.id_paciente'))])
            ->where('activo', true)
            ->where(function ($patientQuery) use ($user): void {
                $patientQuery
                    ->whereDoesntHave('registrosGes')
                    ->orWhereHas('registrosGes', fn ($registroQuery) => $registroQuery->visibleTo($user));
            })
            ->with(['registrosGes' => fn ($registroQuery) => $registroQuery
                ->visibleTo($user)
                ->with($registroRelations)]);

        if ($request->filled('rut')) {
            $rut = preg_replace('/[^0-9kK]/', '', $request->string('rut')->toString());
            if ($rut !== '') {
                $query->whereRaw("REPLACE(REPLACE(rut, '.', ''), '-', '') LIKE ? ESCAPE '!'", [strtolower($rut).'%']);
            }
        }
        if ($request->filled('nombre')) {
            foreach (preg_split('/\s+/', trim($request->string('nombre')->toString())) as $token) {
                $prefix = addcslashes($token, '%_\\') . '%';
                $query->where(function ($nameQuery) use ($prefix): void {
                    $nameQuery
                        ->where('nombre', 'like', $prefix)
                        ->orWhere('nombre', 'like', '% '.$prefix)
                        ->orWhere('apellido_paterno', 'like', $prefix)
                        ->orWhere('apellido_materno', 'like', $prefix);
                });
            }
        }
        $pacientes = $query->orderBy('apellido_paterno')->paginate($request->integer('per_page', 15));

        return PacienteResource::collection($pacientes);
    }

    public function show(Request $request, Paciente $paciente): PacienteResource|JsonResponse
    {
        if ($request->user()->cannot('view', $paciente)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar este paciente.',
            ], 403);
        }

        $registroRelations = ['patologia', 'prioridad', 'tipoRegistro', 'asignaciones.usuario'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $registroRelations[] = 'asociacionesPatologia.patologia';
        }
        $paciente->load(['registrosGes' => fn ($registroQuery) => $registroQuery
            ->visibleTo($request->user())
            ->with($registroRelations)]);
        $paciente->load('documentosGenerales');

        return new PacienteResource($paciente);
    }

    public function byRut(PacienteRutRequest $request): PacienteResource|JsonResponse
    {
        $rut = $request->validated('rut');

        $registroRelations = ['patologia', 'prioridad', 'tipoRegistro', 'asignaciones.usuario'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $registroRelations[] = 'asociacionesPatologia.patologia';
        }
        $paciente = Paciente::query()
            ->where('rut', $rut)
            ->where('activo', true)
            ->where(function ($patientQuery) use ($request): void {
                $patientQuery
                    ->whereDoesntHave('registrosGes')
                    ->orWhereHas('registrosGes', fn ($registroQuery) => $registroQuery->visibleTo($request->user()));
            })
            ->with(['registrosGes' => fn ($registroQuery) => $registroQuery
                ->visibleTo($request->user())
                ->with($registroRelations)])
            ->first();

        if (! $paciente) {
            return response()->json([
                'message' => 'Paciente no encontrado.',
            ], 404);
        }

        return new PacienteResource($paciente);
    }

    public function registrosGes(Request $request, Paciente $paciente): mixed
    {
        if ($request->user()->cannot('view', $paciente)) {
            return response()->json([
                'message' => 'No tienes permiso para consultar los registros de este paciente.',
            ], 403);
        }

        $registroRelations = ['patologia', 'prioridad', 'tipoRegistro'];
        if (Schema::hasTable('registro_ges_patologias')) {
            $registroRelations[] = 'asociacionesPatologia.patologia';
        }
        $registros = $paciente->registrosGes()
            ->visibleTo($request->user())
            ->with($registroRelations)
            ->orderByDesc('fecha_ingreso')
            ->get();

        return response()->json([
            'data' => RegistroGesResource::collection($registros),
        ]);
    }

    public function destroy(Request $request, Paciente $paciente): JsonResponse
    {
        if ($request->user()->cannot('delete', $paciente)) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar este paciente.',
            ], 403);
        }

        $paciente->update(['activo' => false]);

        return response()->json([
            'message' => 'Paciente eliminado correctamente.',
        ]);
    }

    public function destroyMany(Request $request): JsonResponse
    {
        if (! $request->user()?->activo || ! $request->user()->esAdmin()) {
            return response()->json(['message' => 'No tienes permiso para eliminar pacientes en lote.'], 403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:pacientes,id_paciente'],
        ]);

        $cantidad = DB::transaction(fn (): int => Paciente::query()
            ->whereIn('id_paciente', $validated['ids'])
            ->where('activo', true)
            ->update(['activo' => false]));

        return response()->json([
            'data' => ['eliminados' => $cantidad],
            'message' => "Se eliminaron {$cantidad} pacientes correctamente.",
        ]);
    }

    public function destroyManyPermanently(Request $request): JsonResponse
    {
        if (! $request->user()?->activo || ! $request->user()->esAdmin()) {
            return response()->json(['message' => 'No tienes permiso para eliminar pacientes definitivamente.'], 403);
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'distinct', 'exists:pacientes,id_paciente'],
        ]);

        $deleted = DB::transaction(function () use ($validated): int {
            $patientIds = array_map('intval', $validated['ids']);
            $recordIds = DB::table('registros_ges')
                ->whereIn('id_paciente', $patientIds)
                ->pluck('id_registro')
                ->all();

            if ($recordIds !== []) {
                if (Schema::hasTable('registros_ges_documentos')) {
                    $paths = DB::table('registros_ges_documentos')->whereIn('id_registro', $recordIds)->pluck('ruta_archivo');
                    DB::table('registros_ges_documentos')->whereIn('id_registro', $recordIds)->delete();
                    foreach ($paths as $path) {
                        if ($path) {
                            Storage::disk('local')->delete($path);
                        }
                    }
                }
                foreach (['asignaciones', 'hitos', 'historial_registros', 'registro_ges_patologias'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('id_registro', $recordIds)->delete();
                    }
                }
                if (Schema::hasTable('documentos_generales')) {
                    DB::table('documentos_generales')->whereIn('id_registro', $recordIds)->update(['id_registro' => null]);
                }
                DB::table('registros_ges')->whereIn('id_registro', $recordIds)->delete();
            }

            if (Schema::hasTable('documentos_generales')) {
                DB::table('documentos_generales')->whereIn('id_paciente', $patientIds)->update(['id_paciente' => null]);
            }

            return Paciente::query()->whereIn('id_paciente', $patientIds)->delete();
        });

        return response()->json([
            'data' => [
                'eliminados_definitivamente' => $deleted,
                'bloqueados' => [],
            ],
            'message' => "Se eliminaron definitivamente {$deleted} pacientes y sus dependencias GES; los documentos generales quedaron desasociados.",
        ]);
    }
}
