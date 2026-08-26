<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDigitadoraRequest;
use App\Services\GestionUsuariosService;
use Illuminate\Http\JsonResponse;

class GestionUsuariosController extends Controller
{
    public function __construct(private readonly GestionUsuariosService $service)
    {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->digitadoras(),
            'patologias' => $this->service->patologias(),
        ]);
    }

    public function store(StoreDigitadoraRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Digitadora creada correctamente.',
            'data' => $this->service->crearDigitadora($request->validated()),
        ], 201);
    }
}
