<?php

namespace App\Http\Controllers;

use App\Http\Requests\AsignarRegistroRequest;
use App\Http\Requests\FinalizarAsignacionRequest;
use App\Http\Requests\ReasignarAsignacionRequest;
use App\Http\Requests\SugerirAsignacionRequest;
use App\Http\Resources\AsignacionResource;
use App\Models\User;
use App\Services\AsignacionService;
use Illuminate\Http\JsonResponse;

class AsignacionController extends Controller
{
    public function __construct(private readonly AsignacionService $service)
    {
    }

    public function asignar(AsignarRegistroRequest $request): AsignacionResource|JsonResponse
    {
        $asignacion = $this->service->asignar($request->user(), $request->validated());

        return new AsignacionResource($asignacion);
    }

    public function reasignar(ReasignarAsignacionRequest $request, int $idAsignacion): AsignacionResource|JsonResponse
    {
        $asignacion = $this->service->reasignar($request->user(), $idAsignacion, $request->validated());

        return new AsignacionResource($asignacion);
    }

    public function finalizar(FinalizarAsignacionRequest $request, int $idAsignacion): AsignacionResource|JsonResponse
    {
        $asignacion = $this->service->finalizar($request->user(), $idAsignacion, $request->validated());

        return new AsignacionResource($asignacion);
    }

    public function cargaPorUsuario(User $usuario): JsonResponse
    {
        return response()->json($this->service->cargaActualPorUsuario($usuario));
    }

    public function historialRegistro(int $registro): JsonResponse
    {
        return response()->json([
            'data' => AsignacionResource::collection($this->service->historialDeAsignaciones($registro)),
        ]);
    }

    public function sugerir(SugerirAsignacionRequest $request): JsonResponse
    {
        return response()->json($this->service->sugerirOperador($request->user(), $request->validated()));
    }
}
