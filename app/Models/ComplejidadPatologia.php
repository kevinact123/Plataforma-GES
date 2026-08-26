<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplejidadPatologia extends Model
{
    protected $table = 'complejidad_patologia';

    protected $primaryKey = 'id_complejidad_patologia';

    public $timestamps = false;

    protected $fillable = [
        'id_patologia',
        'factor',
        'nivel',
        'motivo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function patologia(): BelongsTo
    {
        return $this->belongsTo(Patologia::class, 'id_patologia', 'id_patologia');
    }
}
