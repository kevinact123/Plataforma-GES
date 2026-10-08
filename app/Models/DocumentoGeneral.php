<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoGeneral extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_REVISADO = 'revisado';

    public const ESTADO_APROBADO = 'aprobado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_REVISADO,
        self::ESTADO_APROBADO,
    ];

    public const ESTADO_ASIGNACION_SIN_ASIGNAR = 'sin_asignar';

    public const ESTADO_ASIGNACION_ACTIVA = 'activa';

    public const ESTADO_ASIGNACION_FINALIZADA = 'finalizada';

    protected $table = 'documentos_generales';

    protected $primaryKey = 'id_documento';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'id_usuario',
        'id_categoria',
        'nombre',
        'descripcion',
        'etiquetas',
        'estado',
        'id_paciente',
        'id_registro',
        'id_usuario_revisor',
        'fecha_revision',
        'nombre_original',
        'nombre_archivo',
        'ruta_archivo',
        'mime_type',
        'tamanio',
        'id_usuario_asignado',
        'asignado_por',
        'fecha_asignacion',
        'estado_asignacion',
    ];

    protected $hidden = [
        'ruta_archivo',
        'nombre_archivo',
    ];

    protected function casts(): array
    {
        return [
            'tamanio' => 'integer',
            'etiquetas' => 'array',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
            'fecha_revision' => 'datetime',
            'fecha_asignacion' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaDocumento::class, 'id_categoria', 'id_categoria');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente', 'id_paciente');
    }

    public function registroGes(): BelongsTo
    {
        return $this->belongsTo(RegistroGes::class, 'id_registro', 'id_registro');
    }

    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_revisor', 'id_usuario');
    }

    public function usuarioAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario_asignado', 'id_usuario');
    }

    public function asignador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_por', 'id_usuario');
    }
}
