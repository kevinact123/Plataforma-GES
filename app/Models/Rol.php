<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    public function esAdmin(): bool
    {
        return in_array(strtolower((string) $this->nombre), ['admin', 'administrador', 'superadmin'], true);
    }

    public function tienePermiso(string $permiso): bool
    {
        return match (strtolower((string) $this->nombre)) {
            'admin', 'administrador', 'superadmin' => true,
            default => false,
        };
    }
}
