<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrioridadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_prioridad' => $this->id_prioridad,
            'nombre' => $this->nombre,
            'nivel' => $this->nivel,
            'descripcion' => $this->descripcion,
        ];
    }
}