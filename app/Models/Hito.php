<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Hito extends Model
{
    protected $table = 'hitos';

    protected $primaryKey = 'id_hito';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_completado' => 'datetime',
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
