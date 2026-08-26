<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class RegistroGesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_registro' => $this->id_registro,
            'id_paciente' => $this->id_paciente,
            'id_patologia' => $this->id_patologia,
            'id_prioridad' => $this->id_prioridad,
            'id_tipo_registro' => $this->id_tipo_registro,
            'tipo_tratamiento' => $this->tipo_tratamiento,
            'fecha_ingreso' => $this->fecha_ingreso?->toDateString(),
            'fecha_limite' => $this->fecha_limite?->toDateString(),
            'estado' => $this->estado,
            'observaciones' => $this->observaciones,
            'fecha_creacion' => $this->fecha_creacion?->toISOString(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toISOString(),
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'patologia' => new PatologiaResource($this->whenLoaded('patologia')),
            'prioridad' => new PrioridadResource($this->whenLoaded('prioridad')),
            'tipo_registro' => new TipoRegistroResource($this->whenLoaded('tipoRegistro')),
            'asignaciones' => AsignacionResource::collection($this->whenLoaded('asignaciones')),
            'documentos' => RegistroGesDocumentoResource::collection($this->whenLoaded('documentos')),
            'cantidad_documentos' => $this->cantidadDocumentos(),
        ];
    }

    private function cantidadDocumentos(): int
    {
        if ($this->relationLoaded('documentos')) {
            return $this->documentos->count();
        }

        if (!Schema::hasTable('registros_ges_documentos')) {
            return 0;
        }

        return $this->documentos()->count();
    }
}
