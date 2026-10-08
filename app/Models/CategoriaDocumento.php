<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaDocumento extends Model
{
    protected $table = 'documentacion_categorias';

    protected $primaryKey = 'id_categoria';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'nombre',
        'descripcion',
        'id_categoria_padre',
    ];

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id_categoria_padre', 'id_categoria');
    }

    public function hijas(): HasMany
    {
        return $this->hasMany(self::class, 'id_categoria_padre', 'id_categoria');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(DocumentoGeneral::class, 'id_categoria', 'id_categoria');
    }
}
