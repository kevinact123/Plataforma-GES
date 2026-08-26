<?php

namespace App\Services;

use App\Models\Hito;
use App\Models\HistorialRegistro;
use App\Models\RegistroGes;
use App\Models\User;
use App\Services\RegistroGesAuditService;
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

            if (!$responsable->activo) {
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

            return $hito->fresh(['registroGes', 'usuario']);
        });
    }

    public function consultar(User $usuarioActual, int $idRegistro): Collection
    {
        $registro = RegistroGes::query()->findOrFail($idRegistro);
        $this->autorizarRegistro($usuarioActual, $registro);

        return Hito::query()
            ->with(['usuario'])
            ->where('id_registro', $idRegistro)
            ->orderBy('id_hito')
            ->get();
    }

    public function pendientes(User $usuarioActual, int $idRegistro): Collection
    {
        $registro = RegistroGes::query()->findOrFail($idRegistro);
        $this->autorizarRegistro($usuarioActual, $registro);

        return Hito::query()
            ->with(['usuario'])
            ->where('id_registro', $idRegistro)
            ->whereIn('estado', ['pendiente', 'en_proceso'])
            ->orderBy('id_hito')
            ->get();
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

        return $base . PHP_EOL . $nueva;
    }

    private function autorizarRegistro(User $usuario, RegistroGes $registro): void
    {
        if ($usuario->cannot('view', $registro)) {
            abort(403, 'No tienes permiso para consultar los hitos de este registro.');
        }
    }

}
