<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\AuditoriaAcceso;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
	public function login(LoginRequest $request): JsonResponse
	{
		$credentials = $request->validated();

		$user = User::where('username', $credentials['username'])->first();

		if (!$user || !$user->activo || !Hash::check($credentials['password'], $user->password)) {
			$this->registrarAuditoria(
				$request,
				$user,
				'rechazado',
				'Las credenciales son incorrectas.'
			);

			return response()->json([
				'message' => 'Las credenciales son incorrectas.',
			], 401);
		}

		try {
			$token = $user->createToken('api-token')->plainTextToken;
			$user->load('rol');
			$this->registrarAuditoria($request, $user, 'conectado', 'Login correcto.');
		} catch (\Throwable $exception) {
			Log::error('No fue posible completar el login.', [
				'username' => $user->username,
				'error' => $exception->getMessage(),
			]);

			return response()->json([
				'message' => 'No fue posible completar la autenticación.',
			], 500);
		}

		return response()->json([
			'message' => 'Autenticación exitosa.',
			'token' => $token,
			'token_type' => 'Bearer',
			'user' => [
				'id_usuario' => $user->id_usuario,
				'nombre' => $user->nombre,
				'apellido' => $user->apellido,
				'username' => $user->username,
				'rol' => $user->rol?->nombre,
				'es_admin' => $user->esAdmin(),
			],
		]);
	}

	public function logout(Request $request): JsonResponse
	{
		$user = $request->user();

		try {
			AuditoriaAcceso::query()
				->where('id_usuario', $user->getKey())
				->where('estado', 'CONECTADO')
				->whereNull('fecha_hora_salida')
				->latest('fecha_hora_entrada')
				->first()
				?->update(['fecha_hora_salida' => now()]);

			$user->currentAccessToken()?->delete();
		} catch (\Throwable $exception) {
			Log::error('No fue posible registrar el cierre de sesión.', [
				'id_usuario' => $user->getKey(),
				'error' => $exception->getMessage(),
			]);

			return response()->json([
				'message' => 'No fue posible completar el cierre de sesión.',
			], 500);
		}

		return response()->json([
			'message' => 'Sesión cerrada correctamente.',
		]);
	}

	public function me(Request $request): JsonResponse
	{
		return response()->json([
			'user' => $request->user(),
		]);
	}

	private function registrarAuditoria(
		Request $request,
		?User $user,
		string $estado,
		string $motivo
	): void {
		try {
			AuditoriaAcceso::create([
				'id_usuario' => $user?->getKey(),
				'nombre_usuario' => (string) $request->input('username', $user?->username ?? 'desconocido'),
				'tipo_documento' => (string) $request->input('tipo_documento', 'USERNAME'),
				'fecha_hora_entrada' => now(),
				'ip' => $request->ip(),
				'estado' => strtoupper($estado),
				'motivo' => $motivo,
			]);
		} catch (\Throwable $exception) {
			Log::error('No fue posible registrar la auditoría de acceso.', [
				'id_usuario' => $user?->getKey(),
				'username' => $request->input('username'),
				'estado' => strtoupper($estado),
				'error' => $exception->getMessage(),
			]);
		}
	}
}
