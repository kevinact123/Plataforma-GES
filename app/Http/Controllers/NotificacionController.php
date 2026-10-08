<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $usuario = $request->user();

        $notificaciones = $usuario->notifications()->latest()->limit(20)->get()
            ->map(fn ($n): array => [
                'id' => $n->id,
                'leida' => $n->read_at !== null,
                'fecha' => $n->created_at?->toIso8601String(),
                'titulo' => $n->data['titulo'] ?? 'Notificación',
                'origen' => $n->data['origen'] ?? null,
                'registros' => $n->data['registros'] ?? [],
            ]);

        return response()->json([
            'no_leidas' => $usuario->unreadNotifications()->count(),
            'data' => $notificaciones,
        ]);
    }

    public function marcarLeida(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['message' => 'Notificación marcada como leída.']);
    }

    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Notificaciones marcadas como leídas.']);
    }
}
