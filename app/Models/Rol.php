<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'descripcion',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'id_rol', 'id_rol');
    }

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(
            Permiso::class,
            'permisos_roles',
            'id_rol',
            'id_permiso',
            'id_rol',
            'id_permiso',
        );
    }

    public function esAdmin(): bool
    {
        return strtolower((string) $this->nombre) === 'administrador';
    }

    public function esDigitadora(): bool
    {
        return strtolower((string) $this->nombre) === 'digitadora';
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->permisos()->where('nombre', $permiso)->exists();
    }
}
