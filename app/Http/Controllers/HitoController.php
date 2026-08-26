<?php

namespace App\Http\Controllers;

use App\Http\Requests\HitoCrearRequest;
use App\Http\Requests\HitoIniciarRequest;
use App\Http\Requests\HitoCompletarRequest;
use App\Services\HitoService;
use Illuminate\Http\JsonResponse;

class HitoController extends Controller
{
    public function __construct(private readonly HitoService $service)
    {
    }

    public function crear(int $registro, HitoCrearRequest $request): JsonResponse
    {
        $hito = $this->service->crear($request->user(), $registro, $request->validated());

        return response()->json([
            'data' => $hito->fresh(['registroGes', 'usuario']),
        ]);
    }

    public function iniciar(int $idHito, HitoIniciarRequest $request): JsonResponse
    {
        $hito = $this->service->iniciar($request->user(), $idHito, $request->validated());

        return response()->json([
            'data' => $hito->fresh(['registroGes', 'usuario']),
        ]);
    }

    public function completar(int $idHito, HitoCompletarRequest $request): JsonResponse
    {
        $hito = $this->service->completar($request->user(), $idHito, $request->validated());

        return response()->json([
            'data' => $hito->fresh(['registroGes', 'usuario']),
        ]);
    }

    public function index(int $registro): JsonResponse
    {
        return response()->json([
            'data' => $this->service->consultar(request()->user(), $registro),
        ]);
    }

    public function pendientes(int $registro): JsonResponse
    {
        return response()->json([
            'data' => $this->service->pendientes(request()->user(), $registro),
        ]);
    }
}
