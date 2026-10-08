<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = [
        'administrar_usuarios',
        'administrar_patologias',
        'editar_registros',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permisos') || ! Schema::hasTable('permisos_roles')) {
            throw new RuntimeException('No se pueden habilitar las configuraciones del Supervisor: faltan las tablas de roles o permisos.');
        }

        $supervisorId = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['supervisor'])->value('id_rol');
        if ($supervisorId === null) {
            throw new RuntimeException('No se pueden habilitar las configuraciones: no existe el rol Supervisor.');
        }

        foreach (self::PERMISSIONS as $permission) {
            $permissionId = DB::table('permisos')->where('nombre', $permission)->value('id_permiso');
            if ($permissionId === null) {
                throw new RuntimeException("No se puede habilitar la configuración: no existe el permiso {$permission}.");
            }

            DB::table('permisos_roles')->insertOrIgnore([
                'id_rol' => $supervisorId,
                'id_permiso' => $permissionId,
            ]);
        }
    }

    public function down(): void
    {
        $supervisorId = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['supervisor'])->value('id_rol');
        if ($supervisorId === null) {
            return;
        }

        $permissionIds = DB::table('permisos')
            ->whereIn('nombre', self::PERMISSIONS)
            ->pluck('id_permiso');

        DB::table('permisos_roles')
            ->where('id_rol', $supervisorId)
            ->whereIn('id_permiso', $permissionIds)
            ->delete();
    }
};
