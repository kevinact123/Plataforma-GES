<?php

namespace App\Http\Controllers;

use App\Http\Resources\PatologiaResource;
use App\Http\Resources\RegistroGesResource;
use App\Models\Patologia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatologiaController extends Controller
{
	public function index(\App\Http\Requests\PatologiaIndexRequest $request): mixed
	{
		$user = $request->user();
		$query = Patologia::query()
			->where(function ($visibilityQuery) use ($user): void {
				$visibilityQuery
					->where('confidencial', false)
					->orWhereIn(
						'id_patologia',
						$user->permisosPatologia()
							->where('puede_ver', true)
							->select('id_patologia'),
					);
			})
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
