<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroGesDocumento extends Model
{
    protected $table = 'registros_ges_documentos';

    protected $primaryKey = 'id_documento';

    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'id_registro',
        'nombre_original',
        'nombre_archivo',
        'ruta_archivo',
        'mime_type',
        'tamanio',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tamanio' => 'integer',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function registroGes(): BelongsTo
    {
        return $this->belongsTo(RegistroGes::class, 'id_registro', 'id_registro');
    }
}
