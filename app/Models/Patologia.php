<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patologia extends Model
{
    protected $table = 'patologias';

    protected $primaryKey = 'id_patologia';

    public $timestamps = false;

    protected $fillable = [
        'numero_ges',
        'nombre',
        'descripcion',
        'confidencial',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'confidencial' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->esAdmin()
            || (! $user->hasRole('digitadora') && $user->hasPermission('ver_registros'))) {
            return $query;
        }

        $patologiasPermitidas = $user->permisosPatologia()
            ->where(function (Builder $permissionQuery): void {
                $permissionQuery
                    ->where('puede_ver', true)
                    ->orWhere('puede_editar', true);
            })
            ->select('id_patologia');

        return $query->where(function (Builder $pathologyQuery) use ($patologiasPermitidas, $user): void {
            $pathologyQuery->whereIn('id_patologia', $patologiasPermitidas);

            if ($user->hasRole('digitadora')
                && $user->tipo_digitadora === User::TIPO_DIGITADORA_CONFIDENCIAL) {
                $pathologyQuery->orWhere('confidencial', true);
            }
        })->when(! $user->puedeAccederAConfidenciales(), fn (Builder $publicQuery) => $publicQuery
            ->where('confidencial', false));
    }

    public function registrosGes(): HasMany
    {
        return $this->hasMany(RegistroGes::class, 'id_patologia', 'id_patologia');
    }

    public function asociacionesRegistrosGes(): HasMany
    {
        return $this->hasMany(RegistroGesPatologia::class, 'id_patologia', 'id_patologia');
    }

    public function registrosGesAsociados(): BelongsToMany
    {
        return $this->belongsToMany(
            RegistroGes::class,
            'registro_ges_patologias',
            'id_patologia',
            'id_registro',
            'id_patologia',
            'id_registro',
        )->withPivot(['id_registro_patologia', 'tipo', 'observacion', 'fecha_creacion', 'fecha_actualizacion']);
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
