<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipoRegistroResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id_tipo_registro' => $this->id_tipo_registro,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'activo' => $this->activo,
        ];
    }
}