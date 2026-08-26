<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatologiaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_patologia' => $this->id_patologia,
            'numero_ges' => $this->numero_ges,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'confidencial' => $this->confidencial,
            'activo' => $this->activo,
            'registros' => RegistroGesResource::collection($this->whenLoaded('registrosGes')),
        ];
    }
}