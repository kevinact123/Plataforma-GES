<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CANONICAL_ROLES = [
        1 => ['nombre' => 'Administrador', 'descripcion' => 'Administración completa del sistema'],
        2 => ['nombre' => 'Supervisor', 'descripcion' => 'Supervisión y distribución de carga laboral'],
        3 => ['nombre' => 'Digitadora', 'descripcion' => 'Digitación y gestión de registros GES'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasColumn('roles', 'id_rol') || ! Schema::hasColumn('roles', 'nombre')) {
            throw new RuntimeException('No se puede normalizar roles: la tabla roles o sus columnas requeridas no existen.');
        }

        if (! Schema::hasTable('usuarios') || ! Schema::hasColumn('usuarios', 'id_rol')) {
            throw new RuntimeException('No se puede normalizar roles: no se encontró usuarios.id_rol.');
        }

        DB::transaction(function (): void {
            $roles = DB::table('roles')->orderBy('id_rol')->get(['id_rol', 'nombre']);
            $groupById = [];
            $roleGroups = [];

            foreach ($roles as $role) {
                $group = $this->groupForName((string) $role->nombre);
                if ($group === null) {
                    throw new RuntimeException(
                        "No se puede normalizar roles: el rol id {$role->id_rol} tiene un nombre no reconocido ({$role->nombre})."
                    );
                }

                $groupById[(int) $role->id_rol] = $group;
                $roleGroups[$group][] = (int) $role->id_rol;
            }

            foreach (self::CANONICAL_ROLES as $targetId => $canonical) {
                if (isset($groupById[$targetId])
                    && $groupById[$targetId] !== $this->groupForName($canonical['nombre'])) {
                    throw new RuntimeException(
                        "No se puede normalizar roles: el id canónico {$targetId} está ocupado por un rol incompatible."
                    );
                }
            }

            $unassignedRoles = DB::table('usuarios')
                ->whereNotNull('id_rol')
                ->whereNotIn('id_rol', array_keys($groupById))
                ->distinct()
                ->pluck('id_rol')
                ->all();

            if ($unassignedRoles !== []) {
                throw new RuntimeException(
                    'No se puede normalizar roles: hay usuarios que referencian roles inexistentes (IDs: '
                    .implode(', ', $unassignedRoles).').'
                );
            }

            $references = $this->roleReferenceColumns();
            $this->makeNamesAvailableForCanonicalRows();

            foreach (self::CANONICAL_ROLES as $targetId => $canonical) {
                if (! DB::table('roles')->where('id_rol', $targetId)->exists()) {
                    DB::table('roles')->insert([
                        'id_rol' => $targetId,
                        'nombre' => $canonical['nombre'],
                        'descripcion' => $canonical['descripcion'],
                    ]);
                }
            }

            foreach ($roleGroups as $group => $sourceIds) {
                $targetId = match ($group) {
                    'admin' => 1,
                    'supervisor' => 2,
                    'digitadora' => 3,
                };

                foreach ($sourceIds as $sourceId) {
                    if ($sourceId === $targetId) {
                        continue;
                    }

                    $this->updateReferences($references, $sourceId, $targetId);
                }
            }

            foreach (self::CANONICAL_ROLES as $targetId => $canonical) {
                DB::table('roles')->where('id_rol', $targetId)->update($canonical);
            }

            $duplicateIds = array_values(array_diff(array_keys($groupById), array_keys(self::CANONICAL_ROLES)));

            foreach ($duplicateIds as $duplicateId) {
                if ($this->hasReferences($references, $duplicateId)) {
                    throw new RuntimeException(
                        "No se puede eliminar el rol duplicado {$duplicateId}: todavía existen referencias."
                    );
                }

                DB::table('roles')->where('id_rol', $duplicateId)->delete();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La normalización de roles no se puede revertir automáticamente porque los roles duplicados se consolidaron.'
        );
    }

    private function groupForName(string $name): ?string
    {
        return match (strtolower(trim($name))) {
            'admin', 'administrador', 'superadmin' => 'admin',
            'supervisor' => 'supervisor',
            'operador', 'digitadora' => 'digitadora',
            default => null,
        };
    }

    private function makeNamesAvailableForCanonicalRows(): void
    {
        foreach (self::CANONICAL_ROLES as $targetId => $canonical) {
            $sameNameRoles = DB::table('roles')
                ->whereRaw('LOWER(nombre) = ?', [strtolower($canonical['nombre'])])
                ->where('id_rol', '<>', $targetId)
                ->get(['id_rol']);

            foreach ($sameNameRoles as $sameNameRole) {
                DB::table('roles')
                    ->where('id_rol', $sameNameRole->id_rol)
                    ->update(['nombre' => '__ges_role_migration_'.$sameNameRole->id_rol]);
            }
        }
    }

    private function roleReferenceColumns(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return array_map(
                fn (object $reference): array => [
                    'table' => $reference->TABLE_NAME,
                    'column' => $reference->COLUMN_NAME,
                ],
                DB::select(
                    'SELECT TABLE_NAME, COLUMN_NAME
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                        AND REFERENCED_TABLE_NAME = ?
                        AND REFERENCED_COLUMN_NAME = ?',
                    ['roles', 'id_rol'],
                ),
            );
        }

        if ($driver === 'sqlite') {
            $references = [];
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

            foreach ($tables as $table) {
                $quotedTable = $connection->getQueryGrammar()->wrapTable($table->name);
                foreach (DB::select("PRAGMA foreign_key_list({$quotedTable})") as $foreignKey) {
                    if ($foreignKey->table === 'roles' && $foreignKey->to === 'id_rol') {
                        $references[] = ['table' => $table->name, 'column' => $foreignKey->from];
                    }
                }
            }

            return $references;
        }

        throw new RuntimeException("No se puede inspeccionar de forma segura las referencias a roles en {$driver}.");
    }

    private function updateReferences(array $references, int $sourceId, int $targetId): void
    {
        foreach ($references as $reference) {
            DB::table($reference['table'])
                ->where($reference['column'], $sourceId)
                ->update([$reference['column'] => $targetId]);
        }
    }

    private function hasReferences(array $references, int $roleId): bool
    {
        foreach ($references as $reference) {
            if (DB::table($reference['table'])->where($reference['column'], $roleId)->exists()) {
                return true;
            }
        }

        return false;
    }
};
