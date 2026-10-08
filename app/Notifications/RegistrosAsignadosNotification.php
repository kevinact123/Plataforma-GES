<?php

namespace App\Notifications;

use App\Models\Asignacion;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class RegistrosAsignadosNotification extends Notification
{
    /** @var array<int, array<string, mixed>> */
    private array $registros;

    /** @param  Collection<int, Asignacion>  $asignaciones */
    public function __construct(Collection $asignaciones, private readonly string $origen)
    {
        $this->registros = $asignaciones
            ->map(fn (Asignacion $asignacion): array => $this->describir($asignacion))
            ->values()
            ->all();
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $cantidad = count($this->registros);

        return [
            'titulo' => $cantidad === 1 ? 'Se te asignó un registro GES' : "Se te asignaron {$cantidad} registros GES",
            'origen' => $this->origen,
            'cantidad' => $cantidad,
            'registros' => $this->registros,
        ];
    }

    private function describir(Asignacion $asignacion): array
    {
        $registro = $asignacion->registroGes()->with('patologia')->first();
        $paciente = $registro && Schema::hasTable('pacientes') ? $registro->paciente : null;

        return [
            'id_registro' => $asignacion->id_registro,
            'id_asignacion' => $asignacion->id_asignacion,
            'paciente' => $paciente ? trim($paciente->nombre.' '.$paciente->apellido_paterno) : null,
            'patologia' => $registro?->patologia
                ? trim($registro->patologia->numero_ges.' - '.$registro->patologia->nombre, ' -')
                : null,
        ];
    }
}
