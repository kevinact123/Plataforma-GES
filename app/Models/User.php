<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
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

    public const TIPO_DIGITADORA_NO_CONFIDENCIAL = 'NO_CONFIDENCIAL';

    public const TIPO_DIGITADORA_CONFIDENCIAL = 'CONFIDENCIAL';

    public const TIPOS_DIGITADORA = [
        self::TIPO_DIGITADORA_NO_CONFIDENCIAL,
        self::TIPO_DIGITADORA_CONFIDENCIAL,
    ];

    protected static function booted(): void
    {
        // tipo_digitadora solo aplica al rol Digitadora; los demás roles lo tienen en NULL.
        static::saving(function (self $user): void {
            if (! $user->isDirty(['id_rol', 'tipo_digitadora']) && $user->exists) {
                return;
            }

            $rol = $user->id_rol ? Rol::query()->find($user->id_rol) : null;

            if (! $rol) {
                return;
            }

            if (strtolower($rol->nombre) !== 'digitadora') {
                $user->tipo_digitadora = null;

                return;
            }

            $user->tipo_digitadora ??= self::TIPO_DIGITADORA_NO_CONFIDENCIAL;

            if (! in_array($user->tipo_digitadora, self::TIPOS_DIGITADORA, true)) {
                throw new \InvalidArgumentException('tipo_digitadora debe ser NO_CONFIDENCIAL o CONFIDENCIAL.');
            }
        });
    }

    protected $fillable = [
        'nombre',
        'apellido',
        'username',
        'correo',
        'password',
        'id_rol',
        'tipo_digitadora',
        'activo',
        'eliminado_en',
        'intentos_fallidos',
        'bloqueado_en',
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
            'eliminado_en' => 'datetime',
            'bloqueado_en' => 'datetime',
            'fecha_creacion' => 'datetime',
            'ultimo_acceso' => 'datetime',
        ];
    }

    public const MAX_INTENTOS_FALLIDOS = 3;

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_en !== null;
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->correo;
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
        return $this->rol?->esAdmin() ?? false;
    }

    public function esAdministradorSistema(): bool
    {
        $username = config('auth.system_admin_username');

        return $this->esAdmin()
            && is_string($username)
            && $username !== ''
            && strcasecmp($this->username, trim($username)) === 0;
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->activo) {
            return false;
        }

        return $this->esAdmin() || ($this->rol?->tienePermiso($permission) ?? false);
    }

    public function permissionNames(): array
    {
        if ($this->esAdmin()) {
            return Permiso::query()->orderBy('nombre')->pluck('nombre')->all();
        }

        return $this->rol?->permisos()
            ->orderBy('nombre')
            ->pluck('nombre')
            ->all() ?? [];
    }

    public function puedeVerPatologia(Patologia $patologia): bool
    {
        if (! $this->activo || ! $patologia->activo || ! $this->puedeAccederAPatologia($patologia)) {
            return false;
        }

        if ($this->esAdmin()
            || (! $this->hasRole('digitadora') && $this->hasPermission('ver_registros'))
            || $this->tieneAccesoConfidencialPorTipo($patologia)) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_ver')
            || $this->tienePermisoPatologia($patologia, 'puede_editar');
    }

    public function puedeEditarPatologia(Patologia $patologia): bool
    {
        if (! $this->activo || ! $patologia->activo || ! $this->puedeAccederAPatologia($patologia)) {
            return false;
        }

        if ($this->esAdmin()
            || (! $this->hasRole('digitadora') && $this->hasPermission('editar_registros'))
            || $this->tieneAccesoConfidencialPorTipo($patologia)) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_editar');
    }

    public function puedeAsignarPatologia(Patologia $patologia): bool
    {
        if (! $this->activo || ! $patologia->activo || ! $this->puedeAccederAPatologia($patologia)) {
            return false;
        }

        if ($this->esAdmin()
            || (! $this->hasRole('digitadora') && $this->hasPermission('asignar_pacientes'))
            || $this->tieneAccesoConfidencialPorTipo($patologia)) {
            return true;
        }

        return $this->tienePermisoPatologia($patologia, 'puede_asignar');
    }

    /**
     * Regla de confidencialidad: solo la digitadora NO_CONFIDENCIAL queda excluida
     * de las patologías confidenciales, sin importar sus permisos por patología.
     */
    public function puedeAccederAConfidenciales(): bool
    {
        if (! $this->activo) {
            return false;
        }

        return ! $this->hasRole('digitadora')
            || $this->tipo_digitadora === self::TIPO_DIGITADORA_CONFIDENCIAL;
    }

    public function puedeAccederAPatologia(Patologia $patologia): bool
    {
        return ! $patologia->confidencial || $this->puedeAccederAConfidenciales();
    }

    public function scopeWithAssignmentAccessTo(Builder $query, Patologia $patologia): Builder
    {
        return $query->where(function (Builder $accessQuery) use ($patologia): void {
            $accessQuery->whereHas('permisosPatologia', fn (Builder $permissionQuery) => $permissionQuery
                ->where('id_patologia', $patologia->getKey())
                ->where('puede_asignar', true));

            if ($patologia->confidencial) {
                $accessQuery->orWhere('tipo_digitadora', self::TIPO_DIGITADORA_CONFIDENCIAL);
            }
        })->when($patologia->confidencial, fn (Builder $confidentialQuery) => $confidentialQuery
            ->where('tipo_digitadora', self::TIPO_DIGITADORA_CONFIDENCIAL));
    }

    private function tienePermisoPatologia(Patologia $patologia, string $permiso): bool
    {
        return $this->activo
            && (bool) $this->permisosPatologia()
                ->where('id_patologia', $patologia->getKey())
                ->where($permiso, true)
                ->exists();
    }

    private function tieneAccesoConfidencialPorTipo(Patologia $patologia): bool
    {
        return $this->hasRole('digitadora')
            && $this->tipo_digitadora === self::TIPO_DIGITADORA_CONFIDENCIAL
            && $patologia->confidencial;
    }
}
