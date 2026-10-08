<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistroGesPatologiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_registro_patologia' => $this->id_registro_patologia,
            'id_registro' => $this->id_registro,
            'id_patologia' => $this->id_patologia,
            'tipo' => $this->tipo,
            'observacion' => $this->observacion,
            'fecha_creacion' => $this->fecha_creacion?->toISOString(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toISOString(),
            'patologia' => new PatologiaResource($this->whenLoaded('patologia')),
        ];
    }
}
