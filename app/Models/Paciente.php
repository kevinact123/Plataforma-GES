<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paciente extends Model
{
    protected $table = 'pacientes';

    protected $primaryKey = 'id_paciente';

    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $fillable = [
        'rut',
        'nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'sexo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo' => 'boolean',
            'fecha_registro' => 'datetime',
        ];
    }

    public function registrosGes(): HasMany
    {
        return $this->hasMany(RegistroGes::class, 'id_paciente', 'id_paciente');
    }
}
