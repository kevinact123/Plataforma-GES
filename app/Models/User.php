<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = null;

    protected $fillable = [
        'nombre',
        'apellido',
        'username',
        'password',
        'id_rol',
        'activo',
        'ultimo_acceso',
    ];

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

    public function auditoriaAccesos(): HasMany
    {
        return $this->hasMany(AuditoriaAcceso::class, 'id_usuario', 'id_usuario');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = array_map('strtolower', is_array($roles) ? $roles : [$roles]);
        $nombre = strtolower((string) $this->rol?->nombre);

        return in_array($nombre, $roles, true);
    }

    public function esAdmin(): bool
    {
        return $this->hasRole(['admin', 'administrador', 'superadmin']) || ($this->rol?->esAdmin() ?? false);
    }

    public function puedeVerPatologia(Patologia $patologia): bool
    {
        if (!$this->activo || !$patologia->activo) {
            return false;
        }

        if ($this->esAdmin()) {
            return true;
        }

        if (!$patologia->confidencial) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_ver');
    }

    public function puedeEditarPatologia(Patologia $patologia): bool
    {
        if (!$this->activo || !$patologia->activo) {
            return false;
        }

        if ($this->esAdmin()) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_editar');
    }

    public function puedeAsignarPatologia(Patologia $patologia): bool
    {
        if (!$this->activo || !$patologia->activo) {
            return false;
        }

        if ($this->esAdmin()) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_asignar');
    }

    private function tienePermisoPatologia(Patologia $patologia, string $permiso): bool
    {
        return $this->activo
            && (bool) $this->permisosPatologia()
                ->where('id_patologia', $patologia->getKey())
                ->where($permiso, true)
                ->exists();
    }
}
