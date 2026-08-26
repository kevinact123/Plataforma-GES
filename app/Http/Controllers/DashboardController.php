<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function resumen(Request $request): JsonResponse
    {
        return response()->json($this->service->resumen($request->user()));
    }

    public function distribuciones(Request $request): JsonResponse
    {
        return response()->json($this->service->distribuciones($request->user()));
    }

    public function cargaOperadores(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->cargaOperadores($request->user()),
        ]);
    }

    public function registrosPorOperador(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->service->registrosPorOperador($request->user()),
        ]);
    }

    public function hitos(Request $request): JsonResponse
    {
        return response()->json($this->service->hitos($request->user()));
    }

    public function complejidadPromedio(Request $request): JsonResponse
    {
        return response()->json($this->service->complejidadPromedio($request->user()));
    }
}
