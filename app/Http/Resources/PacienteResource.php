<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PacienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_paciente' => $this->id_paciente,
            'rut' => $this->rut,
            'nombre' => $this->nombre,
            'apellido_paterno' => $this->apellido_paterno,
            'apellido_materno' => $this->apellido_materno,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'sexo' => $this->sexo,
            'activo' => $this->activo,
            'fecha_registro' => $this->fecha_registro?->toISOString(),
            'registros_ges' => RegistroGesResource::collection($this->whenLoaded('registrosGes')),
        ];
    }
}