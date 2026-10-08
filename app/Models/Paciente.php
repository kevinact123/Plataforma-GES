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
        'hora_cierre_dau',
        'sexo',
        'prevision',
        'tipo_fonasa',
        'numero_dau',
        'establecimiento_emision',
        'establecimiento_destino',
        'domicilio',
        'atencion',
        'consultorio',
        'reporte',
        'ges',
        'desc_cie10',
        'forma_pago',
        'servicio_egreso',
        'ingreso',
        'edad',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'hora_cierre_dau' => 'datetime',
            'ingreso' => 'datetime',
            'edad' => 'integer',
            'activo' => 'boolean',
            'fecha_registro' => 'datetime',
        ];
    }

    public function registrosGes(): HasMany
    {
        return $this->hasMany(RegistroGes::class, 'id_paciente', 'id_paciente');
    }

    public function documentosGenerales(): HasMany
    {
        return $this->hasMany(DocumentoGeneral::class, 'id_paciente', 'id_paciente');
    }
}
