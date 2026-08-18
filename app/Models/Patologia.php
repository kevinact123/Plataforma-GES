<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patologia extends Model
{
    protected $table = 'patologias';

    protected $primaryKey = 'id_patologia';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'confidencial' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function registrosGes(): HasMany
    {
        return $this->hasMany(RegistroGes::class, 'id_patologia', 'id_patologia');
    }

    public function complejidad(): HasOne
    {
        return $this->hasOne(ComplejidadPatologia::class, 'id_patologia', 'id_patologia');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(PermisoPatologia::class, 'id_patologia', 'id_patologia');
    }
}
