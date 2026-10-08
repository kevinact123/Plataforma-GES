<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('registros_ges', 'eliminado_en')) {
            Schema::table('registros_ges', function (Blueprint $table): void {
                $table->timestamp('eliminado_en')->nullable();
            });
        }

        $permisoId = DB::table('permisos')->where('nombre', 'eliminar_registros')->value('id_permiso')
            ?? DB::table('permisos')->insertGetId([
                'nombre' => 'eliminar_registros',
                'descripcion' => 'Eliminar (lógicamente) registros GES',
            ]);

        foreach ([1, 2, 3] as $idRol) {
            DB::table('permisos_roles')->insertOrIgnore(['id_rol' => $idRol, 'id_permiso' => $permisoId]);
        }
    }

    public function down(): void
    {
        $permisoId = DB::table('permisos')->where('nombre', 'eliminar_registros')->value('id_permiso');
        if ($permisoId) {
            DB::table('permisos_roles')->where('id_permiso', $permisoId)->delete();
            DB::table('permisos')->where('id_permiso', $permisoId)->delete();
        }

        if (Schema::hasColumn('registros_ges', 'eliminado_en')) {
            Schema::table('registros_ges', function (Blueprint $table): void {
                $table->dropColumn('eliminado_en');
            });
        }
    }
};
