<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Models\AuditoriaAcceso;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    // Tiempo máximo que puede permanecer pendiente la etapa OTP antes de exigir un nuevo login.
    private const OTP_PENDIENTE_TTL_MINUTOS = 5;

    private const MENSAJE_CUENTA_BLOQUEADA = 'Tu cuenta ha sido bloqueada por demasiados intentos fallidos. Debes ponerte en contacto con el administrador para reactivar tu cuenta.';

    // Suma un intento fallido; al llegar al máximo bloquea la cuenta (los administradores no se bloquean).
    private function registrarIntentoFallido(User $user, bool $bloqueoInmediato = false): bool
    {
        if ($user->esAdmin()) {
            return false;
        }

        $intentos = ((int) $user->intentos_fallidos) + 1;
        $bloquear = $bloqueoInmediato || $intentos >= User::MAX_INTENTOS_FALLIDOS;

        $user->forceFill([
            'intentos_fallidos' => $intentos,
            'bloqueado_en' => $bloquear ? now() : null,
            'activo' => $bloquear ? false : $user->activo,
        ])->save();

        if ($bloquear) {
            $user->tokens()->delete();
        }

        return $bloquear;
    }

    private function limpiarIntentosFallidos(User $user): void
    {
        if ($user->intentos_fallidos) {
            $user->forceFill(['intentos_fallidos' => 0])->save();
        }
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('username', $credentials['username'])->first();

        if ($user && $user->estaBloqueado()) {
            $this->registrarAuditoria($request, $user, 'rechazado', 'Intento de acceso con cuenta bloqueada.');

            return response()->json(['message' => self::MENSAJE_CUENTA_BLOQUEADA, 'bloqueada' => true], 423);
        }

        if (! $user || ! $user->activo || ! Hash::check($credentials['password'], $user->password)) {
            if ($user && $user->activo && $this->registrarIntentoFallido($user)) {
                $this->registrarAuditoria($request, $user, 'rechazado', 'Cuenta bloqueada por intentos fallidos de contraseña.');

                return response()->json(['message' => self::MENSAJE_CUENTA_BLOQUEADA, 'bloqueada' => true], 423);
            }

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

        $this->limpiarIntentosFallidos($user);

        // Solo la cuenta configurada como administrador del sistema omite el OTP.
        if ($user->esAdministradorSistema()) {
            try {
                $token = $user->createToken('api-token')->plainTextToken;
                $user->load('rol');
                $this->registrarAuditoria($request, $user, 'conectado', 'Login correcto (administrador del sistema, sin OTP).');
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
                    'permissions' => $user->permissionNames(),
                ],
            ]);
        }

        if (! $user->correo) {
            Log::error('El usuario no tiene correo institucional configurado.', [
                'id_usuario' => $user->getKey(),
            ]);

            return response()->json([
                'message' => 'No fue posible enviar el código de verificación. Inténtalo nuevamente.',
            ], 422);
        }

        try {
            // El OTP queda pendiente en la sesión de Laravel; el usuario no está autenticado todavía.
            $request->session()->regenerate();
            $request->session()->put('otp_pendiente_usuario_id', $user->getKey());
            $request->session()->put(
                'otp_pendiente_expira_en',
                now()->addMinutes(self::OTP_PENDIENTE_TTL_MINUTOS)->timestamp
            );

            app(OtpService::class)->generar($user);

            $this->registrarAuditoria($request, $user, 'otp_pendiente', 'Credenciales correctas, código de verificación enviado.');
        } catch (\Throwable $exception) {
            Log::error('No fue posible enviar el código de verificación.', [
                'username' => $user->username,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible enviar el código de verificación. Inténtalo nuevamente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Código de verificación enviado.',
            'correo_enmascarado' => $this->enmascararCorreo($user->correo),
        ]);
    }

    public function verificarOtp(Request $request): JsonResponse
    {
        $request->validate([
            'codigo' => ['required', 'string'],
        ]);

        $idUsuario = $this->usuarioPendienteDeOtp($request);

        if (! $idUsuario) {
            return response()->json([
                'message' => 'La sesión de verificación expiró. Inicia sesión nuevamente.',
            ], 422);
        }

        $user = User::find($idUsuario);

        if (! $user || ! $user->activo) {
            return response()->json([
                'message' => 'No fue posible completar la verificación.',
            ], 422);
        }

        $resultado = app(OtpService::class)->verificar($user, $request->string('codigo')->toString());

        if ($resultado === OtpService::RESULTADO_DEMASIADOS_INTENTOS && $this->registrarIntentoFallido($user, true)) {
            $request->session()->forget(['otp_pendiente_usuario_id', 'otp_pendiente_expira_en']);
            $this->registrarAuditoria($request, $user, 'rechazado', 'Cuenta bloqueada por intentos fallidos de código de verificación.');

            return response()->json(['message' => self::MENSAJE_CUENTA_BLOQUEADA, 'bloqueada' => true], 423);
        }

        if ($resultado !== OtpService::RESULTADO_OK) {
            $this->registrarAuditoria($request, $user, 'rechazado', 'Código de verificación: '.$resultado);

            $mensajes = [
                OtpService::RESULTADO_INVALIDO => 'El código ingresado no es válido.',
                OtpService::RESULTADO_EXPIRADO => 'El código ha expirado. Solicita uno nuevo.',
                OtpService::RESULTADO_DEMASIADOS_INTENTOS => 'Se alcanzó el límite de intentos. Solicita un nuevo código.',
            ];

            return response()->json([
                'message' => $mensajes[$resultado] ?? 'El código ingresado no es válido.',
            ], 422);
        }

        try {
            $request->session()->forget(['otp_pendiente_usuario_id', 'otp_pendiente_expira_en']);
            $request->session()->regenerate();

            $token = $user->createToken('api-token')->plainTextToken;
            $user->load('rol');
            $this->registrarAuditoria($request, $user, 'conectado', 'Login correcto (OTP verificado).');
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
                'permissions' => $user->permissionNames(),
            ],
        ]);
    }

    public function reenviarOtp(Request $request): JsonResponse
    {
        $idUsuario = $this->usuarioPendienteDeOtp($request);

        if (! $idUsuario) {
            return response()->json([
                'message' => 'La sesión de verificación expiró. Inicia sesión nuevamente.',
            ], 422);
        }

        $user = User::find($idUsuario);

        if (! $user || ! $user->activo) {
            return response()->json([
                'message' => 'No fue posible enviar el código de verificación. Inténtalo nuevamente.',
            ], 422);
        }

        try {
            app(OtpService::class)->reenviar($user);
            // Reenviar extiende la ventana de la etapa OTP mientras el usuario sigue activo.
            $request->session()->put(
                'otp_pendiente_expira_en',
                now()->addMinutes(self::OTP_PENDIENTE_TTL_MINUTOS)->timestamp
            );
        } catch (\Throwable $exception) {
            Log::error('No fue posible reenviar el código de verificación.', [
                'username' => $user->username,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible enviar el código de verificación. Inténtalo nuevamente.',
            ], 500);
        }

        return response()->json([
            'message' => 'Se envió un nuevo código de verificación.',
        ]);
    }

    private function enmascararCorreo(string $correo): string
    {
        [$local, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        $visible = mb_substr($local, 0, 4);

        return $visible.'***@'.$dominio;
    }

    private function usuarioPendienteDeOtp(Request $request): ?int
    {
        $idUsuario = $request->session()->get('otp_pendiente_usuario_id');
        $expiraEn = $request->session()->get('otp_pendiente_expira_en');

        if (! $idUsuario || ! $expiraEn || $expiraEn < now()->timestamp) {
            $request->session()->forget(['otp_pendiente_usuario_id', 'otp_pendiente_expira_en']);

            if ($idUsuario && ($usuario = User::find($idUsuario))) {
                app(OtpService::class)->invalidar($usuario);
            }

            return null;
        }

        return (int) $idUsuario;
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
        $user = $request->user();

        return response()->json([
            'user' => array_merge($user->toArray(), [
                'permissions' => $user->permissionNames(),
                'es_admin' => $user->esAdmin(),
            ]),
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
