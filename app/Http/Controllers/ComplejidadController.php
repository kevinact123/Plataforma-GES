<?php

namespace App\Http\Controllers;

use App\Services\ComplejidadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplejidadController extends Controller
{
    public function __construct(private readonly ComplejidadService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->consultar($request->user()));
    }

    public function promedioPorTipo(Request $request): JsonResponse
    {
        return response()->json($this->service->promedioPorTipo($request->user()));
    }

    public function porOperador(Request $request): JsonResponse
    {
        return response()->json($this->service->porOperador($request->user()));
    }

    public function porPatologia(Request $request): JsonResponse
    {
        return response()->json($this->service->porPatologia($request->user()));
    }
}