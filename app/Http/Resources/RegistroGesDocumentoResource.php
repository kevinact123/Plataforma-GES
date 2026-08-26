<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistroGesDocumentoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_documento' => $this->id_documento,
            'id_registro' => $this->id_registro,
            'nombre_original' => $this->nombre_original,
            'nombre_archivo' => $this->nombre_archivo,
            'ruta_archivo' => $this->ruta_archivo,
            'mime_type' => $this->mime_type,
            'tamanio' => $this->tamanio,
            'observaciones' => $this->observaciones,
            'fecha_creacion' => $this->fecha_creacion?->toISOString(),
            'fecha_actualizacion' => $this->fecha_actualizacion?->toISOString(),
        ];
    }
}
