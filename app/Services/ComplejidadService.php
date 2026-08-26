<?php

namespace App\Services;

use App\Models\Asignacion;
use App\Models\ComplejidadRegistro;
use App\Models\RegistroGes;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ComplejidadService
{
    public function consultar(User $user): array
    {
        $items = ComplejidadRegistro::query()
            ->when(! $user->esAdmin(), fn ($query) => $query->whereIn('id_tipo_registro', $this->tiposVisibles($user)))
            ->with(['usuario', 'tipoRegistro'])
            ->orderByDesc('fecha_evaluacion')
            ->get();

        return [
            'data' => $items->map(function (ComplejidadRegistro $item): array {
                return [
                    'id_complejidad' => $item->id_complejidad,
                    'id_usuario' => $item->id_usuario,
                    'usuario' => $item->usuario ? trim($item->usuario->nombre . ' ' . $item->usuario->apellido) : null,
                    'id_tipo_registro' => $item->id_tipo_registro,
                    'tipo_registro' => $item->tipoRegistro?->nombre,
                    'puntaje' => (int) $item->puntaje,
                    'observacion' => $item->observacion,
                    'fecha_evaluacion' => $item->fecha_evaluacion?->toDateTimeString(),
                ];
            })->values()->all(),
        ];
    }

    public function promedioPorTipo(User $user): array
    {
        $rows = ComplejidadRegistro::query()
            ->when(! $user->esAdmin(), fn ($query) => $query->whereIn('complejidad_registro.id_tipo_registro', $this->tiposVisibles($user)))
            ->join('tipos_registro', 'tipos_registro.id_tipo_registro', '=', 'complejidad_registro.id_tipo_registro')
            ->select(
                'tipos_registro.id_tipo_registro',
                'tipos_registro.nombre as nombre',
                DB::raw('AVG(complejidad_registro.puntaje) as promedio'),
                DB::raw('COUNT(*) as total_evaluaciones'),
            )
            ->groupBy('tipos_registro.id_tipo_registro', 'tipos_registro.nombre')
            ->orderByDesc('promedio')
            ->get();

        return [
            'data' => $rows->map(function ($row): array {
                return [
                    'id_tipo_registro' => (int) $row->id_tipo_registro,
                    'nombre' => $row->nombre,
                    'promedio' => round((float) $row->promedio, 2),
                    'total_evaluaciones' => (int) $row->total_evaluaciones,
                ];
            })->values()->all(),
        ];
    }

    public function porOperador(User $user): array
    {
        $rows = ComplejidadRegistro::query()
            ->when(! $user->esAdmin(), fn ($query) => $query->whereIn('complejidad_registro.id_tipo_registro', $this->tiposVisibles($user)))
            ->join('usuarios', 'usuarios.id_usuario', '=', 'complejidad_registro.id_usuario')
            ->select(
                'usuarios.id_usuario',
                'usuarios.nombre as nombre_usuario',
                'usuarios.apellido as apellido_usuario',
                DB::raw('AVG(complejidad_registro.puntaje) as promedio'),
                DB::raw('SUM(complejidad_registro.puntaje) as total_puntaje'),
                DB::raw('COUNT(*) as total_evaluaciones'),
            )
            ->groupBy('usuarios.id_usuario', 'usuarios.nombre', 'usuarios.apellido')
            ->orderByDesc('promedio')
            ->get();

        return [
            'data' => $rows->map(function ($row) use ($user): array {
                    $totalActivas = Asignacion::query()
                    ->where('id_usuario', $row->id_usuario)
                    ->where('estado', 'activa')
                        ->whereHas('registroGes', fn ($query) => $query->visibleTo($user))
                    ->count();

                return [
                    'id_usuario' => (int) $row->id_usuario,
                    'nombre' => trim($row->nombre_usuario . ' ' . $row->apellido_usuario),
                    'promedio' => round((float) $row->promedio, 2),
                    'total_puntaje' => (int) $row->total_puntaje,
                    'total_evaluaciones' => (int) $row->total_evaluaciones,
                    'carga_actual' => $totalActivas,
                    'carga_ponderada' => round((float) $row->total_puntaje + $totalActivas, 2),
                ];
            })->values()->all(),
        ];
    }

    public function porPatologia(User $user): array
    {
        $rows = DB::table('registros_ges as rg')
            ->when(! $user->esAdmin(), fn ($query) => $query->whereIn('rg.id_registro', RegistroGes::query()->visibleTo($user)->select('id_registro')))
            ->join('patologias as p', 'p.id_patologia', '=', 'rg.id_patologia')
            ->join('complejidad_registro as cr', 'cr.id_tipo_registro', '=', 'rg.id_tipo_registro')
            ->select(
                'p.id_patologia',
                'p.nombre',
                DB::raw('AVG(cr.puntaje) as promedio'),
                DB::raw('COUNT(DISTINCT rg.id_registro) as total_registros'),
            )
            ->groupBy('p.id_patologia', 'p.nombre')
            ->orderByDesc('promedio')
            ->get();

        return [
            'data' => $rows->map(function ($row): array {
                return [
                    'id_patologia' => (int) $row->id_patologia,
                    'nombre' => $row->nombre,
                    'promedio' => round((float) $row->promedio, 2),
                    'total_registros' => (int) $row->total_registros,
                ];
            })->values()->all(),
        ];
    }

    private function tiposVisibles(User $user): \Illuminate\Database\Eloquent\Builder
    {
        return RegistroGes::query()->visibleTo($user)->select('id_tipo_registro')->distinct();
    }
}