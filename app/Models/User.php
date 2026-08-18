<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'fecha_creacion' => 'datetime',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'id_usuario', 'id_usuario');
    }

    public function asignacionesCreadas(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'asignado_por', 'id_usuario');
    }

    public function complejidadRegistros(): HasMany
    {
        return $this->hasMany(ComplejidadRegistro::class, 'id_usuario', 'id_usuario');
    }

    public function permisosPatologia(): HasMany
    {
        return $this->hasMany(PermisoPatologia::class, 'id_usuario', 'id_usuario');
    }

    public function hitos(): HasMany
    {
        return $this->hasMany(Hito::class, 'id_usuario', 'id_usuario');
    }

    public function historialRegistros(): HasMany
    {
        return $this->hasMany(HistorialRegistro::class, 'id_usuario', 'id_usuario');
    }
}
