<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroGes extends Model
{
    protected $table = 'registros_ges';

    protected $primaryKey = 'id_registro';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_limite' => 'date',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente', 'id_paciente');
    }

    public function patologia(): BelongsTo
    {
        return $this->belongsTo(Patologia::class, 'id_patologia', 'id_patologia');
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'id_prioridad', 'id_prioridad');
    }

    public function tipoRegistro(): BelongsTo
    {
        return $this->belongsTo(TipoRegistro::class, 'id_tipo_registro', 'id_tipo_registro');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'id_registro', 'id_registro');
    }

    public function hitos(): HasMany
    {
        return $this->hasMany(Hito::class, 'id_registro', 'id_registro');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialRegistro::class, 'id_registro', 'id_registro');
    }
}
