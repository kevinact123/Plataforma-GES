<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $coordinadorId = DB::table('roles')->where('nombre', 'Coordinador/a')->value('id_rol');
        $permissionId = DB::table('permisos')->where('nombre', 'crear_registros')->value('id_permiso');

        if ($coordinadorId === null || $permissionId === null) {
            throw new RuntimeException('No existe el rol Coordinador/a o el permiso crear_registros.');
        }

        DB::table('permisos_roles')->insertOrIgnore([
            'id_rol' => $coordinadorId,
            'id_permiso' => $permissionId,
        ]);
    }

    public function down(): void
    {
        $coordinadorId = DB::table('roles')->where('nombre', 'Coordinador/a')->value('id_rol');
        $permissionId = DB::table('permisos')->where('nombre', 'crear_registros')->value('id_permiso');

        if ($coordinadorId !== null && $permissionId !== null) {
            DB::table('permisos_roles')->where('id_rol', $coordinadorId)->where('id_permiso', $permissionId)->delete();
        }
    }
};
