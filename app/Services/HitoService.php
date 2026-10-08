<?php

namespace App\Services;

use App\Models\Hito;
use App\Models\RegistroGes;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HitoService
{
    public function crear(User $usuarioActual, int $idRegistro, array $data): Hito
    {
        return DB::transaction(function () use ($usuarioActual, $idRegistro, $data): Hito {
            $registro = RegistroGes::query()->findOrFail($idRegistro);
            $this->autorizarRegistro($usuarioActual, $registro);
            $responsable = User::query()->findOrFail($data['id_usuario'] ?? $usuarioActual->id_usuario);

            if (! $responsable->activo) {
                throw ValidationException::withMessages([
                    'id_usuario' => ['El usuario responsable del hito debe estar activo.'],
                ]);
            }

            $hito = Hito::create([
                'id_registro' => $registro->id_registro,
                'id_usuario' => $responsable->id_usuario,
                'nombre' => trim((string) ($data['nombre'] ?? '')),
                'estado' => 'pendiente',
                'observacion' => $data['observacion'] ?? null,
            ]);

            app(RegistroGesAuditService::class)->registrar(
                $registro->id_registro,
                $usuarioActual->id_usuario,
                'hito_creado',
                'estado',
                null,
                $hito->estado,
            );
            $this->sincronizarEstadoRegistro($registro);

            return $hito->fresh(['registroGes', 'usuario']);
        });
    }

    public function iniciar(User $usuarioActual, int $idHito, array $data): Hito
    {
        return DB::transaction(function () use ($usuarioActual, $idHito, $data): Hito {
            $hito = Hito::query()->lockForUpdate()->findOrFail($idHito);
            $this->autorizarRegistro($usuarioActual, $hito->registroGes);

            if ($hito->estado === 'completado') {
                throw ValidationException::withMessages([
                    'hito' => ['No se puede iniciar un hito ya completado.'],
                ]);
            }

            $estadoAnterior = $hito->estado;

            $hito->update([
                'estado' => 'en_proceso',
                'fecha_inicio' => $hito->fecha_inicio ?? now(),
                'observacion' => $this->fusionarObservacion($hito->observacion, $data['observacion'] ?? 'Inicio del hito'),
            ]);

            app(RegistroGesAuditService::class)->registrar(
                $hito->id_registro,
                $usuarioActual->id_usuario,
                'hito_iniciado',
                'estado',
                $estadoAnterior,
                $hito->fresh()->estado,
            );
            $this->sincronizarEstadoRegistro($hito->registroGes);

            return $hito->fresh(['registroGes', 'usuario']);
        });
    }

    public function completar(User $usuarioActual, int $idHito, array $data): Hito
    {
        return DB::transaction(function () use ($usuarioActual, $idHito, $data): Hito {
            $hito = Hito::query()->lockForUpdate()->findOrFail($idHito);
            $this->autorizarRegistro($usuarioActual, $hito->registroGes);

            if ($hito->estado === 'completado') {
                throw ValidationException::withMessages([
                    'hito' => ['El hito ya está completado.'],
                ]);
            }

            $estadoAnterior = $hito->estado;

            $hito->update([
                'estado' => 'completado',
                'fecha_inicio' => $hito->fecha_inicio ?? now(),
                'fecha_completado' => $hito->fecha_completado ?? now(),
                'observacion' => $this->fusionarObservacion($hito->observacion, $data['observacion'] ?? 'Hito completado'),
            ]);

            app(RegistroGesAuditService::class)->registrar(
                $hito->id_registro,
                $usuarioActual->id_usuario,
                'hito_completado',
                'estado',
                $estadoAnterior,
                $hito->fresh()->estado,
            );
            $this->sincronizarEstadoRegistro($hito->registroGes);

            return $hito->fresh(['registroGes', 'usuario']);
        });
    }

    public function cambiarEstado(User $usuarioActual, int $idHito, array $data): Hito
    {
        return DB::transaction(function () use ($usuarioActual, $idHito, $data): Hito {
            $hito = Hito::query()->lockForUpdate()->findOrFail($idHito);
            $this->autorizarRegistro($usuarioActual, $hito->registroGes);

            $estadoNuevo = $data['estado'];
            $estadoAnterior = $hito->estado;

            if ($estadoNuevo === 'completado') {
                $hito->fecha_completado = $hito->fecha_completado ?? now();
            }

            if ($estadoNuevo === 'en_proceso' && $hito->fecha_inicio === null) {
                $hito->fecha_inicio = now();
            }

            if ($estadoNuevo === 'pendiente') {
                $hito->fecha_inicio = $hito->fecha_inicio ?? null;
                $hito->fecha_completado = null;
            }

            $hito->update([
                'estado' => $estadoNuevo,
                'fecha_inicio' => $hito->fecha_inicio ?? now(),
                'fecha_completado' => $hito->fecha_completado,
                'observacion' => $this->fusionarObservacion($hito->observacion, $data['observacion'] ?? 'Cambio de estado del hito'),
            ]);

            app(RegistroGesAuditService::class)->registrar(
                $hito->id_registro,
                $usuarioActual->id_usuario,
                'hito_estado_actualizado',
                'estado',
                $estadoAnterior,
                $hito->fresh()->estado,
            );

            return $hito->fresh(['registroGes', 'usuario']);
        });
    }

    public function eliminar(User $usuarioActual, int $idHito): void
    {
        DB::transaction(function () use ($usuarioActual, $idHito): void {
            $hito = Hito::query()->lockForUpdate()->findOrFail($idHito);
            $this->autorizarRegistro($usuarioActual, $hito->registroGes);

            $hito->delete();

            app(RegistroGesAuditService::class)->registrar(
                $hito->id_registro,
                $usuarioActual->id_usuario,
                'hito_eliminado',
                'estado',
                $hito->estado,
                null,
            );
            $this->sincronizarEstadoRegistro($hito->registroGes);
        });
    }

    public function consultar(User $usuarioActual, int $idRegistro): Collection
    {
        $registro = RegistroGes::query()->findOrFail($idRegistro);
        $this->autorizarRegistro($usuarioActual, $registro);

        $query = Hito::query()
            ->with(['usuario'])
            ->where('id_registro', $idRegistro);

        return $query->orderBy('id_hito')->get();
    }

    public function pendientes(User $usuarioActual, int $idRegistro): Collection
    {
        $registro = RegistroGes::query()->findOrFail($idRegistro);
        $this->autorizarRegistro($usuarioActual, $registro);

        $query = Hito::query()
            ->with(['usuario'])
            ->where('id_registro', $idRegistro)
            ->whereIn('estado', ['pendiente', 'en_proceso']);

        return $query->orderBy('id_hito')->get();
    }

    private function fusionarObservacion(?string $observacionActual, string $nuevaObservacion): string
    {
        $base = trim((string) $observacionActual);
        $nueva = trim($nuevaObservacion);

        if ($base === '') {
            return $nueva;
        }

        if ($nueva === '') {
            return $base;
        }

        return $base.PHP_EOL.$nueva;
    }

    private function autorizarRegistro(User $usuario, RegistroGes $registro): void
    {
        if ($usuario->cannot('view', $registro)) {
            abort(403, 'No tienes permiso para consultar los hitos de este registro.');
        }
    }

    private function sincronizarEstadoRegistro(RegistroGes $registro): void
    {
        $hitos = $registro->hitos()->get(['estado']);

        if ($hitos->isEmpty()) {
            return;
        }

        $todosCompletados = $hitos->every(
            fn (Hito $hito): bool => strtolower((string) $hito->estado) === 'completado'
        );

        if ($todosCompletados && $registro->estado !== 'Completado') {
            $registro->update(['estado' => 'Completado']);
        } elseif (! $todosCompletados && $registro->estado === 'Completado') {
            $registro->update(['estado' => 'Asignado']);
        }
    }
}
