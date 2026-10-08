<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDigitadoraRequest;
use App\Http\Requests\UpdateDigitadoraRequest;
use App\Models\User;
use App\Services\GestionUsuariosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GestionUsuariosController extends Controller
{
    public function __construct(private readonly GestionUsuariosService $service) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->service->usuariosRegistrados(),
            'patologias' => $this->service->patologias(),
            'roles' => $this->service->roles(),
        ]);
    }

    public function store(StoreDigitadoraRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $this->service->crearDigitadora($request->validated()),
        ], 201);
    }

    public function update(UpdateDigitadoraRequest $request, User $usuario): JsonResponse
    {
        return response()->json([
            'message' => 'Permisos actualizados correctamente.',
            'data' => $this->service->actualizarDigitadora($usuario, $request->validated()),
        ]);
    }

    public function cambiarEstado(Request $request, User $usuario): JsonResponse
    {
        $request->validate([
            'activo' => ['required', 'boolean'],
        ]);

        return response()->json([
            'message' => $request->boolean('activo')
                ? 'Digitadora reactivada correctamente.'
                : 'Digitadora desactivada correctamente. Sus registros y asignaciones se conservaron.',
            'data' => $this->service->cambiarEstadoDigitadora($usuario, $request->boolean('activo')),
        ]);
    }

    public function destroy(User $usuario): JsonResponse
    {
        $this->service->eliminarDigitadora($usuario);

        return response()->json(['message' => 'Digitador/a eliminado/a correctamente.']);
    }
}
