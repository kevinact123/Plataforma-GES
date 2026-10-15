<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('permisos') || ! Schema::hasTable('permisos_roles')) {
            throw new RuntimeException('No se puede habilitar la carga de documentos para Supervisor: faltan las tablas de roles o permisos.');
        }

        $supervisorId = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['supervisor'])->value('id_rol');
        if ($supervisorId === null) {
            throw new RuntimeException('No se puede habilitar la carga de documentos: no existe el rol Supervisor.');
        }

        $permissionId = DB::table('permisos')->where('nombre', 'crear_registros')->value('id_permiso');
        if ($permissionId === null) {
            throw new RuntimeException('No se puede habilitar la carga de documentos: no existe el permiso crear_registros.');
        }

        DB::table('permisos_roles')->insertOrIgnore([
            'id_rol' => $supervisorId,
            'id_permiso' => $permissionId,
        ]);
    }

    public function down(): void
    {
        $supervisorId = DB::table('roles')->whereRaw('LOWER(nombre) = ?', ['supervisor'])->value('id_rol');
        $permissionId = DB::table('permisos')->where('nombre', 'crear_registros')->value('id_permiso');

        if ($supervisorId !== null && $permissionId !== null) {
            DB::table('permisos_roles')
                ->where('id_rol', $supervisorId)
                ->where('id_permiso', $permissionId)
                ->delete();
        }
    }
};
