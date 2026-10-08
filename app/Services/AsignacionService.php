<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\DocumentoGeneral;
use App\Models\Paciente;
use App\Models\Patologia;
use App\Models\RegistroGes;
use App\Models\User;
use App\Notifications\RegistrosAsignadosNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AsignacionService
{
    public function asignar(User $usuarioActual, array $data): Asignacion
    {
        $this->exigirPermiso($usuarioActual, 'asignar_pacientes');

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

            $this->notificar($asignacion, $operador, 'manual');

            return $asignacion->fresh(['registroGes', 'usuario', 'asignador']);
        });
    }

    public function reasignar(User $usuarioActual, int $idAsignacion, array $data): Asignacion
    {
        $this->exigirPermiso($usuarioActual, 'reasignar_pacientes');

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
                'observacion' => trim(($asignacionActual->observacion ? $asignacionActual->observacion.PHP_EOL : '').'Reasignada el '.now()->toDateTimeString()),
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

            $this->notificar($nuevaAsignacion, $operadorDestino, 'reasignacion');

            return $nuevaAsignacion->fresh(['registroGes', 'usuario', 'asignador']);
        });
    }

    public function finalizar(User $usuarioActual, int $idAsignacion, array $data): Asignacion
    {
        $this->exigirPermiso($usuarioActual, 'reasignar_pacientes');

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
                'observacion' => trim(($asignacion->observacion ? $asignacion->observacion.PHP_EOL : '').($data['observacion'] ?? 'Finalizada por la operación del sistema')),
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
        $asignacionesActivas = Schema::hasTable('asignaciones')
            ? Asignacion::query()
                ->where('id_usuario', $usuario->id_usuario)
                ->where('estado', 'activa')
                ->count()
            : 0;

        return [
            'data' => [
                'id_usuario' => $usuario->id_usuario,
                'nombre' => trim($usuario->nombre.' '.$usuario->apellido),
                'total_activas' => $asignacionesActivas,
                'peso_actual' => $this->calcularCarga($usuario),
            ],
        ];
    }

    public function sugerirOperador(User $usuarioActual, array $data): array
    {
        $this->exigirPermiso($usuarioActual, 'asignar_pacientes');

        $registro = RegistroGes::query()->with(['patologia', 'prioridad'])->findOrFail($data['id_registro']);

        if (! $registro->patologia) {
            throw ValidationException::withMessages([
                'id_registro' => ['El registro no tiene una patología asociada.'],
            ]);
        }

        if (! $usuarioActual->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_registro' => ['No tienes permisos para asignar registros de esta patología.'],
            ]);
        }

        $candidatos = User::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['digitadora']))
            ->withAssignmentAccessTo($registro->patologia)
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

            return [
                'id_usuario' => $operador->id_usuario,
                'nombre' => trim($operador->nombre.' '.$operador->apellido),
                'score' => $carga,
                'carga_actual' => $carga,
                'cantidad_trabajo' => $trabajo,
                'prioridad' => $prioridad,
                'dificultad' => $dificultad,
                'complejidad' => $complejidad,
                'disponibilidad' => $disponibilidad,
            ];
        })->sortBy(fn (array $candidate) => [$candidate['carga_actual'], $candidate['id_usuario']])->values();

        return [
            'id_registro' => $registro->id_registro,
            'patologia' => $registro->patologia?->nombre,
            'operador_recomendado' => $candidatos->first(),
            'candidatos' => $candidatos,
        ];
    }

    public function asignarAutomaticamenteRegistro(User $usuarioActual, int $idRegistro): Asignacion
    {
        $this->exigirPermiso($usuarioActual, 'asignar_pacientes');

        return DB::transaction(function () use ($usuarioActual, $idRegistro): Asignacion {
            $registro = RegistroGes::query()
                ->with(['patologia', 'prioridad'])
                ->lockForUpdate()
                ->findOrFail($idRegistro);

            $this->validarActorPatologia($usuarioActual, $registro);
            $actual = $registro->asignaciones()->where('estado', 'activa')->first();
            if ($actual) {
                return $actual->fresh(['registroGes', 'usuario', 'asignador']);
            }

            $operador = $this->seleccionarOperador($registro->patologia);
            $this->validarAsignacion($usuarioActual, $registro, $operador);

            return $this->crearAsignacion($usuarioActual, $registro, $operador, 'Asignación automática por menor carga activa');
        });
    }

    public function asignarAutomaticamentePaciente(User $usuarioActual, int $idPaciente, ?int $idRegistro = null): Asignacion
    {
        $this->exigirPermiso($usuarioActual, 'asignar_pacientes');

        $paciente = Paciente::query()->findOrFail($idPaciente);
        if ($usuarioActual->cannot('view', $paciente)) {
            throw ValidationException::withMessages(['id_paciente' => ['No tienes permiso para este paciente.']]);
        }

        $query = $paciente->registrosGes()
            ->visibleTo($usuarioActual)
            ->whereDoesntHave('asignaciones', fn ($assignmentQuery) => $assignmentQuery->where('estado', 'activa'));

        if ($idRegistro !== null) {
            $registro = $query->whereKey($idRegistro)->first();
            if (! $registro) {
                throw ValidationException::withMessages(['id_registro' => ['El registro seleccionado no pertenece al paciente o ya está asignado.']]);
            }
        } else {
            $registros = $query->get();
            if ($registros->count() !== 1) {
                throw ValidationException::withMessages([
                    'id_registro' => [$registros->isEmpty() ? 'El paciente no tiene registros GES pendientes de asignación.' : 'Selecciona explícitamente el registro cuando el paciente tiene varias patologías.'],
                ]);
            }
            $registro = $registros->first();
        }

        return $this->asignarAutomaticamenteRegistro($usuarioActual, $registro->id_registro);
    }

    public function asignarAutomaticamenteDocumento(User $usuarioActual, int $idDocumento, ?int $idRegistro = null): DocumentoGeneral
    {
        $this->exigirPermiso($usuarioActual, 'asignar_pacientes');

        return DB::transaction(function () use ($usuarioActual, $idDocumento, $idRegistro): DocumentoGeneral {
            if (! Schema::hasColumn('documentos_generales', 'id_usuario_asignado')) {
                throw ValidationException::withMessages(['documento' => ['La asignación de documentos no está habilitada en la base de datos.']]);
            }

            $documento = DocumentoGeneral::query()->lockForUpdate()->findOrFail($idDocumento);
            $registro = $this->resolverRegistroDocumento($usuarioActual, $documento, $idRegistro);
            $this->validarActorPatologia($usuarioActual, $registro);

            if ($documento->estado_asignacion === DocumentoGeneral::ESTADO_ASIGNACION_ACTIVA && $documento->id_usuario_asignado) {
                return $documento->fresh(['usuarioAsignado', 'asignador', 'registroGes']);
            }

            $operador = $this->seleccionarOperador($registro->patologia);
            $assignmentData = [
                'id_usuario_asignado' => $operador->id_usuario,
                'asignado_por' => $usuarioActual->id_usuario,
                'fecha_asignacion' => now(),
                'estado_asignacion' => DocumentoGeneral::ESTADO_ASIGNACION_ACTIVA,
            ];
            if (! $documento->id_registro) {
                $assignmentData['id_registro'] = $registro->id_registro;
            }
            $documento->update($assignmentData);

            return $documento->fresh(['usuarioAsignado', 'asignador', 'registroGes']);
        });
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
    ): void {
        if (! $operador->activo) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El operador seleccionado no está activo.'],
            ]);
        }

        if (! $operador->hasRole('digitadora')) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El usuario seleccionado no es una digitadora.'],
            ]);
        }

        if (! $registro->patologia) {
            throw ValidationException::withMessages([
                'id_registro' => ['El registro no tiene una patología asociada.'],
            ]);
        }

        if (! $usuarioActual->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_registro' => ['No tienes permisos para asignar registros de esta patología.'],
            ]);
        }

        if (! $operador->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages([
                'id_usuario' => ['El operador no tiene permisos para asignar en esta patología.'],
            ]);
        }

        $asignacionActiva = Schema::hasTable('asignaciones')
            ? Asignacion::query()
                ->where('id_registro', $registro->id_registro)
                ->where('estado', 'activa')
                ->when($idAsignacionExcluir !== null, function ($query) use ($idAsignacionExcluir): void {
                    $query->whereKeyNot($idAsignacionExcluir);
                })
            : null;

        if ($asignacionActiva?->exists()) {
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
        $cargaActual = Schema::hasTable('asignaciones')
            ? Asignacion::query()
                ->where('id_usuario', $operador->id_usuario)
                ->where('estado', 'activa')
                ->count()
            : 0;

        $documentosActivos = Schema::hasColumn('documentos_generales', 'id_usuario_asignado')
            ? DocumentoGeneral::query()
                ->where('id_usuario_asignado', $operador->id_usuario)
                ->where('estado_asignacion', DocumentoGeneral::ESTADO_ASIGNACION_ACTIVA)
                ->count()
            : 0;

        return (float) ($cargaActual + $documentosActivos);
    }

    private function seleccionarOperador(?Patologia $patologia): User
    {
        if (! $patologia) {
            throw ValidationException::withMessages(['id_registro' => ['El registro no tiene una patología asociada.']]);
        }

        $operador = User::query()
            ->where('activo', true)
            ->whereHas('rol', fn ($query) => $query->whereRaw('LOWER(nombre) = ?', ['digitadora']))
            ->withAssignmentAccessTo($patologia)
            ->get()
            ->map(fn (User $user): array => ['user' => $user, 'carga' => $this->calcularCarga($user)])
            ->filter(fn (array $candidate): bool => $candidate['carga'] < 10)
            ->sort(fn (array $left, array $right): int => [$left['carga'], $left['user']->id_usuario] <=> [$right['carga'], $right['user']->id_usuario])
            ->first();

        if (! $operador) {
            throw ValidationException::withMessages(['id_usuario' => ['No existen digitadoras activas con permiso y carga disponible para esta patología.']]);
        }

        return $operador['user'];
    }

    private function crearAsignacion(User $usuarioActual, RegistroGes $registro, User $operador, string $observacion): Asignacion
    {
        $asignacion = Asignacion::create([
            'id_registro' => $registro->id_registro,
            'id_usuario' => $operador->id_usuario,
            'asignado_por' => $usuarioActual->id_usuario,
            'fecha_asignacion' => now(),
            'fecha_inicio' => now(),
            'estado' => 'activa',
            'observacion' => $observacion,
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

        $this->notificar($asignacion, $operador, str_contains($observacion, 'automática') ? 'automatica' : 'manual');

        return $asignacion->fresh(['registroGes', 'usuario', 'asignador']);
    }

    /** @var array<int, array{operador: User, asignaciones: array<int, Asignacion>}>|null */
    private ?array $lote = null;

    public function iniciarLoteNotificaciones(): void
    {
        $this->lote = [];
    }

    public function enviarLoteNotificaciones(): void
    {
        $lote = $this->lote ?? [];
        $this->lote = null;

        foreach ($lote as $item) {
            $item['operador']->notify(new RegistrosAsignadosNotification(collect($item['asignaciones']), 'automatica'));
        }
    }

    private function notificar(Asignacion $asignacion, User $operador, string $origen): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        if ($this->lote !== null) {
            $this->lote[$operador->id_usuario]['operador'] = $operador;
            $this->lote[$operador->id_usuario]['asignaciones'][] = $asignacion;

            return;
        }

        $operador->notify(new RegistrosAsignadosNotification(collect([$asignacion]), $origen));
    }

    private function validarActorPatologia(User $usuarioActual, RegistroGes $registro): void
    {
        if (! $registro->patologia || ! $usuarioActual->puedeAsignarPatologia($registro->patologia)) {
            throw ValidationException::withMessages(['id_registro' => ['No tienes permisos para asignar esta patología.']]);
        }
    }

    private function exigirPermiso(User $user, string $permission): void
    {
        if (! $user->hasPermission($permission)) {
            throw ValidationException::withMessages([
                'permiso' => ['No tienes permiso para realizar esta acción.'],
            ]);
        }
    }

    private function resolverRegistroDocumento(User $usuarioActual, DocumentoGeneral $documento, ?int $idRegistro): RegistroGes
    {
        if ($documento->id_registro && $idRegistro && (int) $documento->id_registro !== (int) $idRegistro) {
            throw ValidationException::withMessages(['id_registro' => ['El documento ya está asociado a otro registro GES.']]);
        }
        $registroId = $idRegistro ?? $documento->id_registro;
        if (! $registroId && $documento->id_paciente) {
            $registros = RegistroGes::query()->where('id_paciente', $documento->id_paciente)->visibleTo($usuarioActual)->get();
            if ($registros->count() !== 1) {
                throw ValidationException::withMessages(['id_registro' => [$registros->isEmpty() ? 'El documento no tiene un registro GES asociado con patología.' : 'Selecciona el registro GES del documento para resolver su patología.']]);
            }
            $registroId = $registros->first()->id_registro;
        }

        if (! $registroId) {
            throw ValidationException::withMessages(['documento' => ['Los documentos sin paciente o registro no pueden asignarse automáticamente.']]);
        }

        $registro = RegistroGes::query()->with('patologia')->findOrFail($registroId);
        if ($documento->id_paciente && (int) $documento->id_paciente !== (int) $registro->id_paciente) {
            throw ValidationException::withMessages(['id_registro' => ['El registro no corresponde al paciente del documento.']]);
        }
        if ($usuarioActual->cannot('view', $registro)) {
            throw ValidationException::withMessages(['documento' => ['No tienes permiso para consultar el registro del documento.']]);
        }

        return $registro;
    }

    private function obtenerPonderacionComplejidad(User $operador): float
    {
        if (! Schema::hasTable('complejidad_registro')) {
            return 1.0;
        }

        return (float) $operador->complejidadRegistros()->sum('puntaje') ?: 1.0;
    }
}
