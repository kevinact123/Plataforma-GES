<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AsignacionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_asignacion' => $this->id_asignacion,
            'id_registro' => $this->id_registro,
            'id_usuario' => $this->id_usuario,
            'asignado_por' => $this->asignado_por,
            'fecha_asignacion' => $this->fecha_asignacion?->toISOString(),
            'fecha_inicio' => $this->fecha_inicio?->toISOString(),
            'fecha_finalizacion' => $this->fecha_finalizacion?->toISOString(),
            'estado' => $this->estado,
            'observacion' => $this->observacion,
        ];
    }
}