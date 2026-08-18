<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplejidadRegistro extends Model
{
    protected $table = 'complejidad_registro';

    protected $primaryKey = 'id_complejidad';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'puntaje' => 'integer',
            'fecha_evaluacion' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function tipoRegistro(): BelongsTo
    {
        return $this->belongsTo(TipoRegistro::class, 'id_tipo_registro', 'id_tipo_registro');
    }
}
