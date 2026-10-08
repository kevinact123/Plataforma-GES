<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Hito;
use App\Models\Paciente;
use App\Models\RegistroGes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardService
{
    public function resumen(User $user, ?string $mes = null): array
    {
        $visibles = $this->registrosVisibles($user, $mes);
        $totalPacientes = Paciente::query()
            ->where('activo', true)
            ->where(function ($query) use ($user): void {
                $query
                    ->whereDoesntHave('registrosGes')
                    ->orWhereHas('registrosGes', fn ($registroQuery) => $registroQuery->visibleTo($user));
            })
            ->count();
        $totalRegistros = (clone $visibles)->count();
        $pendientes = (clone $visibles)->where('estado', 'Pendiente')->count();
        $enProceso = (clone $visibles)->where('estado', 'Asignado')->count();
        $completados = (clone $visibles)
            ->where(function ($query): void {
                $query
                    ->where('estado', 'Completado')
                    ->orWhereHas('asignaciones', fn ($assignmentQuery) => $assignmentQuery->where('estado', 'finalizada'));
            })
            ->count();
        $sinAsignar = (clone $visibles)->whereDoesntHave('asignaciones')->count();

        return [
            'total_pacientes' => $totalPacientes,
            'total_registros' => $totalRegistros,
            'registros_pendientes' => $pendientes,
            'registros_en_proceso' => $enProceso,
            'registros_completados' => $completados,
            'registros_sin_asignar' => $sinAsignar,
        ];
    }

    public function distribuciones(User $user, ?string $mes = null): array
    {
        $prioridades = $this->registrosVisibles($user, $mes)
            ->join('prioridades', 'prioridades.id_prioridad', '=', 'registros_ges.id_prioridad')
            ->select('prioridades.nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('prioridades.id_prioridad', 'prioridades.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();

        $patologias = $this->registrosVisibles($user, $mes)
            ->join('patologias', 'patologias.id_patologia', '=', 'registros_ges.id_patologia')
            ->select('patologias.nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('patologias.id_patologia', 'patologias.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();

        $tipos = $this->registrosVisibles($user, $mes)
            ->join('tipos_registro', 'tipos_registro.id_tipo_registro', '=', 'registros_ges.id_tipo_registro')
            ->select('tipos_registro.nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('tipos_registro.id_tipo_registro', 'tipos_registro.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();

        return [
            'prioridades' => $prioridades,
            'patologias' => $patologias,
            'tipos_registro' => $tipos,
        ];
    }

    public function cargaOperadores(User $user): array
    {
        $usuarios = User::query()
            ->where('activo', true)
            ->get();

        return $usuarios
            ->map(function (User $usuario) use ($user): array {
                $totalActivas = Asignacion::query()
                    ->where('id_usuario', $usuario->id_usuario)
                    ->where('estado', 'activa')
                    ->when($user->hasRole('digitadora'), function ($query) use ($user): void {
                        $query->whereHas('registroGes', fn ($registroQuery) => $registroQuery->visibleTo($user));
                    })
                    ->count();

                return [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => trim($usuario->nombre.' '.$usuario->apellido),
                    'total_activas' => (int) $totalActivas,
                    'carga_ponderada' => round((float) $totalActivas, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function registrosPorOperador(User $user): array
    {
        return Asignacion::query()
            ->whereHas('registroGes', fn ($query) => $query->visibleTo($user))
            ->join('usuarios', 'usuarios.id_usuario', '=', 'asignaciones.id_usuario')
            ->select(
                'asignaciones.id_usuario',
                'usuarios.nombre as nombre_usuario',
                'usuarios.apellido as apellido_usuario',
                DB::raw('COUNT(*) as total_registros'),
            )
            ->groupBy('asignaciones.id_usuario', 'usuarios.nombre', 'usuarios.apellido')
            ->orderByDesc('total_registros')
            ->get()
            ->map(function ($row): array {
                return [
                    'id_usuario' => (int) $row->id_usuario,
                    'nombre' => trim($row->nombre_usuario.' '.$row->apellido_usuario),
                    'total_registros' => (int) $row->total_registros,
                ];
            })
            ->all();
    }

    public function hitos(User $user): array
    {
        $visibles = Hito::query()->visibleTo($user);
        $pendientes = (clone $visibles)->whereRaw('LOWER(estado) = ?', ['pendiente'])->count();
        $enProceso = (clone $visibles)->whereRaw('LOWER(estado) = ?', ['en_proceso'])->count();
        $completados = (clone $visibles)->whereRaw('LOWER(estado) = ?', ['completado'])->count();

        return [
            'pendientes' => $pendientes,
            'en_proceso' => $enProceso,
            'completados' => $completados,
        ];
    }

    public function complejidadPromedio(User $user, ?string $mes = null): array
    {
        if (! Schema::hasTable('complejidad_registro')) {
            return ['promedio' => 0.0];
        }

        $tiposVisibles = $this->registrosVisibles($user)
            ->select('registros_ges.id_tipo_registro')
            ->distinct()
            ->pluck('id_tipo_registro')
            ->all();

        if (empty($tiposVisibles)) {
            return ['promedio' => 0.0];
        }

        $promedio = DB::table('complejidad_registro')
            ->whereIn('id_tipo_registro', $tiposVisibles)
            ->when($mes !== null, function ($query) use ($mes): void {
                [$inicio, $fin] = $this->rangoMes($mes);
                $query->whereBetween('fecha_evaluacion', [$inicio, $fin]);
            })
            ->selectRaw('COALESCE(AVG(puntaje), 0) as promedio')
            ->value('promedio');

        return [
            'promedio' => round((float) $promedio, 2),
        ];
    }

    private function registrosVisibles(User $user, ?string $mes = null): Builder
    {
        return RegistroGes::query()
            ->visibleTo($user)
            ->when($mes !== null, function (Builder $query) use ($mes): void {
                [$inicio, $fin] = $this->rangoMes($mes);
                $query->where(function (Builder $fecha) use ($inicio, $fin): void {
                    $fecha->whereBetween('registros_ges.fecha_ingreso', [substr($inicio, 0, 10), substr($fin, 0, 10)])
                        ->orWhere(function (Builder $sinIngreso) use ($inicio, $fin): void {
                            $sinIngreso->whereNull('registros_ges.fecha_ingreso')
                                ->whereBetween('registros_ges.fecha_creacion', [$inicio, $fin]);
                        });
                });
            });
    }

    /** @return array{0: string, 1: string} */
    private function rangoMes(string $mes): array
    {
        $inicio = Carbon::createFromFormat('!Y-m', $mes)->startOfMonth();

        return [$inicio->toDateTimeString(), $inicio->copy()->endOfMonth()->toDateTimeString()];
    }
}
