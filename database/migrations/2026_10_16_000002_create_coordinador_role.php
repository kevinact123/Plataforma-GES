<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NOMBRE = 'Coordinador/a';

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permisos') || ! Schema::hasTable('permisos_roles')) {
            throw new RuntimeException('No se puede crear el rol Coordinador/a: faltan las tablas de roles o permisos.');
        }

        $supervisorId = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['supervisor'])->value('id_rol');
        if ($supervisorId === null) {
            throw new RuntimeException('No se puede crear el rol Coordinador/a: no existe el rol Supervisor.');
        }

        DB::transaction(function () use ($supervisorId): void {
            $coordinadorId = DB::table('roles')->where('nombre', self::NOMBRE)->value('id_rol');

            if ($coordinadorId === null) {
                $coordinadorId = DB::table('roles')->insertGetId([
                    'nombre' => self::NOMBRE,
                    'descripcion' => 'Mismo alcance que Supervisor, sin gestión de usuarios',
                ], 'id_rol');
            }

            $usuariosId = DB::table('permisos')->where('nombre', 'administrar_usuarios')->value('id_permiso');

            DB::table('permisos_roles')
                ->where('id_rol', $supervisorId)
                ->when($usuariosId !== null, fn ($query) => $query->where('id_permiso', '!=', $usuariosId))
                ->pluck('id_permiso')
                ->each(fn ($permissionId) => DB::table('permisos_roles')->insertOrIgnore([
                    'id_rol' => $coordinadorId,
                    'id_permiso' => $permissionId,
                ]));
        });
    }

    public function down(): void
    {
        $coordinadorId = DB::table('roles')->where('nombre', self::NOMBRE)->value('id_rol');
        if ($coordinadorId === null) {
            return;
        }

        if (DB::table('usuarios')->where('id_rol', $coordinadorId)->exists()) {
            throw new RuntimeException('No se puede revertir: hay usuarios con el rol Coordinador/a.');
        }

        DB::table('permisos_roles')->where('id_rol', $coordinadorId)->delete();
        DB::table('roles')->where('id_rol', $coordinadorId)->delete();
    }
};
