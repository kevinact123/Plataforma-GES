<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Schema;

class RegistroGesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $registro = $this->resource;

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
            'puede_ver' => $user ? $user->can('view', $registro) : false,
            'puede_editar' => $user ? $user->can('update', $registro) : false,
            'puede_eliminar' => $user ? $user->can('delete', $registro) : false,
            'paciente' => new PacienteResource($this->whenLoaded('paciente')),
            'patologia' => new PatologiaResource($this->whenLoaded('patologia')),
            'patologias_asociadas' => $this->when(
                $this->relationLoaded('asociacionesPatologia'),
                fn () => RegistroGesPatologiaResource::collection(
                    $this->asociacionesPatologia->filter(
                        fn ($asociacion) => $asociacion->patologia
                            && ($user?->puedeVerPatologia($asociacion->patologia) ?? false),
                    ),
                ),
            ),
            'prioridad' => new PrioridadResource($this->whenLoaded('prioridad')),
            'tipo_registro' => new TipoRegistroResource($this->whenLoaded('tipoRegistro')),
            'asignaciones' => AsignacionResource::collection($this->whenLoaded('asignaciones')),
            'documentos' => RegistroGesDocumentoResource::collection($this->whenLoaded('documentos')),
            'documentos_generales' => $this->whenLoaded('documentosGenerales'),
            'cantidad_documentos' => $this->cantidadDocumentos(),
        ];
    }

    private function cantidadDocumentos(): int
    {
        $registroDocuments = $this->relationLoaded('documentos')
            ? $this->documentos->count()
            : (Schema::hasTable('registros_ges_documentos') ? $this->documentos()->count() : 0);
        $generalDocuments = $this->relationLoaded('documentosGenerales')
            ? $this->documentosGenerales->count()
            : (Schema::hasTable('documentos_generales') ? $this->documentosGenerales()->count() : 0);

        return $registroDocuments + $generalDocuments;
    }
}
