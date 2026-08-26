<?php

namespace App\Http\Controllers;

use App\Http\Requests\PacienteIndexRequest;
use App\Http\Requests\PacienteRutRequest;
use App\Http\Requests\StorePacienteRequest;
use App\Http\Resources\PacienteResource;
use App\Models\Paciente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PacienteController extends Controller
{
	public function store(StorePacienteRequest $request): JsonResponse
	{
		$paciente = Paciente::create([
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
		$query = Paciente::query()
			->where('activo', true)
			->where(function ($patientQuery) use ($user): void {
				$patientQuery
					->whereDoesntHave('registrosGes')
					->orWhereHas('registrosGes', fn ($registroQuery) => $registroQuery->visibleTo($user));
			})
			->with(['registrosGes' => fn ($registroQuery) => $registroQuery
				->visibleTo($user)
				->with(['patologia', 'prioridad', 'tipoRegistro'])]);

		if ($request->filled('rut')) {
			$query->where('rut', $request->string('rut')->toString());
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

		$paciente->load(['registrosGes' => fn ($registroQuery) => $registroQuery
			->visibleTo($request->user())
			->with(['patologia', 'prioridad', 'tipoRegistro'])]);

		return new PacienteResource($paciente);
	}

	public function byRut(PacienteRutRequest $request): PacienteResource|JsonResponse
	{
		$rut = $request->validated('rut');

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
				->with(['patologia', 'prioridad', 'tipoRegistro'])])
			->first();

		if (!$paciente) {
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

		$registros = $paciente->registrosGes()
			->visibleTo($request->user())
			->with(['patologia', 'prioridad', 'tipoRegistro'])
			->orderByDesc('fecha_ingreso')
			->get();

		return response()->json([
			'data' => \App\Http\Resources\RegistroGesResource::collection($registros),
		]);
	}
}
