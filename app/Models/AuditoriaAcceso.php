<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditoriaAcceso extends Model
{
    protected $table = 'auditoria_accesos';

    protected $primaryKey = 'id_auditoria';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'nombre_usuario',
        'tipo_documento',
        'fecha_hora_entrada',
        'fecha_hora_salida',
        'ip',
        'estado',
        'motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora_entrada' => 'datetime',
            'fecha_hora_salida' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
