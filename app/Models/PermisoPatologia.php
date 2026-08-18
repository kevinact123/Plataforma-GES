<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PermisoPatologia extends Model
{
    protected $table = 'permisos_patologia';

    protected $primaryKey = 'id_permiso';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'puede_ver' => 'boolean',
            'puede_editar' => 'boolean',
            'puede_asignar' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_usuario', 'id_usuario');
    }

    public function patologia(): BelongsTo
    {
        return $this->belongsTo(Patologia::class, 'id_patologia', 'id_patologia');
    }
}
