<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\RegistroGes;
use App\Models\User;
use App\Services\RegistroGesAuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AsignacionService
{
    public function asignar(User $usuarioActual, array $data): Asignacion
    {
        return DB::transaction(function () use ($usuarioActual, $data): Asignacion {
            $registro = RegistroGes::query()
                ->with(['patologia', 'prioridad'])
                ->lockForUpdate()
                ->findOrFail($data['id_registro']);

            $operador = User::query()
                ->with('permisosPatologia')
                ->findOrFail($data['id_usuario']);

            $this->validarAsignacion($usuarioActual, $registro, $operador);

            $asignacion = Asignacion::create([
                'id_registro' => $registro->id_registro,
                'id_usuario' => $operador->id_usuario,
                'asignado_por' => $usuarioActual->id_usuario,
                'fecha_asignacion' => now(),
                'fecha_inicio' => now(),
                'estado' => 'activa',
                'observacion' => $data['observacion'] ?? 'Asignación creada',
            ]);

            $registro->update(['estado' => 'Asignado']);

            app(RegistroGesAuditService::class)->registrar(
                $registro->id_registro,
                $usuarioActual->id_usuario,
                'asignacion',
                'estado',
                'Pendiente',
                'Asignado',
            );

            return $asignacion->fresh(['registroGes', 'usuario', 'asignador']);
        });
    }

    public function reasignar(User $usuarioActual, int $idAsignacion, array $data): Asignacion
    {
        return DB::transaction(function () use ($usuarioActual, $idAsignacion, $data): Asignacion {
            $asignacionActual = Asignacion::query()->lockForUpdate()->findOrFail($idAsignacion);

            if ($asignacionActual->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'asignacion' => ['Solo se puede reasignar una asignación activa.'],
                ]);
            }

            $registro = $asignacionActual->registroGes()->firstOrFail();

            $operadorDestino = User::query()->with('permisosPatologia')->findOrFail($data['id_usuario']);

            $this->validarReasignacion($usuarioActual, $registro, $operadorDestino, $asignacionActual);

            $asignacionActual->update([
                'fecha_finalizacion' => now(),
                'estado' => 'reasignada',
                'observacion' => trim(($asignacionActual->observacion ? $asignacionActual->observacion . PHP_EOL : '') . 'Reasignada el ' . now()->toDateTimeString()),
            ]);

            $nuevaAsignacion = Asignacion::create([
                'id_registro' => $registro->id_registro,
                'id_usuario' => $operadorDestino->id_usuario,
                'asignado_por' => $usuarioActual->id_usuario,
                'fecha_asignacion' => now(),
                'fecha_inicio' => now(),
                'estado' => 'activa',
                'observacion' => $data['observacion'] ?? 'Registro reasignado',
            ]);

            $registro->update(['estado' => 'Asignado']);

            app(RegistroGesAuditService::class)->registrar(
                $registro->id_registro,
                $usuarioActual->id_usuario,
                'reasignacion',
                'id_usuario',
                $asignacionActual->id_usuario,
                $operadorDestino->id_usuario,
            );

            app(RegistroGesAuditService::class)->registrar(
                $registro->id_registro,
                $usuarioActual->id_usuario,
                'cambio_estado',
                'estado',
                'Asignado',
                'Asignado',
            );

            return $nuevaAsignacion->fresh(['registroGes', 'usuario', 'asignador']);
        });
    }

    public function finalizar(User $usuarioActual, int $idAsignacion, array $data): Asignacion
    {
        return DB::transaction(function () use ($usuarioActual, $idAsignacion, $data): Asignacion {
            $asignacion = Asignacion::query()->with(['registroGes'])
                ->lockForUpdate()
                ->findOrFail($idAsignacion);

            if ($asignacion->estado !== 'activa') {
                throw ValidationException::withMessages([
                    'asignacion' => ['Solo se puede finalizar una asignación activa.'],
                ]);
            }

            $asignacion->update([
                'fecha_finalizacion' => now(),
                'estado' => 'finalizada',
                'observacion' => trim(($asignacion->observacion ? $asignacion->observacion . PHP_EOL : '') . ($data['observacion'] ?? 'Finalizada por la operación del sistema')),
            ]);

            $asignacion->registroGes()->update(['estado' => 'Pendiente']);

            app(RegistroGesAuditService::class)->registrar(
                $asignacion->id_registro,
                $usuarioActual?->id_usuario,
                'cambio_estado',
                'estado',
                'Asignado',
                'Pendiente',
            );

            return $asignacion->fresh(['registroGes', 'usuario', 'asignador']);
        });
    }

    public function cargaActualPorUsuario(User $usuario): array
    {
        $asignacionesActivas = Asignacion::query()
            ->where('id_usuario', $usuario->id_usuario)
            ->where('estado', 'activa')
            ->count();

        return [
            'data' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => trim($usuario->nombre . ' ' . $usuario->apellido),
                'total_activas' => $asignacionesActivas,
                'peso_actual' => $this->calcularCarga($usuario),
            ],
        ];
    }

    public function sugerirOperador(User $usuarioActual, array $data): array
    {
        $registro = RegistroGes::query()->with(['patologia', 'prioridad'])->findOrFail($data['id_registro']);

        if (!$usuarioActual->esAdmin() && !$usuarioActual->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_registro' => ['No tienes permisos para asignar registros de esta patología.'],
            ]);
        }

        $candidatos = User::query()
            ->where('activo', true)
            ->whereHas('permisosPatologia', function ($query) use ($registro): void {
                $query->where('id_patologia', $registro->id_patologia)
                    ->where('puede_asignar', true);
            })
            ->get();

        if ($candidatos->isEmpty()) {
            throw ValidationException::withMessages([
                'id_usuario' => ['No existen operadores con permisos para esta patología.'],
            ]);
        }

        $candidatos = $candidatos->map(function (User $operador) use ($registro, $data): array {
            $carga = $this->calcularCarga($operador);
            $trabajo = (int) ($data['cantidad_trabajo'] ?? 1);
            $prioridad = (int) ($data['prioridad'] ?? ($registro->prioridad?->nivel ?? 1));
            $dificultad = (int) ($data['dificultad'] ?? 1);
            $complejidad = (int) ($data['complejidad'] ?? 1);
            $disponibilidad = (int) ($data['disponibilidad'] ?? 3);

            $score = ($carga * 2.5)
                + ($trabajo * 1.4)
                + ($prioridad * 1.6)
                + ($dificultad * 1.3)
                + ($complejidad * 1.8)
                - ($disponibilidad * 1.5);

            return [
                'id_usuario' => $operador->id_usuario,
                'nombre' => trim($operador->nombre . ' ' . $operador->apellido),
                'score' => round((float) $score, 2),
                'carga_actual' => $carga,
                'cantidad_trabajo' => $trabajo,
                'prioridad' => $prioridad,
                'dificultad' => $dificultad,
                'complejidad' => $complejidad,
                'disponibilidad' => $disponibilidad,
            ];
        })->sortBy('score')->values();

        return [
            'id_registro' => $registro->id_registro,
            'patologia' => $registro->patologia?->nombre,
            'operador_recomendado' => $candidatos->first(),
            'candidatos' => $candidatos,
        ];
    }

    public function historialDeAsignaciones(int $idRegistro): Collection
    {
        return Asignacion::query()
            ->with(['usuario', 'asignador'])
            ->where('id_registro', $idRegistro)
            ->orderByDesc('fecha_asignacion')
            ->get();
    }

    private function validarAsignacion(
        User $usuarioActual,
        RegistroGes $registro,
        User $operador,
        ?int $idAsignacionExcluir = null,
    ): void
    {
        if (!$operador->activo) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El operador seleccionado no está activo.'],
            ]);
        }

        if (!$registro->patologia) {
            throw ValidationException::withMessages([
                'id_registro' => ['El registro no tiene una patología asociada.'],
            ]);
        }

        if (!$usuarioActual->esAdmin() && !$usuarioActual->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_registro' => ['No tienes permisos para asignar registros de esta patología.'],
            ]);
        }

        if (!$operador->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El operador no tiene permisos para asignar en esta patología.'],
            ]);
        }

        $asignacionActiva = Asignacion::query()
            ->where('id_registro', $registro->id_registro)
            ->where('estado', 'activa')
            ->when($idAsignacionExcluir !== null, function ($query) use ($idAsignacionExcluir): void {
                $query->whereKeyNot($idAsignacionExcluir);
            });

        if ($asignacionActiva->exists()) {
            throw ValidationException::withMessages([
                'id_registro' => ['Este registro ya tiene una asignación activa.'],
            ]);
        }

        $carga = $this->calcularCarga($operador);
        if ($carga >= 10) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El operador supera la carga máxima de trabajo.'],
            ]);
        }
    }

    private function validarReasignacion(User $usuarioActual, RegistroGes $registro, User $operador, Asignacion $asignacionActual): void
    {
        $this->validarAsignacion(
            $usuarioActual,
            $registro,
            $operador,
            $asignacionActual->id_asignacion,
        );
    }

    private function calcularCarga(User $operador): float
    {
        $cargaActual = Asignacion::query()
            ->where('id_usuario', $operador->id_usuario)
            ->where('estado', 'activa')
            ->count();

        $ponderacion = $this->obtenerPonderacionComplejidad($operador);

        return (float) ($cargaActual + $ponderacion);
    }

    private function obtenerPonderacionComplejidad(User $operador): float
    {
        if (!Schema::hasTable('complejidad_registro')) {
            return 1.0;
        }

        return (float) $operador->complejidadRegistros()->sum('puntaje') ?: 1.0;
    }
}
