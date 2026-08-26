<?php

namespace App\Models;

use App\Services\RegistroGesAuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistroGes extends Model
{
    public const ESTADO_PENDIENTE = 'Pendiente';

    protected $table = 'registros_ges';

    protected $primaryKey = 'id_registro';

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $fillable = [
        'id_paciente',
        'id_patologia',
        'id_prioridad',
        'id_tipo_registro',
        'tipo_tratamiento',
        'fecha_ingreso',
        'fecha_limite',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'fecha_ingreso' => 'date',
            'fecha_limite' => 'date',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (!$user->activo) {
            return $query->whereKey(-1);
        }

        if ($user->esAdmin()) {
            return $query->whereHas('patologia', fn (Builder $patologiaQuery) => $patologiaQuery->where('activo', true));
        }

        $patologiasPermitidas = $user->permisosPatologia()
            ->where('puede_ver', true)
            ->select('id_patologia');

        return $query->whereHas('patologia', function (Builder $patologiaQuery) use ($patologiasPermitidas): void {
            $patologiaQuery
                ->where('activo', true)
                ->where(function (Builder $visibilityQuery) use ($patologiasPermitidas): void {
                    $visibilityQuery
                        ->where('confidencial', false)
                        ->orWhereIn('id_patologia', $patologiasPermitidas);
                });
        });
    }

    public function scopeByPriority(Builder $query, int $priorityId): Builder
    {
        return $query->where('id_prioridad', $priorityId);
    }

    public function scopeByPathology(Builder $query, int $pathologyId): Builder
    {
        return $query->where('id_patologia', $pathologyId);
    }

    public function scopeByRecordType(Builder $query, int $recordTypeId): Builder
    {
        return $query->where('id_tipo_registro', $recordTypeId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('estado', $status);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->byStatus(self::ESTADO_PENDIENTE);
    }

    public function scopeAssigned(Builder $query): Builder
    {
        return $query->whereHas('asignaciones');
    }

    public function scopeUnassigned(Builder $query): Builder
    {
        return $query->whereDoesntHave('asignaciones');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'id_paciente', 'id_paciente');
    }

    public function patologia(): BelongsTo
    {
        return $this->belongsTo(Patologia::class, 'id_patologia', 'id_patologia');
    }

    public function prioridad(): BelongsTo
    {
        return $this->belongsTo(Prioridad::class, 'id_prioridad', 'id_prioridad');
    }

    public function tipoRegistro(): BelongsTo
    {
        return $this->belongsTo(TipoRegistro::class, 'id_tipo_registro', 'id_tipo_registro');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(Asignacion::class, 'id_registro', 'id_registro');
    }

    public function hitos(): HasMany
    {
        return $this->hasMany(Hito::class, 'id_registro', 'id_registro');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(HistorialRegistro::class, 'id_registro', 'id_registro');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(RegistroGesDocumento::class, 'id_registro', 'id_registro');
    }

    protected static function booted(): void
    {
        static::created(function (self $registro): void {
            app(RegistroGesAuditService::class)->registrar(
                $registro->id_registro,
                request()->user()?->id_usuario,
                'registro_creado',
                null,
                null,
                'creado',
            );
        });

        static::updated(function (self $registro): void {
            $changes = $registro->getDirty();

            foreach ($changes as $campo => $valorNuevo) {
                $valorAnterior = $registro->getOriginal($campo);

                if ($campo === 'fecha_actualizacion' || $campo === 'fecha_creacion') {
                    continue;
                }

                app(RegistroGesAuditService::class)->registrar(
                    $registro->id_registro,
                    request()->user()?->id_usuario,
                    'registro_modificado',
                    $campo,
                    $valorAnterior,
                    $valorNuevo,
                );
            }
        });
    }
}
