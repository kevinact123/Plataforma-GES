<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoRegistro extends Model
{
    protected $table = 'tipos_registro';

    protected $primaryKey = 'id_tipo_registro';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function registrosGes(): HasMany
    {
        return $this->hasMany(RegistroGes::class, 'id_tipo_registro', 'id_tipo_registro');
    }

    public function complejidadRegistros(): HasMany
    {
        return $this->hasMany(ComplejidadRegistro::class, 'id_tipo_registro', 'id_tipo_registro');
    }
}
