<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialRegistro extends Model
{
    protected $table = 'historial_registros';

    protected $primaryKey = 'id_historial';

    public $timestamps = false;

    protected $fillable = [
        'id_registro',
        'id_usuario',
        'accion',
        'campo_modificado',
        'valor_anterior',
        'valor_nuevo',
        'fecha',
        'ip',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    public function registroGes(): BelongsTo
    {
        return $this->belongsTo(RegistroGes::class, 'id_registro', 'id_registro');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }
}
