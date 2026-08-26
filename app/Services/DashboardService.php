<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\Hito;
use App\Models\Paciente;
use App\Models\RegistroGes;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function resumen(User $user): array
    {
        $visibles = $this->registrosVisibles($user);
        $totalPacientes = Paciente::query()->whereHas('registrosGes', fn ($query) => $query->visibleTo($user))->count();
        $totalRegistros = (clone $visibles)->count();
        $pendientes = (clone $visibles)->where('estado', 'Pendiente')->count();
        $enProceso = (clone $visibles)->where('estado', 'Asignado')->count();
        $completados = Asignacion::query()->where('estado', 'finalizada')->whereHas('registroGes', fn ($query) => $query->visibleTo($user))->distinct('id_registro')->count('id_registro');
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

    public function distribuciones(User $user): array
    {
        $prioridades = $this->registrosVisibles($user)
            ->join('prioridades', 'prioridades.id_prioridad', '=', 'registros_ges.id_prioridad')
            ->select('prioridades.nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('prioridades.id_prioridad', 'prioridades.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();

        $patologias = $this->registrosVisibles($user)
            ->join('patologias', 'patologias.id_patologia', '=', 'registros_ges.id_patologia')
            ->select('patologias.nombre as label', DB::raw('COUNT(*) as total'))
            ->groupBy('patologias.id_patologia', 'patologias.nombre')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'total' => (int) $row->total])
            ->all();

        $tipos = $this->registrosVisibles($user)
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
        return User::query()
            ->where('activo', true)
            ->withCount(['asignaciones as total_activas' => fn ($query) => $query
                ->where('estado', 'activa')
                ->whereHas('registroGes', fn ($registroQuery) => $registroQuery->visibleTo($user))])
            ->get()
            ->map(function (User $usuario): array {
                return [
                    'id_usuario' => $usuario->id_usuario,
                    'nombre' => trim($usuario->nombre . ' ' . $usuario->apellido),
                    'total_activas' => (int) $usuario->total_activas,
                    'carga_ponderada' => round((float) $usuario->total_activas, 2),
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
                    'nombre' => trim($row->nombre_usuario . ' ' . $row->apellido_usuario),
                    'total_registros' => (int) $row->total_registros,
                ];
            })
            ->all();
    }

    public function hitos(User $user): array
    {
        $visibles = fn ($query) => $query->whereHas('registroGes', fn ($registroQuery) => $registroQuery->visibleTo($user));
        $pendientes = Hito::query()->whereIn('estado', ['pendiente', 'en_proceso'])->where($visibles)->count();
        $completados = Hito::query()->where('estado', 'completado')->where($visibles)->count();

        return [
            'pendientes' => $pendientes,
            'completados' => $completados,
        ];
    }

    public function complejidadPromedio(User $user): array
    {
        $tiposVisibles = $this->registrosVisibles($user)->select('id_tipo_registro')->distinct();
        $promedio = DB::table('complejidad_registro')->whereIn('id_tipo_registro', $tiposVisibles)->avg('puntaje');

        return [
            'promedio' => $promedio ? round((float) $promedio, 2) : 0.0,
        ];
    }

    private function registrosVisibles(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return RegistroGes::query()->visibleTo($user);
    }
}
